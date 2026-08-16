import asyncio
import json
import logging
import time
from dataclasses import asdict, dataclass, field
from datetime import date
from decimal import Decimal
from typing import Any

import asyncpg
import redis.asyncio as redis

logger = logging.getLogger("azkia.billing")
# Diisi main.py saat startup; dipakai untuk rate limit per plan.
redis_client: redis.Redis | None = None
TERMINAL_STATUSES = {"settled", "partially_settled", "released"}


@dataclass
class ReservationPayload:
    user_id: int
    api_key_id: int
    model: str
    endpoint: str
    reserved_tokens: int
    reserved_cost: str
    quota_period_start: str
    pricing_snapshot: dict[str, Any]
    ip_address: str | None
    user_agent: str | None
    # ai_model_id request: dipakai untuk memfilter plan yang boleh dipakai
    # (plan_models). None = tanpa filter (semua plan).
    ai_model_id: int | None = None
    # Kuota plan (user_plans) yang direservasi untuk request ini, contoh:
    # [{"plan_id": 3, "tokens": 1500}, ...] diurutkan sesuai ekspirasi tercepat.
    plan_allocations: list[dict[str, Any]] = field(default_factory=list)
    # Token yang tidak tercakup plan -> dibebankan PAYG ke saldo (bila payg_enabled).
    payg_tokens: int = 0


@dataclass
class SettlementPayload:
    user_id: int
    api_key_id: int
    model: str
    endpoint: str
    input_tokens: int
    output_tokens: int
    cost: str
    latency_ms: int
    status_code: int
    upstream_request_id: str
    ip_address: str | None
    user_agent: str | None
    usage_source: str
    cache_read: bool = False
    cache_write: bool = False
    settlement_kind: str = "full"
    usage_quality: str = "reported"
    stream_failure_reason: str | None = None
    # Salinan alokasi plan saat reservasi; dipakai settlement/recovery untuk
    # mengembalikan sisa kuota yang tidak terpakai.
    plan_allocations: list[dict[str, Any]] = field(default_factory=list)
    payg_tokens: int = 0


