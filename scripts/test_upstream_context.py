"""Diagnosis: kirim request kecil vs konteks ~70k token langsung ke upstream Xkiro.

Memakai API key provider (didekripsi dari DB), bukan key user, jadi tidak
menyentuh saldo user. max_tokens sengaja kecil agar biaya minimal.
"""
import asyncio

import asyncpg
import httpx

from app.config import settings
from app.crypto import decrypt_laravel

# (provider_id, base_url, model)
TARGETS = [
    (4, "https://api.xkiro.com/v1", "deepseek/deepseek-v4-pro"),
    (3, "https://api.tokenrouter.com/v1", "deepseek/deepseek-v4-flash"),
    (2, "https://router.bynara.id/v1", "agnes-2.0-flash"),
]


async def main() -> None:
    pool = await asyncpg.create_pool(settings.database_url)
    key_rows = {r["id"]: r["api_key_encrypted"] for r in await pool.fetch("select id, api_key_encrypted from providers where id = any($1)", [t[0] for t in TARGETS])}
    await pool.close()

    # ~70k token ≈ 280k karakter (~4 char/token)
    filler = "Ini adalah teks uji konteks panjang untuk memastikan perilaku model saat konteks membesar. " * 8000
    print(f"panjang filler: {len(filler):,} karakter")

    # 4 karakter/token. Teks bervariasi agar bukan repetisi murni.
    base = (
        "Catatan teknis: sistem distribusi data harus memastikan konsistensi antar layanan. "
        "Setiap modul bertanggung jawab atas validasi input sebelum diteruskan ke lapisan berikutnya. "
        "Kami mencatat semua event ke penyimpanan log terpusat agar mudah diaudit. "
        "Konfigurasi dibaca dari file lingkungan dan di-cache saat proses pertama kali berjalan. "
    )

    async with httpx.AsyncClient(timeout=httpx.Timeout(180.0, connect=15.0)) as client:
        for provider_id, base_url, model in TARGETS:
            encrypted = key_rows.get(provider_id)
            api_key = decrypt_laravel(encrypted, settings.laravel_app_key) if encrypted and settings.laravel_app_key else settings.default_upstream_api_key
            headers = {"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"}
            print(f"\n########## {model} ({base_url}) ##########")
            for label, chars in [
                ("KECIL", 10),
                ("~50k token", 200_000),
                ("~60k token", 240_000),
                ("~130k token", 520_000),
            ]:
                content = base * (chars // len(base)) if chars > 10 else "Halo"
                payload = {
                    "model": model,
                    "messages": [{"role": "user", "content": content}],
                    "max_tokens": 8,
                    "stream": False,
                }
                try:
                    r = await client.post(base_url + "/chat/completions", json=payload, headers=headers)
                    summary = ""
                    if r.status_code == 200:
                        j = r.json()
                        u = j.get("usage") or {}
                        choice = (j.get("choices") or [{}])[0]
                        msg = choice.get("message") or {}
                        summary = f"prompt={u.get('prompt_tokens')} completion={u.get('completion_tokens')} finish={choice.get('finish_reason')} content={msg.get('content')!r:.80}"
                    print(f"  {label} -> HTTP {r.status_code} | {summary}")
                    if r.status_code >= 400:
                        print("   ", r.text[:300])
                except Exception as exc:
                    print(f"  {label} -> ERROR {type(exc).__name__}: {str(exc)[:300]}")


if __name__ == "__main__":
    asyncio.run(main())
