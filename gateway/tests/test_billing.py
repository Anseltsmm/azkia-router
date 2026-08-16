from contextlib import asynccontextmanager
from datetime import date, timedelta

import asyncpg
import pytest

from app import billing
from app.billing import SettlementPayload, settle_event, settle_with_retry


def payload() -> SettlementPayload:
    return SettlementPayload(1, 2, "model", "/chat/completions", 10, 5, "0.250000", 100, 200, "req", None, None, "stream")


class Connection:
    def __init__(self, status: str):
        self.status = status
        self.executed = []

    async def fetchrow(self, query, *args):
        if "billing_events" in query:
            return {"status": self.status, "user_id": 1, "api_key_id": 2, "reserved_cost": "0.250000", "reserved_tokens": 15, "quota_period_start": "2026-08-01"}
        raise AssertionError(query)

    async def execute(self, query, *args):
        self.executed.append((query, args))


@pytest.mark.asyncio
async def test_settled_event_is_idempotent():
    conn = Connection("settled")
    assert await settle_event(conn, "billing-id", payload()) is False
    assert conn.executed == []


@pytest.mark.asyncio
async def test_failed_upstream_event_is_never_settled():
    conn = Connection("failed")
    with pytest.raises(ValueError, match="not reserved"):
        await settle_event(conn, "billing-id", payload())
    assert conn.executed == []


@pytest.mark.asyncio
async def test_transient_settlement_retries_are_bounded(monkeypatch):
    attempts = 0

    async def fail_then_succeed(pool, billing_id, settlement):
        nonlocal attempts
        attempts += 1
        if attempts < 3:
            raise asyncpg.PostgresConnectionError("temporary")
        return True

    async def no_sleep(delay):
        return None

    monkeypatch.setattr(billing, "settle_payload", fail_then_succeed)
    monkeypatch.setattr(billing.asyncio, "sleep", no_sleep)
    assert await settle_with_retry(object(), "billing-id", payload(), attempts=3) is True
    assert attempts == 3


@pytest.mark.asyncio
async def test_transient_settlement_stops_at_limit(monkeypatch):
    attempts = 0

    async def always_fail(pool, billing_id, settlement):
        nonlocal attempts
        attempts += 1
        raise asyncpg.PostgresConnectionError("temporary")

    async def no_sleep(delay):
        return None

    monkeypatch.setattr(billing, "settle_payload", always_fail)
    monkeypatch.setattr(billing.asyncio, "sleep", no_sleep)
    with pytest.raises(asyncpg.PostgresConnectionError):
        await settle_with_retry(object(), "billing-id", payload(), attempts=2)
    assert attempts == 2


class Transaction:
    def __init__(self, conn):
        self.conn = conn

    async def __aenter__(self):
        self.balance = self.conn.balance
        self.executed = list(self.conn.executed)

    async def __aexit__(self, exc_type, exc, traceback):
        if exc_type:
            self.conn.balance = self.balance
            self.conn.executed = self.executed


class SettlementConnection(Connection):
    def __init__(self, balance="1.00"):
        super().__init__("pending")
        self.balance = balance

    def transaction(self):
        return Transaction(self)

    async def fetchrow(self, query, *args):
        if "billing_events" in query:
            return {"status": self.status, "user_id": 1, "api_key_id": 2, "reserved_cost": "0.250000", "reserved_tokens": 15, "quota_period_start": "2026-08-01"}
        if "api_keys" in query:
            return {"monthly_quota_tokens": None}
        if "users" in query:
            return {"balance": self.balance, "reserved_balance": "0.250000"}
        raise AssertionError(query)

    async def execute(self, query, *args):
        self.executed.append((query, args))
        if "update users set balance" in query:
            self.balance = args[0]


class Pool:
    def __init__(self, conn):
        self.conn = conn

    @asynccontextmanager
    async def acquire(self):
        yield self.conn


@pytest.mark.asyncio
async def test_actual_cost_above_reservation_settles_real_cost():
    conn = SettlementConnection("0.10")
    settlement = payload()
    settlement.cost = "0.300000"
    assert await billing.settle_payload(Pool(conn), "billing-id", settlement) is True
    assert conn.balance == billing.Decimal("-0.200000")
    assert len(conn.executed) > 0


