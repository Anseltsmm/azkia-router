from decimal import Decimal

import pytest
from fastapi import HTTPException

from app import main


def auth():
    return main.AuthContext(2, 1, "active", Decimal("10"), 3, None, True)


@pytest.mark.asyncio
async def test_health_models_requires_configured_and_matching_token(monkeypatch):
    monkeypatch.setattr(main.settings, "health_token", "")
    with pytest.raises(HTTPException) as exc:
        await main.health_models(None)
    assert exc.value.status_code == 503

    monkeypatch.setattr(main.settings, "health_token", "secret")
    with pytest.raises(HTTPException) as exc:
        await main.health_models("wrong")
    assert exc.value.status_code == 401


@pytest.mark.asyncio
async def test_rate_limit_redis_failure_is_fail_open(monkeypatch):
    class Redis:
        async def incr(self, key):
            raise ConnectionError("down")

    monkeypatch.setattr(main, "redis_client", Redis())
    await main.check_rate_limit(auth())


@pytest.mark.asyncio
async def test_rate_limit_rejects_over_limit(monkeypatch):
    class Redis:
        async def incr(self, key):
            return 4

    monkeypatch.setattr(main, "redis_client", Redis())
    with pytest.raises(HTTPException) as exc:
        await main.check_rate_limit(auth())
    assert exc.value.status_code == 429


@pytest.mark.asyncio
async def test_model_rate_limit_skipped_without_limit():
    await main.check_model_rate_limit(auth(), {"ai_model_id": 3, "rate_limit_per_minute": None, "public_name": "m"})


@pytest.mark.asyncio
async def test_model_rate_limit_rejects_over_limit(monkeypatch):
    class Redis:
        async def incr(self, key):
            return 11

        async def expire(self, key, ttl):
            return True

    monkeypatch.setattr(main, "redis_client", Redis())
    with pytest.raises(HTTPException) as exc:
        await main.check_model_rate_limit(auth(), {"ai_model_id": 3, "rate_limit_per_minute": 10, "public_name": "deepseek/x"})
    assert exc.value.status_code == 429


@pytest.mark.asyncio
async def test_model_rate_limit_redis_failure_is_fail_open(monkeypatch):
    class Redis:
        async def incr(self, key):
            raise ConnectionError("down")

    monkeypatch.setattr(main, "redis_client", Redis())
    await main.check_model_rate_limit(auth(), {"ai_model_id": 3, "rate_limit_per_minute": 10, "public_name": "deepseek/x"})


@pytest.mark.asyncio
async def test_resolve_model_query_excludes_inactive_provider(monkeypatch):
    seen = {}

    async def fetch_one(query, *args):
        seen["query"] = query
        seen["args"] = args
        return None

    monkeypatch.setattr(main, "fetch_one", fetch_one)
    with pytest.raises(HTTPException) as exc:
        await main.resolve_model("missing")
    assert exc.value.status_code == 404
    assert "providers.is_active = true" in seen["query"]
    assert seen["args"] == ("missing",)


class Upstream:
    status_code = 200
    headers = {"content-type": "text/event-stream"}

    def __init__(self, chunks):
        self.chunks = chunks
        self.closed = False

    async def aiter_bytes(self):
        for chunk in self.chunks:
            yield chunk

    async def aclose(self):
        self.closed = True


class Client:
    def __init__(self, upstream):
        self.upstream = upstream
        self.closed = False

    def build_request(self, *args, **kwargs):
        return object()

    async def send(self, request, stream):
        return self.upstream

    async def aclose(self):
        self.closed = True


@pytest.mark.asyncio
async def test_stream_finalize_uses_reported_usage(monkeypatch):
    upstream = Upstream([b'data: {"id":"up-1","usage":{"prompt_tokens":8,"completion_tokens":3},"choices":[]}\n\n', b"data: [DONE]\n\n"])
    client = Client(upstream)
    calls = []

    monkeypatch.setattr(main.httpx, "AsyncClient", lambda timeout: client)

    async def settle(*args, **kwargs):
        calls.append(("settle", args, kwargs))

    monkeypatch.setattr(main, "calculate_snapshot_cost", lambda *args: calls.append(("cost", args)) or Decimal("0.5"))
    monkeypatch.setattr(main, "settlement_usage", settle)
    response = await main.proxy_stream({"ai_model_id": 9, "base_url": "https://upstream", "upstream_name": "x", "input_per_million": "1", "output_per_million": "1"}, "/chat/completions", {"model": "x", "messages": []}, auth(), "public", None, None, main.time.time(), "bill")
    chunks = [chunk async for chunk in response.body_iterator]
    body = b"".join(chunks)
    assert body.count(b"data: [DONE]") == 1
    assert body.endswith(b"data: [DONE]\n\n")
    assert body.index(b'"usage"') < body.index(b"data: [DONE]")
    assert calls[0][1][1:] == (8, 3, 0, 0)
    assert calls[1][1][0:7] == ("bill", auth(), "public", "/chat/completions", 8, 3, Decimal("0.5"))
    assert calls[1][1][9] == "up-1"
    assert upstream.closed and client.closed


@pytest.mark.asyncio
async def test_stream_injects_usage_before_single_done(monkeypatch):
    upstream = Upstream([b'data: {"id":"up-2","choices":[{"delta":{"content":"hello"}}]}\n\n', b"data: [DONE]\n\n"])
    client = Client(upstream)
    monkeypatch.setattr(main.httpx, "AsyncClient", lambda timeout: client)
    monkeypatch.setattr(main, "calculate_snapshot_cost", lambda *args: Decimal("0.1"))

    async def settle(*args, **kwargs):
        return None

    monkeypatch.setattr(main, "settlement_usage", settle)
    response = await main.proxy_stream({"ai_model_id": 9, "base_url": "https://upstream", "input_per_million": "1", "output_per_million": "1"}, "/chat/completions", {"model": "x", "messages": [{"role": "user", "content": "hi"}]}, auth(), "public", None, None, main.time.time(), "bill")
    body = b"".join([chunk async for chunk in response.body_iterator])
    assert body.count(b"data: [DONE]") == 1
    assert body.endswith(b"data: [DONE]\n\n")
    assert b'"choices": []' in body
    assert body.index(b'"usage"') < body.index(b"data: [DONE]")


@pytest.mark.asyncio
async def test_stream_failure_partially_settles(monkeypatch):
    class BrokenUpstream(Upstream):
        async def aiter_bytes(self):
            raise RuntimeError("stream broke")
            yield b""

    upstream = BrokenUpstream([])
    client = Client(upstream)
    settled = []
    monkeypatch.setattr(main.httpx, "AsyncClient", lambda timeout: client)

    async def settle(*args, **kwargs):
        settled.append((args, kwargs))

    monkeypatch.setattr(main, "calculate_snapshot_cost", lambda *args: Decimal("0.1"))
    monkeypatch.setattr(main, "settlement_usage", settle)
    response = await main.proxy_stream({"ai_model_id": 9, "base_url": "https://upstream"}, "/chat/completions", {"model": "x"}, auth(), "public", None, None, main.time.time(), "bill")
    with pytest.raises(RuntimeError, match="stream broke"):
        async for _ in response.body_iterator:
            pass
    assert settled[0][1]["settlement_kind"] == "partial"
    assert settled[0][1]["stream_failure_reason"] == "stream broke"
