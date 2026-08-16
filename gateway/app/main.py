import asyncio
import hashlib
import json
import logging
import math
import time
import uuid
from dataclasses import dataclass
from datetime import datetime, timezone
from decimal import Decimal
from typing import Any

import asyncpg
import httpx
import redis.asyncio as redis
from fastapi import FastAPI, Header, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse, StreamingResponse

from app import billing
from app.billing import ReservationPayload, SettlementPayload, mark_upstream_started, persist_settlement_payload, release_payload, reserve_payload, settle_with_retry
from app.capabilities import _message_content_parts, model_capabilities, payload_has_audio, payload_has_vision
from app.config import settings
from app.crypto import decrypt_laravel
from app.usage import conservative_token_estimate, estimate_input_tokens, estimate_response_tokens, normalize_usage, observed_output_tokens, request_id_for

app = FastAPI(title=settings.app_name, version="0.2.0", docs_url=None, redoc_url=None, openapi_url=None)
logger = logging.getLogger("azkia.gateway")
STARTED_AT = time.time()

# CORS: banyak klien OpenAI-compatible berbasis browser (Open WebUI, Chatbox, dll.)
# membaca respons langsung dari browser; tanpa ini browser memblokir akses.
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins,
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)
db_pool: asyncpg.Pool | None = None
redis_client: redis.Redis | None = None


@dataclass
class AuthContext:
    api_key_id: int
    user_id: int
    user_status: str
    balance: Decimal
    rate_limit_per_minute: int
    monthly_quota_tokens: int | None
    payg_enabled: bool


class UpstreamHTTPException(HTTPException):
    """HTTPException yang berasal dari upstream (sudah dicatat sebagai billing error)."""


@app.on_event("startup")
async def startup() -> None:
    global db_pool, redis_client
    db_pool = await asyncpg.create_pool(settings.database_url, min_size=1, max_size=10)
    redis_client = redis.from_url(settings.redis_url, decode_responses=True)
    # Rate limit per plan ditegakkan di billing.py; pakai klien Redis yang sama.
    billing.redis_client = redis_client


@app.on_event("shutdown")
async def shutdown() -> None:
    if db_pool:
        await db_pool.close()
    if redis_client:
        await redis_client.aclose()


@app.get("/health")
async def health() -> dict[str, Any]:
    db_ok = False
    if db_pool:
        try:
            async with db_pool.acquire() as conn:
                await conn.fetchval("select 1")
            db_ok = True
        except Exception:
            db_ok = False
    redis_ok = False
    if redis_client:
        try:
            await redis_client.ping()
            redis_ok = True
        except Exception:
            redis_ok = False
    active_models = 0
    if db_ok:
        try:
            active_models = int(await fetch_val("select count(*) from ai_models join providers on providers.id = ai_models.provider_id where ai_models.is_active = true and providers.is_active = true") or 0)
        except Exception:
            pass
    return {
        "status": "ok" if db_ok else "degraded",
        "service": "azkia-gateway",
        "version": app.version,
        "uptime_seconds": int(time.time() - STARTED_AT),
        "database": "ok" if db_ok else "down",
        "redis": "ok" if redis_ok else "down",
        "active_models": active_models,
        "timestamp": datetime.now(timezone.utc).isoformat(),
    }