@pytest.mark.asyncio
async def test_recover_pending_settles_json_and_marks_permanent_failure(monkeypatch):
    good = payload()
    rows = [
        {"id": "good", "payload": billing.json.dumps(billing.asdict(good))},
        {"id": "bad", "payload": {**billing.asdict(good), "unexpected": True}},
    ]

    class RecoveryConnection:
        def __init__(self):
            self.updates = []

        async def fetch(self, query, *args):
            assert "retry_count<$1" in query
            assert args == (7, 5)
            return rows

        async def execute(self, query, *args):
            self.updates.append((query, args))

    conn = RecoveryConnection()

    async def settle(pool, billing_id, settlement):
        assert billing_id == "good"
        assert settlement == good
        return True

    monkeypatch.setattr(billing, "settle_payload", settle)
    assert await billing.recover_pending(Pool(conn), limit=5, max_retries=7) == (1, 1)
    assert len(conn.updates) == 1
    assert conn.updates[0][1][0:2] == ("bad", 7)


@pytest.mark.asyncio
async def test_recover_pending_schedules_transient_failure(monkeypatch):
    conn = type("RecoveryConnection", (), {})()
    conn.fetch = lambda query, *args: _async_value([{"id": "retry", "payload": billing.asdict(payload())}])
    conn.updates = []

    async def execute(query, *args):
        conn.updates.append((query, args))

    conn.execute = execute

    async def transient(pool, billing_id, settlement):
        raise asyncpg.PostgresConnectionError("temporary")

    monkeypatch.setattr(billing, "settle_payload", transient)
    assert await billing.recover_pending(Pool(conn)) == (0, 1)
    assert "retry_count=retry_count+1" in conn.updates[0][0]
    assert conn.updates[0][1] == ("retry", "temporary")


async def _async_value(value):
    return value


# --- Kuota plan (user_plans) ---


def reservation(total: int = 100, cost: str = "0.700000", ai_model_id: int | None = None):
    return billing.ReservationPayload(1, 2, "model", "/chat/completions", total, cost, "2026-08-01", {"pricing_rule_id": 1}, None, None, ai_model_id=ai_model_id)


class ReserveConnection:
    def __init__(self, plans, balance="10", payg_enabled=True, quota=None):
        self.plans = plans
        self.balance = balance
        self.payg_enabled = payg_enabled
        self.quota = quota
        self.executed = []
        self.plan_updates = []

    async def fetchrow(self, query, *args):
        if "api_keys" in query:
            return {"user_id": 1, "monthly_quota_tokens": self.quota}
        if "users" in query:
            return {"balance": self.balance, "reserved_balance": "0", "status": "active", "payg_enabled": self.payg_enabled}
        if "api_key_monthly_usage" in query:
            return {"settled_tokens": 0, "reserved_tokens": 0}
        raise AssertionError(query)

    async def fetch(self, query, *args):
        if "user_plans" in query:
            return self.plans
        raise AssertionError(query)

    async def execute(self, query, *args):
        self.executed.append((query, args))
        if "user_plans" in query:
            self.plan_updates.append((query, args))


def plan(plan_id=7, remaining=1000, daily_limit=None, daily_used=0, reset_date=None, resets_daily=False, rate_limit=None):
    return {"id": plan_id, "tokens_remaining": remaining, "daily_limit_tokens": daily_limit, "daily_tokens_used": daily_used, "daily_reset_date": reset_date, "resets_daily": resets_daily, "rate_limit_per_minute": rate_limit}


@pytest.mark.asyncio
async def test_reserve_allocates_plan_tokens_first_then_payg():
    conn = ReserveConnection([plan(plan_id=7, remaining=60)])
    payload = reservation(total=100)
    await billing.reserve_event(conn, "bill", payload)
    assert payload.plan_allocations == [{"plan_id": 7, "tokens": 60, "resets_daily": False}]
    assert payload.payg_tokens == 40
    insert = [e for e in conn.executed if "insert into billing_events" in e[0]][0]
    # reserved_cost hanya bagian PAYG: 0.70 * 40/100 = 0.28; plan_tokens = 60
    assert insert[1][7] == billing.Decimal("0.28")
    assert insert[1][12] == 60


@pytest.mark.asyncio
async def test_reserve_fully_covered_by_plan_reserves_nothing_from_balance():
    conn = ReserveConnection([plan(plan_id=7, remaining=500)])
    payload = reservation(total=100)
    await billing.reserve_event(conn, "bill", payload)
    assert payload.plan_allocations == [{"plan_id": 7, "tokens": 100, "resets_daily": False}]
    assert payload.payg_tokens == 0
    insert = [e for e in conn.executed if "insert into billing_events" in e[0]][0]
    assert insert[1][7] == billing.Decimal("0")