async def reserve_event(conn: asyncpg.Connection, billing_id: str, payload: ReservationPayload) -> None:
    key = await conn.fetchrow("select user_id, monthly_quota_tokens from api_keys where id = $1 and is_active = true for update", payload.api_key_id)
    user = await conn.fetchrow("select balance, reserved_balance, status, payg_enabled from users where id = $1 for update", payload.user_id)
    if not key or not user or key["user_id"] != payload.user_id or user["status"] != "active":
        raise ValueError("Invalid API key")
    reserved_tokens = payload.reserved_tokens
    full_cost = Decimal(payload.reserved_cost)

    # 1) Alokasi kuota plan (per user, semua API key berbagi). Plan yang
    #    paling cepat kedaluwarsa dipakai lebih dulu (plan tanpa expiry = terakhir);
    #    batas harian (bila ada) di-reset mengikuti tanggal server (UTC). Plan
    #    resets_daily (mis. gratis harian) tidak punya pool — kuotanya selalu
    #    = batas harian dan tidak berkurang permanen.
    today = date.today()
    # Plan yang boleh dipakai request ini: plan tanpa pembatasan model (plan_models
    # kosong) atau plan yang mencakup ai_model_id request. Sentinel -1 dipakai saat
    # ai_model_id None agar hanya plan tanpa pembatasan yang lolos.
    model_id = payload.ai_model_id if payload.ai_model_id is not None else -1
    plans = await conn.fetch(
        "select up.id, up.tokens_remaining, up.daily_limit_tokens, up.daily_tokens_used, up.daily_reset_date, up.resets_daily, p.rate_limit_per_minute "
        "from user_plans up "
        "left join plans p on p.id = up.plan_id "
        "where up.user_id = $1 and up.status = 'active' and (up.expires_at is null or up.expires_at > now()) and up.tokens_remaining > 0 "
        "and (not exists (select 1 from plan_models pm where pm.plan_id = up.plan_id) "
        "     or exists (select 1 from plan_models pm where pm.plan_id = up.plan_id and pm.ai_model_id = $2)) "
        "order by up.expires_at asc nulls last for update",
        payload.user_id,
        model_id,
    )
    allocations: list[dict[str, Any]] = []
    remaining = reserved_tokens
    for plan in plans:
        if remaining <= 0:
            break
        plan_id = int(plan["id"])
        resets_daily = bool(plan["resets_daily"])
        if plan["daily_limit_tokens"] is not None:
            if plan["daily_reset_date"] is None or plan["daily_reset_date"] != today:
                daily_used, reset_date = 0, today
            else:
                daily_used, reset_date = int(plan["daily_tokens_used"]), plan["daily_reset_date"]
            available = max(0, int(plan["daily_limit_tokens"]) - daily_used)
        else:
            available, reset_date = int(plan["tokens_remaining"]), None
        take = min(remaining, int(plan["tokens_remaining"]), available)
        if take <= 0:
            continue
        # Rate limit per menit per user per plan (bila plan mengaturnya).
        # Gagal Redis = fail-open (sama seperti rate limit API key).
        limit = plan.get("rate_limit_per_minute")
        if limit and redis_client:
            bucket = int(time.time() // 60)
            rkey = f"rate:plan:{payload.user_id}:{plan_id}:{bucket}"
            try:
                count = await redis_client.incr(rkey)
                if count == 1:
                    await redis_client.expire(rkey, 90)
            except Exception:
                logger.exception("Rate limiter plan gagal user_id=%s plan_id=%s; fail-open", payload.user_id, plan_id)
            else:
                if count > int(limit):
                    raise ValueError(f"Rate limit exceeded (plan quota: {int(limit)}/menit)")
        allocations.append({"plan_id": plan_id, "tokens": take, "resets_daily": resets_daily})
        if resets_daily:
            # Pool tidak berkurang; hanya penghitung harian yang naik.
            await conn.execute(
                "update user_plans set daily_tokens_used = daily_tokens_used + $1, "
                "daily_reset_date = coalesce($2, daily_reset_date), updated_at = now() where id = $3",
                take, reset_date, plan_id,
            )
        else:
            await conn.execute(
                "update user_plans set tokens_remaining = tokens_remaining - $1, "
                "daily_tokens_used = daily_tokens_used + $2, "
                "daily_reset_date = coalesce($3, daily_reset_date), "
                "status = case when tokens_remaining - $1 <= 0 then 'consumed' else status end, "
                "updated_at = now() where id = $4",
                take, take, reset_date, plan_id,
            )
        remaining -= take

    plan_tokens = reserved_tokens - remaining
    payg_tokens = remaining

    # 2) Bagian yang tidak tercakup plan wajib dijamin saldo PAYG. Bila PAYG
    #    nonaktif dan kuota plan tidak mencukupi -> tolak request.
    if payg_tokens > 0:
        if not user["payg_enabled"]:
            raise ValueError("Plan quota exhausted (PAYG disabled)")
        payg_cost = full_cost * Decimal(payg_tokens) / Decimal(reserved_tokens) if reserved_tokens > 0 else Decimal("0")
        available = Decimal(str(user["balance"])) - Decimal(str(user["reserved_balance"]))
        if payg_cost > available:
            raise ValueError("Insufficient balance")
        reserved_cost = payg_cost
    else:
        reserved_cost = Decimal("0")
    payload.plan_allocations = allocations
    payload.payg_tokens = payg_tokens

    # 3) Kuota bulanan API key tetap dihitung dari TOTAL token (termasuk plan).
    await conn.execute(
        "insert into api_key_monthly_usage (api_key_id, period_start, settled_tokens, reserved_tokens, created_at, updated_at) values ($1,$2::text::date,0,0,now(),now()) on conflict (api_key_id, period_start) do nothing",
        payload.api_key_id, payload.quota_period_start,
    )
    counter = await conn.fetchrow("select settled_tokens, reserved_tokens from api_key_monthly_usage where api_key_id = $1 and period_start = $2::text::date for update", payload.api_key_id, payload.quota_period_start)
    quota = key["monthly_quota_tokens"]
    if quota is not None and int(counter["settled_tokens"]) + int(counter["reserved_tokens"]) + reserved_tokens > int(quota):
        raise ValueError("Monthly token quota exceeded")
    await conn.execute("update users set reserved_balance = reserved_balance + $1, updated_at = now() where id = $2", reserved_cost, payload.user_id)
    await conn.execute("update api_key_monthly_usage set reserved_tokens = reserved_tokens + $3, updated_at = now() where api_key_id = $1 and period_start = $2::text::date", payload.api_key_id, payload.quota_period_start, reserved_tokens)
    await conn.execute(
        """
        insert into billing_events (id,user_id,api_key_id,model,endpoint,ip_address,user_agent,status,reserved_cost,reserved_tokens,quota_period_start,pricing_snapshot,reserved_at,payload,plan_tokens,created_at,updated_at)
        values ($1,$2,$3,$4,$5,$6,$7,'reserved',$8,$9,$10::text::date,$11::jsonb,now(),$12::jsonb,$13,now(),now())
        """,
        billing_id, payload.user_id, payload.api_key_id, payload.model, payload.endpoint,
        payload.ip_address, payload.user_agent, reserved_cost, reserved_tokens,
        payload.quota_period_start, json.dumps(payload.pricing_snapshot), json.dumps(asdict(payload)), plan_tokens,
    )


async def reserve_payload(pool: asyncpg.Pool, billing_id: str, payload: ReservationPayload) -> None:
    async with pool.acquire() as conn:
        async with conn.transaction():
            await reserve_event(conn, billing_id, payload)


async def mark_upstream_started(pool: asyncpg.Pool, billing_id: str) -> None:
    async with pool.acquire() as conn:
        await conn.execute("update billing_events set upstream_started_at = coalesce(upstream_started_at, now()), updated_at = now() where id = $1 and status = 'reserved'", billing_id)


async def persist_settlement_payload(pool: asyncpg.Pool, billing_id: str, payload: SettlementPayload) -> None:
    async with pool.acquire() as conn:
        result = await conn.execute(
            "update billing_events set payload=$2::jsonb,input_tokens=$3,output_tokens=$4,cost=$5,latency_ms=$6,status_code=$7,upstream_request_id=$8,usage_source=$9,next_retry_at=now(),failure_reason=null,last_error=null,updated_at=now() where id=$1 and status in ('pending','reserved')",
            billing_id, json.dumps(asdict(payload)), payload.input_tokens, payload.output_tokens,
            Decimal(payload.cost), payload.latency_ms, payload.status_code, payload.upstream_request_id, payload.usage_source,
        )
    if result == "UPDATE 0":
        async with pool.acquire() as conn:
            status = await conn.fetchval("select status from billing_events where id = $1", billing_id)
        if status not in TERMINAL_STATUSES:
            raise ValueError("Billing event not reservable")


async def settle_event(conn: asyncpg.Connection, billing_id: str, payload: SettlementPayload) -> bool:
    event = await conn.fetchrow("select status,user_id,api_key_id,model,endpoint,ip_address,user_agent,reserved_cost,reserved_tokens,quota_period_start from billing_events where id=$1 for update", billing_id)
    if not event:
        raise ValueError("Billing event not found")
    if event["status"] in TERMINAL_STATUSES:
        return False
    if event["status"] not in ("pending", "reserved"):
        raise ValueError("Billing event is not reserved")
    if event["user_id"] != payload.user_id or event["api_key_id"] != payload.api_key_id:
        raise ValueError("Billing event mismatch")
    user = await conn.fetchrow("select balance,reserved_balance from users where id=$1 for update", payload.user_id)
    if not user:
        raise ValueError("Invalid API key")
    full_cost = Decimal(payload.cost)
    reserved_cost = Decimal(str(event["reserved_cost"] or 0))
    tokens = payload.input_tokens + payload.output_tokens
    reserved_tokens = int(event["reserved_tokens"] or 0)

    # 1) Selesaikan kuota plan yang direservasi: token yang benar-benar dipakai
    #    diambil dari plan, sisanya (estimasi berlebih) dikembalikan ke plan.
    plan_covered = 0
    allocations = payload.plan_allocations or []
    if allocations:
        remaining_actual = tokens
        for alloc in allocations:
            if remaining_actual <= 0:
                break
            plan_id = int(alloc["plan_id"])
            reserved_plan = int(alloc["tokens"])
            actual_plan = min(reserved_plan, remaining_actual)
            remaining_actual -= actual_plan
            plan_covered += actual_plan
            unused = reserved_plan - actual_plan
            if alloc.get("resets_daily"):
                # Plan reset harian: pool tidak pernah berkurang, cukup kembalikan penghitung harian.
                await conn.execute(
                    "update user_plans set daily_tokens_used = greatest(0, daily_tokens_used - $1), "
                    "updated_at = now() where id = $2",
                    unused, plan_id,
                )
            else:
                await conn.execute(
                    "update user_plans set tokens_remaining = tokens_remaining + $1, "
                    "daily_tokens_used = greatest(0, daily_tokens_used - $2), "
                    "status = case when status = 'consumed' and tokens_remaining + $1 > 0 then 'active' else status end, "
                    "updated_at = now() where id = $3",
                    unused, unused, plan_id,
                )

    # 2) Biaya PAYG proporsional terhadap token yang tidak tercakup plan.
    payg_tokens = tokens - plan_covered
    cost = full_cost * Decimal(payg_tokens) / Decimal(tokens) if tokens > 0 else Decimal("0")
    plan_id_col = allocations[0]["plan_id"] if allocations else None

    balance_before = Decimal(str(user["balance"]))
    balance_after = balance_before - cost
    await conn.execute("update users set balance=$1,reserved_balance=greatest(0,reserved_balance-$2),updated_at=now() where id=$3", balance_after, reserved_cost, payload.user_id)
    await conn.execute("update api_key_monthly_usage set reserved_tokens=greatest(0,reserved_tokens-$3),settled_tokens=settled_tokens+$4,updated_at=now() where api_key_id=$1 and period_start=$2", payload.api_key_id, event["quota_period_start"], reserved_tokens, tokens)
    await conn.execute(
        "insert into usage_logs (user_id,api_key_id,model,endpoint,input_tokens,output_tokens,cost,latency_ms,status_code,request_id,upstream_request_id,billing_id,usage_source,ip_address,user_agent,cache_read,cache_write,usage_quality,plan_id,plan_tokens,created_at,updated_at) values ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19,now(),now()) on conflict (billing_id) do nothing",
        payload.user_id,payload.api_key_id,payload.model,payload.endpoint,payload.input_tokens,payload.output_tokens,cost,payload.latency_ms,payload.status_code,payload.upstream_request_id,billing_id,payload.usage_source,payload.ip_address,payload.user_agent,payload.cache_read,payload.cache_write,payload.usage_quality,plan_id_col,plan_covered,
    )
    if cost > 0:
        await conn.execute("insert into transactions (user_id,type,amount,balance_before,balance_after,currency,status,reference,billing_id,notes,created_at,updated_at) values ($1,'usage',$2,$3,$4,'USD','completed',$5,$6,$7,now(),now()) on conflict (billing_id) do nothing", payload.user_id,-cost,balance_before,balance_after,payload.upstream_request_id,billing_id,f"{payload.model} · {payload.endpoint}")
    status = "partially_settled" if payload.settlement_kind == "partial" else "settled"
    await conn.execute("update api_keys set last_used_at=now(),updated_at=now() where id=$1", payload.api_key_id)
    await conn.execute("update billing_events set model=$2,endpoint=$3,input_tokens=$4,output_tokens=$5,cost=$6,latency_ms=$7,status_code=$8,upstream_request_id=$9,usage_source=$10,ip_address=$11,user_agent=$12,status=$13,settlement_kind=$14,usage_quality=$15,stream_failure_reason=$16,plan_tokens=$17,settled_at=now(),released_at=now(),next_retry_at=null,last_error=null,updated_at=now() where id=$1 and status in ('pending','reserved')", billing_id,payload.model,payload.endpoint,payload.input_tokens,payload.output_tokens,cost,payload.latency_ms,payload.status_code,payload.upstream_request_id,payload.usage_source,payload.ip_address,payload.user_agent,status,payload.settlement_kind,payload.usage_quality,payload.stream_failure_reason,plan_covered)
    return True


async def release_event(conn: asyncpg.Connection, billing_id: str, reason: str, status_code: int | None = None) -> bool:
    event = await conn.fetchrow("select status,user_id,api_key_id,model,endpoint,ip_address,user_agent,reserved_cost,reserved_tokens,quota_period_start,payload from billing_events where id=$1 for update", billing_id)
    if not event:
        raise ValueError("Billing event not found")
    if event["status"] in TERMINAL_STATUSES:
        return False
    if event["status"] == "pending" and event["reserved_cost"] is None:
        await conn.execute("update billing_events set status='failed',status_code=$2,failure_reason=$3,updated_at=now() where id=$1", billing_id,status_code,reason[:2000])
        return True
    await conn.execute("select id from users where id=$1 for update", event["user_id"])
    await conn.execute("update users set reserved_balance=greatest(0,reserved_balance-$1),updated_at=now() where id=$2", Decimal(str(event["reserved_cost"] or 0)),event["user_id"])
    await conn.execute("update api_key_monthly_usage set reserved_tokens=greatest(0,reserved_tokens-$3),updated_at=now() where api_key_id=$1 and period_start=$2",event["api_key_id"],event["quota_period_start"],int(event["reserved_tokens"] or 0))
    # Kembalikan kuota plan yang direservasi ke user_plans.
    raw = event["payload"]
    if raw:
        try:
            raw_obj = json.loads(raw) if isinstance(raw, str) else raw
            for alloc in raw_obj.get("plan_allocations") or []:
                if alloc.get("resets_daily"):
                    await conn.execute(
                        "update user_plans set daily_tokens_used = greatest(0, daily_tokens_used - $1), "
                        "updated_at = now() where id = $2",
                        int(alloc["tokens"]), int(alloc["plan_id"]),
                    )
                else:
                    await conn.execute(
                        "update user_plans set tokens_remaining = tokens_remaining + $1, "
                        "daily_tokens_used = greatest(0, daily_tokens_used - $2), "
                        "status = case when status = 'consumed' and tokens_remaining + $1 > 0 then 'active' else status end, "
                        "updated_at = now() where id = $3",
                        int(alloc["tokens"]), int(alloc["tokens"]), int(alloc["plan_id"]),
                    )
        except Exception:
            logger.exception("Gagal mengembalikan kuota plan billing_id=%s", billing_id)
    request_id = "azk-" + billing_id.replace("-", "")[:16]
    await conn.execute(
        "insert into usage_logs (user_id,api_key_id,model,endpoint,input_tokens,output_tokens,cost,latency_ms,status_code,request_id,upstream_request_id,billing_id,usage_source,ip_address,user_agent,cache_read,cache_write,created_at,updated_at) values ($1,$2,$3,$4,0,0,0,null,$5,$6,null,$7,'error',$8,$9,false,false,now(),now()) on conflict (billing_id) do nothing",
        event["user_id"],event["api_key_id"],event["model"],event["endpoint"],status_code or 500,request_id,billing_id,event["ip_address"],event["user_agent"],
    )
    await conn.execute("update billing_events set status='released',status_code=$2,failure_reason=$3,released_at=now(),settlement_kind='released',updated_at=now() where id=$1",billing_id,status_code or 500,reason[:2000])
    return True


async def release_payload(pool: asyncpg.Pool, billing_id: str, reason: str, status_code: int | None = None) -> bool:
    async with pool.acquire() as conn:
        async with conn.transaction():
            return await release_event(conn,billing_id,reason,status_code)


async def settle_payload(pool: asyncpg.Pool, billing_id: str, payload: SettlementPayload) -> bool:
    async with pool.acquire() as conn:
        async with conn.transaction():
            return await settle_event(conn,billing_id,payload)


async def settle_with_retry(pool: asyncpg.Pool, billing_id: str, payload: SettlementPayload, attempts: int = 3) -> bool:
    for attempt in range(attempts):
        try:
            return await settle_payload(pool,billing_id,payload)
        except (asyncpg.PostgresConnectionError,asyncpg.CannotConnectNowError,asyncio.TimeoutError):
            if attempt + 1 == attempts:
                raise
            await asyncio.sleep(0.1 * (2 ** attempt))
    return False


async def recover_pending(pool: asyncpg.Pool, limit: int = 100, max_retries: int = 12) -> tuple[int,int]:
    async with pool.acquire() as conn:
        rows = await conn.fetch("select id,payload from billing_events where status in ('pending','reserved') and payload is not null and retry_count<$1 and (next_retry_at is null or next_retry_at<=now()) order by created_at limit $2",max_retries,limit)
    settled = failed = 0
    for row in rows:
        billing_id = str(row["id"])
        try:
            raw = json.loads(row["payload"]) if isinstance(row["payload"],str) else row["payload"]
            if "input_tokens" not in raw:
                continue
            if await settle_payload(pool,billing_id,SettlementPayload(**raw)):
                settled += 1
        except (asyncpg.PostgresConnectionError,asyncpg.CannotConnectNowError,asyncio.TimeoutError) as exc:
            failed += 1
            async with pool.acquire() as conn:
                await conn.execute("update billing_events set retry_count=retry_count+1,last_attempt_at=now(),last_error=$2,next_retry_at=now()+make_interval(secs=>least(300,power(2,retry_count)::int)),updated_at=now() where id=$1 and status in ('pending','reserved')",billing_id,str(exc)[:2000])
        except Exception as exc:
            failed += 1
            async with pool.acquire() as conn:
                await conn.execute("update billing_events set retry_count=$2,last_attempt_at=now(),last_error=$3,next_retry_at=null,updated_at=now() where id=$1 and status in ('pending','reserved')",billing_id,max_retries,str(exc)[:2000])
    return settled,failed