@app.get("/health/models")
async def health_models(x_health_token: str | None = Header(default=None)) -> Any:
    """Ping langsung ke upstream tiap model aktif (realtime).

    Dipakai halaman Status admin dashboard. Tidak butuh API key user;
    opsional dilindungi header X-Health-Token bila settings.health_token diisi.
    """
    if not settings.health_token:
        raise HTTPException(status_code=503, detail="Health token is not configured")
    if x_health_token != settings.health_token:
        raise HTTPException(status_code=401, detail="Invalid health token")
    rows = await fetch_all(
        """
        select ai_models.id, ai_models.public_name, ai_models.upstream_name,
               providers.base_url, providers.api_key_encrypted,
               exists(select 1 from pricing_rules pr where pr.ai_model_id = ai_models.id and pr.is_active = true) as has_pricing
        from ai_models left join providers on providers.id = ai_models.provider_id
        where ai_models.is_active = true and providers.is_active = true
        order by ai_models.public_name
        """,
    )

    async def ping(row: asyncpg.Record) -> dict[str, Any]:
        base_url = (row["base_url"] or settings.default_upstream_base_url).rstrip("/")
        api_key = resolve_upstream_api_key(dict(row))
        headers = {"Authorization": f"Bearer {api_key}"}
        started = time.time()
        try:
            async with httpx.AsyncClient(timeout=httpx.Timeout(8.0, connect=4.0)) as client:
                resp = await client.get(base_url + "/models", headers=headers)
                if resp.status_code < 400 and resp.content:
                    # Validasi nama model upstream terhadap daftar model provider.
                    # Upstream name yang salah (mis. typo/prefix) tetap terdeteksi
                    # walaupun provider-nya sendiri reachable.
                    try:
                        ids = [str(m.get("id", "")) for m in (resp.json().get("data") or []) if isinstance(m, dict) and m.get("id")]
                    except Exception:
                        ids = []
                    if ids and row["upstream_name"] not in ids:
                        return {
                            "model": row["public_name"],
                            "upstream": row["upstream_name"],
                            "reachable": False,
                            "status_code": None,
                            "latency_ms": int((time.time() - started) * 1000),
                            "has_pricing": bool(row["has_pricing"]),
                            "error": f"Model '{row['upstream_name']}' tidak terdaftar di provider /models",
                        }
                if resp.status_code in (404, 405):
                    # Provider tanpa GET /models: fallback request chat minimal.
                    resp = await client.post(
                        base_url + "/chat/completions",
                        json={"model": row["upstream_name"], "messages": [{"role": "user", "content": "ping"}], "max_tokens": 1},
                        headers={**headers, "Content-Type": "application/json"},
                    )
            latency_ms = int((time.time() - started) * 1000)
            return {
                "model": row["public_name"],
                "upstream": row["upstream_name"],
                "reachable": resp.status_code < 400,
                "status_code": resp.status_code,
                "latency_ms": latency_ms,
                "has_pricing": bool(row["has_pricing"]),
            }
        except Exception as exc:
            latency_ms = int((time.time() - started) * 1000)
            return {
                "model": row["public_name"],
                "upstream": row["upstream_name"],
                "reachable": False,
                "status_code": None,
                "latency_ms": latency_ms,
                "has_pricing": bool(row["has_pricing"]),
                "error": str(exc)[:120],
            }

    results = await asyncio.gather(*(ping(row) for row in rows))
    return {"checked_at": datetime.now(timezone.utc).isoformat(), "data": results}


@app.get("/v1/models")
async def models(request: Request, authorization: str | None = Header(default=None)) -> Any:
    auth = await require_api_key(request, authorization)
    await check_rate_limit(auth)
    rows = await fetch_all("select ai_models.public_name, ai_models.type, ai_models.capabilities from ai_models join providers on providers.id = ai_models.provider_id where ai_models.is_active = true and providers.is_active = true order by ai_models.public_name")
    data = []
    for row in rows:
        item = {"id": row["public_name"], "object": "model", "owned_by": "azkia-router", "type": row["type"]}
        item["capabilities"] = sorted(model_capabilities(dict(row)))
        data.append(item)
    return {"object": "list", "data": data}


@app.post("/v1/chat/completions")
async def chat_completions(request: Request, authorization: str | None = Header(default=None)) -> Any:
    return await handle_openai_request(request, authorization, "/chat/completions")


@app.post("/v1/completions")
async def completions(request: Request, authorization: str | None = Header(default=None)) -> Any:
    return await handle_openai_request(request, authorization, "/completions")


@app.post("/v1/embeddings")
async def embeddings(request: Request, authorization: str | None = Header(default=None)) -> Any:
    return await handle_openai_request(request, authorization, "/embeddings")


