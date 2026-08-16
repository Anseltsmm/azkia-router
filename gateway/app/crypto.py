import base64
import hashlib
import hmac
import json

from cryptography.hazmat.primitives import padding
from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes


def decrypt_laravel(payload_b64: str, app_key_b64: str) -> str:
    """Dekripsi nilai yang dibuat Laravel \"Crypt::encryptString\" (AES-256-CBC + HMAC-SHA256).

    Format payload: base64(json({"iv": b64, "value": b64, "mac": hex})) dengan
    mac = HMAC-SHA256(iv_b64 + value_b64) menggunakan raw key yang sama.
    APP_KEY di .env Laravel berbentuk \"base64:...\".
    """
    raw_key = base64.b64decode(app_key_b64.removeprefix("base64:"))
    data = json.loads(base64.b64decode(payload_b64))
    iv = base64.b64decode(data["iv"])
    value = base64.b64decode(data["value"])
    expected = hmac.new(raw_key, (data["iv"] + data["value"]).encode(), hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, data["mac"]):
        raise ValueError("MAC verification failed")
    cipher = Cipher(algorithms.AES(raw_key), modes.CBC(iv))
    decryptor = cipher.decryptor()
    padded = decryptor.update(value) + decryptor.finalize()
    unpadder = padding.PKCS7(algorithms.AES.block_size).unpadder()
    return (unpadder.update(padded) + unpadder.finalize()).decode()