@pytest.mark.asyncio
async def test_reserve_plan_rate_limit_exceeded_rejects_before_mutation(monkeypatch):
    class Redis:
        async def incr(self, key):
            return 11

        async def expire(self, key, ttl):
            return True

    monkeypatch.setattr(billing, "redis_client", Redis())
    conn = ReserveConnection([plan(plan_id=7, remaining=1000, rate_limit=10)])
    with pytest.raises(ValueError, match="Rate limit exceeded"):
        await billing.reserve_event(conn, "bill", reservation(total=100))
    # Tidak ada update kuota plan karena transaksi digagalkan sebelum mutasi.
    assert conn.plan_updates == []


@pytest.mark.asyncio
async def test_reserve_plan_rate_limit_allows_under_limit(monkeypatch):
    class Redis:
        async def incr(self, key):
            return 5

        async def expire(self, key, ttl):
            return True

    monkeypatch.setattr(billing, "redis_client", Redis())
    conn = ReserveConnection([plan(plan_id=7, remaining=1000, rate_limit=10)])
    payload = reservation(total=100)
    await billing.reserve_event(conn, "bill", payload)
    assert payload.plan_allocations == [{"plan_id": 7, "tokens": 100, "resets_daily": False}]


@pytest.mark.asyncio
async def test_reserve_plan_rate_limit_redis_failure_is_fail_open(monkeypatch):
    class Redis:
        async def incr(self, key):
            raise ConnectionError("down")

    monkeypatch.setattr(billing, "redis_client", Redis())
    conn = ReserveConnection([plan(plan_id=7, remaining=1000, rate_limit=10)])
    payload = reservation(total=100)
    await billing.reserve_event(conn, "bill", payload)
    assert payload.plan_allocations == [{"plan_id": 7, "tokens": 100, "resets_daily": False}]


@pytest.mark.asyncio
async def test_reserve_rejects_when_payg_disabled_and_plan_insufficient():
    conn = ReserveConnection([plan(plan_id=7, remaining=60)], payg_enabled=False)
    with pytest.raises(ValueError, match="Plan quota exhausted"):
        await billing.reserve_event(conn, "bill", reservation(total=100))


@pytest.mark.asyncio
async def test_reserve_respects_daily_limit():
    conn = ReserveConnection([plan(plan_id=7, remaining=1000, daily_limit=100, daily_used=80, reset_date=date.today())])
    payload = reservation(total=100)
    await billing.reserve_event(conn, "bill", payload)
    assert payload.plan_allocations == [{"plan_id": 7, "tokens": 20, "resets_daily": False}]
    assert payload.payg_tokens == 80
    # daily_reset_date dipertahankan (tanggal sama)
    assert conn.plan_updates[0][1][2] == date.today()


@pytest.mark.asyncio
async def test_reserve_resets_daily_usage_on_new_day():
    conn = ReserveConnection([plan(plan_id=7, remaining=1000, daily_limit=100, daily_used=100, reset_date=date.today() - timedelta(days=1))])
    payload = reservation(total=50)
    await billing.reserve_event(conn, "bill", payload)
    assert payload.plan_allocations == [{"plan_id": 7, "tokens": 50, "resets_daily": False}]
    # reset tanggal diperbarui ke hari ini
    assert conn.plan_updates[0][1][2] == date.today()


@pytest.mark.asyncio
async def test_reserve_free_daily_plan_uses_only_daily_counter():
    conn = ReserveConnection([plan(plan_id=7, remaining=7000000, daily_limit=7000000, daily_used=0, reset_date=None, resets_daily=True)])
    payload = reservation(total=1000)
    await billing.reserve_event(conn, "bill", payload)
    assert payload.plan_allocations == [{"plan_id": 7, "tokens": 1000, "resets_daily": True}]
    assert payload.payg_tokens == 0
    # pool (tokens_remaining) tidak boleh berkurang; reset tanggal di-set hari ini
    assert "tokens_remaining = tokens_remaining -" not in conn.plan_updates[0][0]
    assert conn.plan_updates[0][1][0:3] == (1000, date.today(), 7)


@pytest.mark.asyncio
async def test_reserve_filters_plans_by_requested_model():
    seen = {}

    class ModelConnection(ReserveConnection):
        async def fetch(self, query, *args):
            if "user_plans" in query:
                seen["query"] = query
                seen["args"] = args
                return self.plans
            raise AssertionError(query)

    conn = ModelConnection([plan(plan_id=7, remaining=60)])
    payload = reservation(total=100, ai_model_id=9)
    await billing.reserve_event(conn, "bill", payload)
    # Query harus memfilter plan berdasarkan plan_models + ai_model_id request.
    assert "plan_models" in seen["query"]
    assert "pm.ai_model_id = $2" in seen["query"]
    assert seen["args"] == (1, 9)