async def handle_openai_request(request: Request, authorization: str | None, endpoint: str) -> Any:
    auth = await require_api_key(request, authorization)
    await check_rate_limit(auth)
    payload = await request.json()
    model_name = str(payload.get("model", ""))
    request.state.model = model_name
    route = await resolve_model(model_name)
    await check_model_rate_limit(auth, route)
    caps = model_capabilities(route)
    if endpoint == "/embeddings" and "embedding" not in caps:
        raise HTTPException(status_code=400, detail=f"Model '{model_name}' does not support embeddings")
    if endpoint == "/completions" and "completion" not in caps:
        raise HTTPException(status_code=400, detail=f"Model '{model_name}' does not support completions")
    if endpoint == "/chat/completions" and "chat" not in caps:
        raise HTTPException(status_code=400, detail=f"Model '{model_name}' does not support chat completions")
    if endpoint == "/chat/completions" and "tool" not in caps and (payload.get("tools") or payload.get("functions")):
        raise HTTPException(status_code=400, detail=f"Model '{model_name}' does not support tool/function calling")
    if endpoint == "/chat/completions" and "vision" not in caps and payload_has_vision(payload):
        raise HTTPException(status_code=400, detail=f"Model '{model_name}' does not support vision (image input)")
    if endpoint == "/chat/completions" and "audio" not in caps and payload_has_audio(payload):
        raise HTTPException(status_code=400, detail=f"Model '{model_name}' does not support audio input")
    payload["model"] = route["upstream_name"]
    started = time.time()
    stream = bool(payload.get("stream"))
    if endpoint != "/embeddings" and "max_tokens" not in payload and "max_completion_tokens" not in payload:
        payload["max_tokens"] = settings.default_generation_max_tokens
    # nginx membangun X-Forwarded-For sebagai "$http_x_forwarded_for, $remote_addr",
    # jadi IP asli klien ada di elemen TERAKHIR (elemen awal bisa di-spoof klien).
    xff = (request.headers.get("x-forwarded-for") or "").strip()
    ip_address = xff.split(",")[-1].strip() if xff else (request.client.host if request.client else None)
    user_agent = (request.headers.get("user-agent") or "")[:255] or None

    cache_key = None
    # Cache Redis gateway selalu aktif (dikontrol global settings.cache_enabled);
    # tidak ada lagi izin per-model. Harga cache (read/write) tetap dihitung dari
    # pricing rule saat provider melaporkan prompt caching.
    if settings.cache_enabled and not stream and redis_client:
        cache_key = cache_key_for(endpoint, payload, auth.user_id)
        cached = await cache_get(cache_key)
        if cached is not None and isinstance(cached, dict):
            latency_ms = int((time.time() - started) * 1000)
            normalized = normalize_usage(cached.get("usage"))
            input_tokens = normalized.input_tokens if normalized else estimate_input_tokens(payload)
            output_tokens = normalized.output_tokens if normalized else estimate_response_tokens(cached)
            cost = calculate_snapshot_cost(route, input_tokens, output_tokens)
            request_id = request_id_for(cached.get("id"))
            billing_id, plan_allocations, _ = await create_reservation(auth, route, model_name or route["public_name"], endpoint, input_tokens + output_tokens, cost, ip_address, user_agent)
            await settlement_usage(billing_id, auth, model_name or route["public_name"], endpoint, input_tokens, output_tokens, cost, latency_ms, 200, request_id, ip_address, user_agent, "cache", cache_read=True, plan_allocations=plan_allocations)
            cached["azkia"] = {"latency_ms": latency_ms, "cost": str(cost), "model": model_name or route["public_name"], "cache": "hit"}
            return cached

    estimated_input, estimated_output = conservative_token_estimate(payload, endpoint, settings.default_generation_max_tokens, settings.max_generation_tokens)
    reserved_cost = calculate_snapshot_cost(route, estimated_input, estimated_output, 0, estimated_input)
    billing_id, plan_allocations, _ = await create_reservation(auth, route, model_name or route["public_name"], endpoint, estimated_input + estimated_output, reserved_cost, ip_address, user_agent)
    if stream:
        return await proxy_stream(
            route, endpoint, payload, auth,
            model_name or route["public_name"],
            ip_address, user_agent, started, billing_id, plan_allocations,
        )
    try:
        await mark_upstream_started(db_pool, billing_id)
        response = await proxy(route, endpoint, payload)
    except HTTPException as exc:
        await mark_billing_failed(billing_id, exc.status_code, str(exc.detail))
        raise
    except Exception as exc:
        await mark_billing_failed(billing_id, None, str(exc))
        raise
    latency_ms = int((time.time() - started) * 1000)
    normalized = normalize_usage(response.get("usage") if isinstance(response, dict) else None)
    if normalized:
        input_tokens = normalized.input_tokens
        output_tokens = normalized.output_tokens
        cached_t = normalized.cached_input_tokens
        created_t = normalized.cache_creation_input_tokens
        usage_quality = "reported"
    else:
        input_tokens = estimate_input_tokens(payload)
        output_tokens = 0 if endpoint == "/embeddings" else estimate_response_tokens(response)
        cached_t, created_t = 0, 0
        usage_quality = "estimated"
    cost = calculate_snapshot_cost(route, input_tokens, output_tokens, cached_t, created_t)
    request_id = request_id_for(response.get("id") if isinstance(response, dict) else None)
    await settlement_usage(billing_id, auth, model_name or route["public_name"], endpoint, input_tokens, output_tokens, cost, latency_ms, 200, request_id, ip_address, user_agent, "upstream", cache_write=cache_key is not None and isinstance(response, dict), usage_quality=usage_quality, plan_allocations=plan_allocations)
    if cache_key is not None and isinstance(response, dict):
        await cache_set(cache_key, response, settings.cache_ttl_seconds)
    if isinstance(response, dict):
        response.setdefault("azkia", {"latency_ms": latency_ms, "cost": str(cost), "model": model_name or route["public_name"], "cache": "miss"})
    return response


