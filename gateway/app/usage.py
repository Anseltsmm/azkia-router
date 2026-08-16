import json
import math
import uuid
from dataclasses import dataclass
from typing import Any


@dataclass(frozen=True)
class NormalizedUsage:
    input_tokens: int
    output_tokens: int
    cached_input_tokens: int = 0
    cache_creation_input_tokens: int = 0
    quality: str = "reported"


def nonnegative_int(value: Any) -> int | None:
    if isinstance(value, bool) or value is None:
        return None
    try:
        parsed = int(value)
    except (TypeError, ValueError, OverflowError):
        return None
    return parsed if parsed >= 0 else None


def normalize_usage(usage: Any) -> NormalizedUsage | None:
    if not isinstance(usage, dict):
        return None
    input_tokens = nonnegative_int(usage.get("prompt_tokens"))
    if input_tokens is None:
        input_tokens = nonnegative_int(usage.get("input_tokens"))
    output_tokens = nonnegative_int(usage.get("completion_tokens"))
    if output_tokens is None:
        output_tokens = nonnegative_int(usage.get("output_tokens"))
    if input_tokens is None and output_tokens is None:
        return None
    input_tokens = input_tokens or 0
    output_tokens = output_tokens or 0
    details = usage.get("prompt_tokens_details")
    cached = nonnegative_int(details.get("cached_tokens")) if isinstance(details, dict) else None
    if cached is None:
        cached = nonnegative_int(usage.get("cache_read_input_tokens")) or 0
    created = nonnegative_int(usage.get("cache_creation_input_tokens")) or 0
    return NormalizedUsage(input_tokens, output_tokens, min(cached, input_tokens), created)


def estimate_input_tokens(payload: dict[str, Any]) -> int:
    billable = {key: payload[key] for key in ("messages", "prompt", "input", "tools", "functions", "response_format") if key in payload}
    serialized = json.dumps(billable, ensure_ascii=False, separators=(",", ":"), default=str)
    return _chars_tokens(len(serialized.encode()))


def conservative_token_estimate(payload: dict[str, Any], endpoint: str, default_output_tokens: int, max_output_tokens: int) -> tuple[int, int]:
    input_tokens = estimate_input_tokens(payload)
    if endpoint == "/embeddings":
        return input_tokens, 0
    requested = payload.get("max_completion_tokens", payload.get("max_tokens", default_output_tokens))
    output_tokens = nonnegative_int(requested)
    if output_tokens is None:
        output_tokens = default_output_tokens
    multiplier = nonnegative_int(payload.get("n")) or 1
    if endpoint == "/completions":
        multiplier = max(multiplier, nonnegative_int(payload.get("best_of")) or 1)
    return input_tokens, min(output_tokens, max_output_tokens) * min(multiplier, 16)


def _chars_tokens(chars: int) -> int:
    if chars <= 0:
        return 0
    base = math.ceil(chars / 4)
    return max(1, base + math.ceil(base * 0.05))


def estimate_response_tokens(response: Any) -> int:
    if not isinstance(response, dict):
        return 0
    content: list[Any] = []
    choices = response.get("choices")
    if isinstance(choices, list):
        for choice in choices:
            if not isinstance(choice, dict):
                continue
            for field in ("text", "message", "delta"):
                if choice.get(field) is not None:
                    content.append(choice[field])
    data = response.get("data")
    if not content and isinstance(data, list):
        content = data
    serialized = json.dumps(content, ensure_ascii=False, separators=(",", ":"), default=str)
    return _chars_tokens(len(serialized.encode()))


def observed_output_tokens(output_chars: int) -> int:
    return _chars_tokens(output_chars)


def request_id_for(upstream_id: Any) -> str:
    if isinstance(upstream_id, str) and upstream_id:
        return upstream_id
    return "azk-" + uuid.uuid4().hex[:16]


def usage_cache_tokens(usage: dict[str, Any]) -> tuple[int, int]:
    normalized = normalize_usage(usage)
    if normalized is None:
        return 0, 0
    return normalized.cached_input_tokens, normalized.cache_creation_input_tokens