@pytest.mark.asyncio
async def test_reserve_plan_filter_uses_sentinel_without_model():
    seen = {}

    class ModelConnection(ReserveConnection):
        async def fetch(self, query, *args):
            if "user_plans" in query:
                seen["args"] = args
                return self.plans
            raise AssertionError(query)

    conn = ModelConnection([plan(plan_id=7, remaining=60)])
    await billing.reserve_event(conn, "bill", reservation(total=100))
    # Tanpa ai_model_id: sentinel -1 sehingga hanya plan tanpa pembatasan yang lolos.
    assert seen["args"] == (1, -1)


@pytest.mark.asyncio
async def test_reserve_free_daily_plan_respects_daily_cap():
    conn = ReserveConnection([plan(plan_id=7, remaining=7000000, daily_limit=7000000, daily_used=6990000, reset_date=date.today(), resets_daily=True)])
    payload = reservation(total=11000)
    await billing.reserve_event(conn, "bill", payload)
    assert payload.plan_allocations == [{"plan_id": 7, "tokens": 10000, "resets_daily": True}]
    assert payload.payg_tokens == 1000


@pytest.mark.asyncio
async def test_settle_free_daily_plan_returns_only_daily_counter():
    conn = PlanSettlementConnection("1.00")
    settlement = payload()  # 15 token
    settlement.cost = "0.300000"
    settlement.plan_allocations = [{"plan_id": 7, "tokens": 100, "resets_daily": True}]
    assert await billing.settle_payload(Pool(conn), "bill", settlement) is True
    # 100 - 15 = 85 dikembalikan ke penghitung harian; pool tidak disentuh
    assert conn.plan_updates[0][1][0:2] == (85, 7)
    assert conn.balance == billing.Decimal("1.00")


@pytest.mark.asyncio
async def test_reserve_consumes_multiple_plans_soonest_expiry_first():
    conn = ReserveConnection([plan(plan_id=1, remaining=30), plan(plan_id=2, remaining=50)])
    payload = reservation(total=100)
    await billing.reserve_event(conn, "bill", payload)
    assert payload.plan_allocations == [{"plan_id": 1, "tokens": 30, "resets_daily": False}, {"plan_id": 2, "tokens": 50, "resets_daily": False}]
    assert payload.payg_tokens == 20


class PlanSettlementConnection(SettlementConnection):
    def __init__(self, balance="1.00"):
        super().__init__(balance)
        self.plan_updates = []

    async def execute(self, query, *args):
        self.executed.append((query, args))
        if "update users set balance" in query:
            self.balance = args[0]
        if "user_plans" in query:
            self.plan_updates.append((query, args))


@pytest.mark.asyncio
async def test_settle_fully_plan_covered_charges_nothing_and_returns_unused():
    conn = PlanSettlementConnection("1.00")
    settlement = payload()  # 10 + 5 = 15 token
    settlement.cost = "0.300000"
    settlement.plan_allocations = [{"plan_id": 7, "tokens": 15}]
    assert await billing.settle_payload(Pool(conn), "bill", settlement) is True
    assert conn.balance == billing.Decimal("1.00")
    assert conn.plan_updates[0][1][0:3] == (0, 0, 7)  # unused = 15 - 15 = 0


@pytest.mark.asyncio
async def test_settle_splits_cost_proportionally_across_plan_and_payg():
    conn = PlanSettlementConnection("1.00")
    settlement = payload()  # 15 token total
    settlement.cost = "0.300000"
    settlement.plan_allocations = [{"plan_id": 7, "tokens": 10}]
    assert await billing.settle_payload(Pool(conn), "bill", settlement) is True
    # plan_covered = 10, payg = 5 -> payg_cost = 0.30 * 5/15 = 0.10
    assert conn.balance == billing.Decimal("0.900000")
    assert conn.plan_updates[0][1][0:3] == (0, 0, 7)  # unused = 10 - 10 = 0


@pytest.mark.asyncio
async def test_settle_returns_unused_reservation_to_plan():
    conn = PlanSettlementConnection("1.00")
    settlement = payload()  # 15 token total
    settlement.cost = "0.300000"
    settlement.plan_allocations = [{"plan_id": 7, "tokens": 100}]  # estimasi berlebih
    assert await billing.settle_payload(Pool(conn), "bill", settlement) is True
    # 100 - 15 = 85 token dikembalikan ke plan; semua tercakup plan -> saldo utuh
    assert conn.plan_updates[0][1][0:3] == (85, 85, 7)
    assert conn.balance == billing.Decimal("1.00")