async def require_api_key(request: Request, authorization: str | None) -> AuthContext:
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Missing bearer token")
    token = authorization.removeprefix("Bearer ").strip()
    if len(token) < 16:
        raise HTTPException(status_code=401, detail="Invalid API key")
    key_hash = hashlib.sha256(token.encode()).hexdigest()
    row = await fetch_one(
        """
        select api_keys.id as api_key_id, api_keys.user_id, api_keys.rate_limit_per_minute,
               api_keys.monthly_quota_tokens, api_keys.expires_at, users.status as user_status,
               users.balance, users.payg_enabled
        from api_keys join users on users.id = api_keys.user_id
        where api_keys.key_hash = $1 and api_keys.is_active = true
        """,
        key_hash,
    )
    if not row:
        raise HTTPException(status_code=401, detail="Invalid API key")
    # Asumsi: expires_at ditulis Laravel dalam UTC (config app.timezone default UTC),
    # kolom timestamp tanpa timezone, jadi bandingkan dengan jam UTC naive.
    if row["expires_at"] is not None and row["expires_at"] <= datetime.now(timezone.utc).replace(tzinfo=None):
        raise HTTPException(status_code=401, detail="API key expired")
    if row["user_status"] != "active":
        raise HTTPException(status_code=403, detail="User suspended")
    # Cek saldo dilakukan di reservasi (billing.py): user dengan plan aktif boleh
    # lanjut walau saldo negatif selama kuota plan mencukupi.
    auth = AuthContext(row["api_key_id"], row["user_id"], row["user_status"], Decimal(str(row["balance"])), row["rate_limit_per_minute"], row["monthly_quota_tokens"], bool(row["payg_enabled"]))
    request.state.auth = auth
    return auth


