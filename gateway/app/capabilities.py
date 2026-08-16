import json
from typing import Any


def _message_content_parts(payload: dict[str, Any]) -> list[dict[str, Any]]:
    """Part konten multimodal dari messages (format OpenAI: content berupa array)."""
    parts: list[dict[str, Any]] = []
    messages = payload.get("messages")
    if not isinstance(messages, list):
        return parts
    for m in messages:
        if not isinstance(m, dict):
            continue
        content = m.get("content")
        if isinstance(content, list):
            parts.extend(p for p in content if isinstance(p, dict))
        elif isinstance(content, dict):
            parts.append(content)
    return parts


def payload_has_vision(payload: dict[str, Any]) -> bool:
    return any(p.get("type") == "image_url" for p in _message_content_parts(payload))


def payload_has_audio(payload: dict[str, Any]) -> bool:
    return any(p.get("type") == "input_audio" for p in _message_content_parts(payload))


def model_capabilities(route: dict[str, Any]) -> set[str]:
    """Kemampuan model dari kolom capabilities (JSON array).

    Model lama yang belum punya capabilities terisi diturunkan dari kolom type
    agar perilaku lama tidak berubah (mis. type=embedding -> hanya embedding).
    """
    caps = route.get("capabilities")
    if isinstance(caps, str):
        try:
            caps = json.loads(caps)
        except Exception:
            caps = None
    if isinstance(caps, list):
        clean = {str(c).strip().lower() for c in caps if str(c).strip()}
        if clean:
            return clean
    t = str(route.get("type") or "chat").strip().lower()
    return {t} if t else {"chat"}