async def check_rate_limit(auth: AuthContext) -> None:
    if not redis_client:
        return
    bucket = int(time.time() // 60)
    key = f"rate:{auth.api_key_id}:{bucket}"
    try:
        count = await redis_client.incr(key)
        if count == 1:
            await redis_client.expire(key, 90)
    except Exception:
        logger.exception("Rate limiter Redis gagal api_key_id=%s; fail-open", auth.api_key_id)
        return
    if count > auth.rate_limit_per_minute:
        raise HTTPException(status_code=429, detail="Rate limit exceeded")


async def check_model_rate_limit(auth: AuthContext, route: dict[str, Any]) -> None:
    """Rate limit per menit per API key per model (opsional, dari ai_models.rate_limit_per_minute)."""
    limit = route.get("rate_limit_per_minute")
    model_id = route.get("ai_model_id")
    if not limit or model_id is None or not redis_client:
        return
    bucket = int(time.time() // 60)
    key = f"rate:{auth.api_key_id}:model:{model_id}:{bucket}"
    try:
        count = await redis_client.incr(key)
        if count == 1:
            await redis_client.expire(key, 90)
    except Exception:
        logger.exception("Rate limiter model gagal api_key_id=%s model_id=%s; fail-open", auth.api_key_id, model_id)
        return
    if count > int(limit):
        raise HTTPException(status_code=429, detail=f"Rate limit exceeded for model '{route.get('public_name')}'")


async def resolve_model(model_name: str) -> dict[str, Any]:
    row = await fetch_one(
        """
        select ai_models.id as ai_model_id, ai_models.public_name, ai_models.upstream_name,
               ai_models.type, ai_models.capabilities, ai_models.rate_limit_per_minute,
                providers.id as provider_id, providers.base_url, providers.api_key_encrypted,
                case when pricing.is_promo = true
                          and (pricing.promo_starts_at is null or pricing.promo_starts_at <= now())
                          and (pricing.promo_ends_at is null or pricing.promo_ends_at >= now())
                     then pricing.input_per_million else coalesce(pricing.original_input_per_million, pricing.input_per_million) end as input_per_million,
                case when pricing.is_promo = true
                          and (pricing.promo_starts_at is null or pricing.promo_starts_at <= now())
                          and (pricing.promo_ends_at is null or pricing.promo_ends_at >= now())
                     then pricing.output_per_million else coalesce(pricing.original_output_per_million, pricing.output_per_million) end as output_per_million,
                pricing.cache_read_input_per_million, pricing.cache_write_per_million,
                pricing.id as pricing_rule_id, pricing.id is not null as has_pricing

         from ai_models left join providers on providers.id = ai_models.provider_id
         left join lateral (select pr.* from pricing_rules pr where pr.ai_model_id = ai_models.id and pr.is_active = true order by pr.id desc limit 1) pricing on true
         where ai_models.public_name = $1 and ai_models.is_active = true and providers.is_active = true

        limit 1
        """,
        model_name,
    )
    if not row:
        # Tanpa model terdaftar tidak ada pricing rule -> tolak agar tidak ada pemakaian gratis.
        raise HTTPException(status_code=404, detail=f"Model '{model_name}' not found")
    if not row["has_pricing"]:
        # Model terdaftar tapi belum punya pricing rule aktif -> tolak agar tidak gratis.
        raise HTTPException(status_code=402, detail=f"Model '{model_name}' has no active pricing rule")
    return dict(row)


# Cache key upstream hasil dekripsi per provider (provider_id -> plaintext).
# Tidak pernah di-log; hanya disimpan di memori proses.
# Catatan: jika key provider dirotasi di database, perlu restart gateway
# agar cache ter-refresh (cache sengaja permanen selama proses hidup).
_upstream_key_cache: dict[int | str, str] = {}


def resolve_upstream_api_key(route: dict[str, Any]) -> str:
    """Key upstream dari provider (dekripsi kolom api_key_encrypted), fallback ke default .env."""
    encrypted = route.get("api_key_encrypted")
    if encrypted and settings.laravel_app_key:
        cache_key = route.get("provider_id") or encrypted
        if cache_key in _upstream_key_cache:
            return _upstream_key_cache[cache_key]
        try:
            plain = decrypt_laravel(encrypted, settings.laravel_app_key)
            _upstream_key_cache[cache_key] = plain
            return plain
        except Exception:
            # Jangan crash; fallback ke key default .env + log warning (tanpa isi key).
            logger.warning(
                "Gagal mendekripsi api_key_encrypted provider_id=%s; fallback ke default_upstream_api_key",
                route.get("provider_id"),
            )
    return settings.default_upstream_api_key


async def proxy(route: dict[str, Any], path: str, payload: dict[str, Any]) -> Any:
    base_url = (route.get("base_url") or settings.default_upstream_base_url).rstrip("/")
    url = base_url + path
    api_key = resolve_upstream_api_key(route)
    headers = {"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"}
    timeout = httpx.Timeout(180.0, connect=10.0)
    async with httpx.AsyncClient(timeout=timeout) as client:
        upstream = await client.post(url, json=payload, headers=headers)
        if upstream.status_code >= 400:
            raise UpstreamHTTPException(status_code=upstream.status_code, detail=upstream.text)
        return upstream.json()


async def proxy_stream(
    route: dict[str, Any],
    path: str,
    payload: dict[str, Any],
    auth: AuthContext,
    model_name: str,
    ip_address: str | None,
    user_agent: str | None,
    started: float,
    billing_id: str,
    plan_allocations: list[dict[str, Any]] | None = None,
) -> StreamingResponse:
    """Proxy request streaming sambil menghitung token dari aliran SSE dan
    membebankan biaya setelah stream selesai.

    Jika provider mengirim blok \"usage\" (gaya OpenAI), dipakai sebagai acuan.
    Jika tidak, token output diestimasi dari panjang konten delta (~4 char/token)
    dan token input dari isi request. Pemotongan saldo dilakukan setelah stream
    selesai; saldo boleh menjadi minus bila tidak mencukupi.
    """
    base_url = (route.get("base_url") or settings.default_upstream_base_url).rstrip("/")
    url = base_url + path
    api_key = resolve_upstream_api_key(route)
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json",
        "Accept": "text/event-stream",
        "Cache-Control": "no-cache",
    }
    timeout = httpx.Timeout(180.0, connect=10.0)

    upstream_payload = dict(payload)
    if path == "/chat/completions":
        upstream_payload.setdefault("stream_options", {})["include_usage"] = True

    client = httpx.AsyncClient(timeout=timeout)
    req = client.build_request("POST", url, json=upstream_payload, headers=headers)
    try:
        if db_pool:
            await mark_upstream_started(db_pool, billing_id)
        upstream = await client.send(req, stream=True)
        if upstream.status_code >= 400:
            body = await upstream.aread()
            await mark_billing_failed(billing_id, upstream.status_code, body.decode(errors="ignore"))
            raise UpstreamHTTPException(status_code=upstream.status_code, detail=body.decode(errors="ignore"))
    except BaseException as exc:
        await asyncio.shield(client.aclose())
        if not isinstance(exc, HTTPException):
            await asyncio.shield(mark_billing_failed(billing_id, None, str(exc)))
        raise

    input_estimate = estimate_input_tokens(payload)
    stats: dict[str, Any] = {"usage": None, "output_chars": 0, "request_id": None}
    upstream_content_type = upstream.headers.get("content-type", "").lower()

    def _content_chars(value: Any) -> int:
        if value is None:
            return 0
        if isinstance(value, str):
            return len(value)
        if isinstance(value, dict):
            return sum(_content_chars(v) for v in value.values())
        if isinstance(value, (list, tuple)):
            return sum(_content_chars(v) for v in value)
        if isinstance(value, bool):
            return 0
        return len(str(value))

    def handle_data_line(raw: bytes) -> None:
        text = raw.strip().decode(errors="ignore")
        if not text or text == "[DONE]":
            return
        try:
            data = json.loads(text)
        except Exception:
            return
        if not isinstance(data, dict):
            return
        if isinstance(data.get("usage"), dict):
            stats["usage"] = data["usage"]
        if isinstance(data.get("id"), str):
            stats["request_id"] = data["id"]
        choices = data.get("choices")
        if isinstance(choices, list):
            for choice in choices:
                if not isinstance(choice, dict):
                    continue
                if isinstance(choice.get("usage"), dict):
                    stats["usage"] = choice["usage"]
                generated = {key: choice[key] for key in ("text", "delta", "message") if choice.get(key) is not None}
                stats["output_chars"] += _content_chars(generated)

    async def generate_body():
        if "text/event-stream" not in upstream_content_type:
            body = await upstream.aread()
            try:
                data = json.loads(body)
            except Exception:
                yield body
                return
            if isinstance(data, dict):
                handle_data_line(json.dumps(data).encode())
                choices = data.get("choices")
                if isinstance(choices, list) and choices and isinstance(choices[0], dict):
                    choice = choices[0]
                    message = choice.get("message") if isinstance(choice.get("message"), dict) else {}
                    delta: dict[str, Any] = {"role": message.get("role", "assistant")}
                    for field in ("content", "reasoning_content", "tool_calls", "function_call"):
                        if message.get(field) is not None:
                            delta[field] = message[field]
                    chunk = {
                        "id": data.get("id") or "chatcmpl-" + uuid.uuid4().hex,
                        "object": "chat.completion.chunk",
                        "created": data.get("created") or int(time.time()),
                        "model": data.get("model") or payload.get("model"),
                        "choices": [{"index": choice.get("index", 0), "delta": delta, "finish_reason": None}],
                    }
                    yield b"data: " + json.dumps(chunk, ensure_ascii=False).encode() + b"\n\n"
                    final_chunk = {
                        **chunk,
                        "choices": [{"index": choice.get("index", 0), "delta": {}, "finish_reason": choice.get("finish_reason") or "stop"}],
                    }
                    if isinstance(data.get("usage"), dict):
                        final_chunk["usage"] = data["usage"]
                    yield b"data: " + json.dumps(final_chunk, ensure_ascii=False).encode() + b"\n\n"
                else:
                    yield b"data: " + json.dumps(data, ensure_ascii=False).encode() + b"\n\n"
                yield b"data: [DONE]\n\n"
                return

        buffer = b""
        async for chunk in upstream.aiter_bytes():
            if not chunk:
                continue
            buffer += chunk
            while b"\n" in buffer:
                line, buffer = buffer.split(b"\n", 1)
                stripped = line.rstrip(b"\r")
                if stripped.startswith(b"data:"):
                    data_line = stripped[5:].strip()
                    if data_line == b"[DONE]":
                        continue
                    handle_data_line(data_line)
                yield line + b"\n"
        if buffer:
            stripped = buffer.rstrip(b"\r")
            if not (stripped.startswith(b"data:") and stripped[5:].strip() == b"[DONE]"):
                if stripped.startswith(b"data:"):
                    handle_data_line(stripped[5:].strip())
                yield buffer

        if stats["usage"] is None:
            totals = usage_totals()
            final_chunk = {
                "id": stats["request_id"] or ("chatcmpl-" + uuid.uuid4().hex),
                "object": "chat.completion.chunk",
                "created": int(time.time()),
                "model": payload.get("model"),
                "choices": [],
                "usage": {
                    "prompt_tokens": totals["prompt_tokens"],
                    "completion_tokens": totals["completion_tokens"],
                    "total_tokens": totals["total_tokens"],
                },
            }
            yield b"data: " + json.dumps(final_chunk, ensure_ascii=False).encode() + b"\n\n"
        yield b"data: [DONE]\n\n"

    def usage_totals() -> dict[str, int]:
        normalized = normalize_usage(stats["usage"])
        if normalized:
            return {
                "prompt_tokens": normalized.input_tokens,
                "completion_tokens": normalized.output_tokens,
                "total_tokens": normalized.input_tokens + normalized.output_tokens,
                "cached_tokens": normalized.cached_input_tokens,
                "cache_creation_tokens": normalized.cache_creation_input_tokens,
            }
        input_tokens = input_estimate
        output_tokens = observed_output_tokens(stats["output_chars"])
        return {
            "prompt_tokens": input_tokens,
            "completion_tokens": output_tokens,
            "total_tokens": input_tokens + output_tokens,
            "cached_tokens": 0,
            "cache_creation_tokens": 0,
        }

    async def finalize(failure: BaseException | None = None) -> None:
        totals = usage_totals()
        input_tokens = totals["prompt_tokens"]
        output_tokens = totals["completion_tokens"]
        cached_t = totals["cached_tokens"]
        created_t = totals["cache_creation_tokens"]
        latency_ms = int((time.time() - started) * 1000)
        cost = calculate_snapshot_cost(route, input_tokens, output_tokens, cached_t, created_t)
        request_id = request_id_for(stats["request_id"])
        status_code = 499 if isinstance(failure, asyncio.CancelledError) else (502 if failure else 200)
        await settlement_usage(billing_id, auth, model_name, path, input_tokens, output_tokens, cost, latency_ms, status_code, request_id, ip_address, user_agent, "stream", settlement_kind="partial" if failure else "full", usage_quality="reported" if normalize_usage(stats["usage"]) else "estimated", stream_failure_reason=str(failure)[:2000] if failure else None, plan_allocations=plan_allocations)

    async def generate():
        failure: BaseException | None = None
        try:
            async for chunk in generate_body():
                yield chunk
        except BaseException as exc:
            failure = exc
            raise
        finally:
            try:
                await asyncio.shield(upstream.aclose())
                await asyncio.shield(client.aclose())
                await asyncio.shield(finalize(failure))
            except Exception:
                logger.exception("Gagal finalisasi streaming user_id=%s api_key_id=%s", auth.user_id, auth.api_key_id)

    return StreamingResponse(
        generate(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache, no-transform",
            "X-Accel-Buffering": "no",
            "Connection": "keep-alive",
        },
    )


async def calculate_cost(ai_model_id: int | None, input_tokens: int, output_tokens: int, cached_tokens: int = 0, cache_write_tokens: int = 0) -> Decimal:
    if not ai_model_id:
        return Decimal("0")
    # Harga per model sudah final (tanpa margin).
    row = await fetch_one(
        "select case when is_promo = true and (promo_starts_at is null or promo_starts_at <= now()) and (promo_ends_at is null or promo_ends_at >= now()) then input_per_million else coalesce(original_input_per_million, input_per_million) end as input_per_million, "
        "case when is_promo = true and (promo_starts_at is null or promo_starts_at <= now()) and (promo_ends_at is null or promo_ends_at >= now()) then output_per_million else coalesce(original_output_per_million, output_per_million) end as output_per_million, "
        "cache_read_input_per_million, cache_write_per_million "
        "from pricing_rules where ai_model_id = $1 and is_active = true order by id desc limit 1",
        ai_model_id,
    )
    if not row:
        return Decimal("0")
    input_price = Decimal(str(row["input_per_million"]))
    output_price = Decimal(str(row["output_per_million"]))
    # Harga cache opsional: null = tanpa diskon read / tanpa biaya write.
    read_price = Decimal(str(row["cache_read_input_per_million"])) if row["cache_read_input_per_million"] is not None else input_price
    write_price = Decimal(str(row["cache_write_per_million"])) if row["cache_write_per_million"] is not None else Decimal("0")
    # Token cache tidak boleh melebihi total input.
    cached = max(0, min(int(cached_tokens), int(input_tokens)))
    miss = int(input_tokens) - cached
    input_cost = (Decimal(miss) * input_price + Decimal(cached) * read_price) / Decimal("1000000")
    output_cost = Decimal(output_tokens) * output_price / Decimal("1000000")
    write_cost = Decimal(cache_write_tokens) * write_price / Decimal("1000000")
    return input_cost + output_cost + write_cost


def pricing_snapshot(route: dict[str, Any]) -> dict[str, Any]:
    return {key: str(route[key]) if route.get(key) is not None else None for key in ("pricing_rule_id", "input_per_million", "output_per_million", "cache_read_input_per_million", "cache_write_per_million")}


def calculate_snapshot_cost(route: dict[str, Any], input_tokens: int, output_tokens: int, cached_tokens: int = 0, cache_write_tokens: int = 0) -> Decimal:
    input_price = Decimal(str(route["input_per_million"]))
    output_price = Decimal(str(route["output_per_million"]))
    read_price = Decimal(str(route["cache_read_input_per_million"])) if route.get("cache_read_input_per_million") is not None else input_price
    write_price = Decimal(str(route["cache_write_per_million"])) if route.get("cache_write_per_million") is not None else Decimal("0")
    cached = max(0, min(int(cached_tokens), int(input_tokens)))
    return (Decimal(input_tokens - cached) * input_price + Decimal(cached) * read_price + Decimal(output_tokens) * output_price + Decimal(cache_write_tokens) * write_price) / Decimal("1000000")


async def create_reservation(auth: AuthContext, route: dict[str, Any], model: str, endpoint: str, reserved_tokens: int, reserved_cost: Decimal, ip_address: str | None, user_agent: str | None) -> tuple[str, list[dict[str, Any]], int]:
    """Reservasi biaya (PAYG) + kuota plan; kembalikan (billing_id, plan_allocations, payg_tokens)."""
    if not db_pool:
        raise HTTPException(status_code=503, detail="Database unavailable")
    billing_id = str(uuid.uuid4())
    payload = ReservationPayload(auth.user_id, auth.api_key_id, model, endpoint, reserved_tokens, str(reserved_cost), datetime.now(timezone.utc).date().replace(day=1).isoformat(), pricing_snapshot(route), ip_address, user_agent, ai_model_id=route.get("ai_model_id"))
    try:
        await reserve_payload(db_pool, billing_id, payload)
    except ValueError as exc:
        msg = str(exc).lower()
        # 429: rate limit (API key, model, atau plan); 402: masalah saldo/plan (harus bayar).
        if "rate limit" in msg:
            status = 429
        elif "balance" in msg or "plan" in msg:
            status = 402
        else:
            status = 429
        raise HTTPException(status_code=status, detail=str(exc)) from exc
    return billing_id, payload.plan_allocations, payload.payg_tokens


async def mark_billing_failed(billing_id: str, status_code: int | None, reason: str) -> None:
    if not db_pool:
        return
    await release_payload(db_pool, billing_id, reason, status_code)


async def settlement_usage(
    billing_id: str,
    auth: AuthContext,
    model: str,
    endpoint: str,
    input_tokens: int,
    output_tokens: int,
    cost: Decimal,
    latency_ms: int,
    status_code: int,
    upstream_request_id: str,
    ip_address: str | None,
    user_agent: str | None,
    usage_source: str,
    cache_read: bool = False,
    cache_write: bool = False,
    settlement_kind: str = "full",
    usage_quality: str = "reported",
    stream_failure_reason: str | None = None,
    plan_allocations: list[dict[str, Any]] | None = None,
) -> None:
    if not db_pool:
        raise HTTPException(status_code=503, detail="Database unavailable")
    payload = SettlementPayload(
        user_id=auth.user_id,
        api_key_id=auth.api_key_id,
        model=model,
        endpoint=endpoint,
        input_tokens=input_tokens,
        output_tokens=output_tokens,
        cost=str(cost),
        latency_ms=latency_ms,
        status_code=status_code,
        upstream_request_id=upstream_request_id,
        ip_address=ip_address,
        user_agent=user_agent,
        usage_source=usage_source,
        cache_read=cache_read,
        cache_write=cache_write,
        settlement_kind=settlement_kind,
        usage_quality=usage_quality,
        stream_failure_reason=stream_failure_reason,
        plan_allocations=plan_allocations or [],
    )
    await persist_settlement_payload(db_pool, billing_id, payload)
    try:
        await settle_with_retry(db_pool, billing_id, payload)
    except ValueError as exc:
        raise HTTPException(status_code=409, detail=str(exc)) from exc


def cache_key_for(endpoint: str, payload: dict, user_id: int) -> str:
    body = json.dumps(payload, sort_keys=True, separators=(",", ":"), default=str)
    return "cache:" + hashlib.sha256(f"{user_id}|{endpoint}|{body}".encode()).hexdigest()


async def cache_get(key: str) -> Any:
    if not redis_client:
        return None
    try:
        raw = await redis_client.get(key)
    except Exception:
        return None
    if raw is None:
        return None
    try:
        return json.loads(raw)
    except Exception:
        return None


async def cache_set(key: str, value: Any, ttl: int) -> None:
    if not redis_client:
        return
    try:
        await redis_client.set(key, json.dumps(value, default=str), ex=ttl)
    except Exception:
        pass


@app.exception_handler(HTTPException)
async def rejection_handler(request: Request, exc: HTTPException) -> JSONResponse:
    """Catat request /v1/* yang ditolak sebagai audit trail (request_rejections).

    UpstreamHTTPException dilewati karena sudah dicatat sebagai billing error
    (usage_logs dengan usage_source='error') oleh release_payload.
    """
    if not isinstance(exc, UpstreamHTTPException) and request.url.path.startswith("/v1/"):
        await log_rejection(request, exc.status_code, str(exc.detail))
    return JSONResponse({"detail": exc.detail}, status_code=exc.status_code)


async def log_rejection(request: Request, status_code: int, detail: str) -> None:
    if not db_pool:
        return
    auth = getattr(request.state, "auth", None)
    model = getattr(request.state, "model", None)
    xff = (request.headers.get("x-forwarded-for") or "").strip()
    ip_address = xff.split(",")[-1].strip() if xff else (request.client.host if request.client else None)
    user_agent = (request.headers.get("user-agent") or "")[:255] or None
    try:
        await db_pool.execute(
            """
            insert into request_rejections (endpoint, model, status_code, reason, user_id, api_key_id, ip_address, user_agent, created_at)
            values ($1, $2, $3, $4, $5, $6, $7, $8, now())
            """,
            request.url.path,
            (model or "")[:255],
            status_code,
            detail[:1000],
            auth.user_id if auth else None,
            auth.api_key_id if auth else None,
            ip_address,
            user_agent,
        )
    except Exception:
        logger.exception("Gagal mencatat request_rejection path=%s status=%s", request.url.path, status_code)


async def fetch_one(query: str, *args: Any) -> asyncpg.Record | None:
    if not db_pool:
        raise HTTPException(status_code=503, detail="Database unavailable")
    async with db_pool.acquire() as conn:
        return await conn.fetchrow(query, *args)


async def fetch_all(query: str, *args: Any) -> list[asyncpg.Record]:
    if not db_pool:
        raise HTTPException(status_code=503, detail="Database unavailable")
    async with db_pool.acquire() as conn:
        return await conn.fetch(query, *args)


async def fetch_val(query: str, *args: Any) -> Any:
    if not db_pool:
        raise HTTPException(status_code=503, detail="Database unavailable")
    async with db_pool.acquire() as conn:
        return await conn.fetchval(query, *args)


async def execute(query: str, *args: Any) -> None:
    if not db_pool:
        raise HTTPException(status_code=503, detail="Database unavailable")
    async with db_pool.acquire() as conn:
        await conn.execute(query, *args)
