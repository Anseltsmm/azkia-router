# Azkia Router

**Satu API key untuk berbagai model AI.** Platform AI API gateway dengan billing otomatis (IDR), sistem plan & kuota token, rate limiting, dan dashboard monitoring — sepenuhnya **OpenAI-compatible**, sehingga bisa langsung dipakai dari tools favorit (OpenCode, Cline, Aider, Continue, dan lainnya).

```
┌──────────────────┐     ┌──────────────────────┐     ┌─────────────────────┐
│  Coding tools &  │────▶│   FastAPI Gateway    │────▶│  Upstream providers │
│  AI agents       │     │   api.azkia.cloud    │     │  (OpenAI, dsb.)     │
└──────────────────┘     └──────────┬───────────┘     └─────────────────────┘
                                    │ auth + billing + rate limit
                          ┌─────────▼──────────┐
                          │  Laravel Dashboard │   admin.azkia.cloud
                          │  azkia.cloud        │   (panel admin)
                          └────────────────────┘
```

---

## Daftar Isi

1. [Fitur](#fitur)
2. [Arsitektur](#arsitektur)
3. [Tech Stack](#tech-stack)
4. [Struktur Repo](#struktur-repo)
5. [Instalasi](#instalasi)
6. [Konfigurasi](#konfigurasi)
7. [Penggunaan API](#penggunaan-api)
8. [Sistem Plan, Kuota & Billing](#sistem-plan-kuota--billing)
9. [Panel Admin](#panel-admin)
10. [Deployment & Service](#deployment--service)
11. [Backup & Migrasi](#backup--migrasi)
12. [Troubleshooting](#troubleshooting)
13. [Keamanan](#keamanan)

---

## Fitur

### Untuk Pengguna
- **Login Google OAuth** — registrasi email/password dinonaktifkan; semua akun baru via Google
- **API key management** — buat/atur API key, toggle aktif/nonaktif, set masa berlaku
- **Sistem Plan** — free plan harian otomatis + plan berbayar (kuota token, stok, promo) + mode **PAYG** (pay-as-you-go per request)
- **Top-up saldo IDR** — pembayaran via **Tripay** (VA, QRIS, e-wallet), saldo dikonversi ke USD memakai kurs realtime
- **Redeem code** — kode isi saldo / kuota
- **Program Referral** — undang teman lewat link `?ref=kode`; referrer dapat reward saldo flat saat teman melakukan top-up pertama (min. nominal)
- **Usage analytics** — grafik per hari, per model, per API key; filter status (sukses/error), export CSV
- **Leaderboard model** — ranking pemakaian antar pengguna
- **Model status & API health** — halaman status model realtime + health gateway
- **Support Center** — tiket bantuan dengan lampiran
- **Inbox** — notifikasi & pesan dari admin
- **Docs API** — panduan integrasi + konfigurasi untuk 9+ tools AI populer
- **Multi-bahasa ID/EN** — switcher bahasa (persisten via cookie) + tema gelap/terang

### Untuk Admin (`admin.azkia.cloud`)
- Kelola **provider** (upstream) & **model** (aktif/nonaktif, rate limit per menit, harga per 1M token, badge promo)
- Kelola **plan** (kuota, harga IDR, stok, reset harian, rate limit per menit)
- **Deposit** — rekonsiliasi otomatis order Tripay, manual credit (dengan verifikasi password admin), export
- **Redeem code** — generate batch, nonaktifkan per kode/batch
- **Billing monitoring** — telusuri billing event per pengguna
- **Request logs & rejections** — inspeksi setiap request, lihat request yang ditolak (alasan, kuota habis, rate limit)
- **Users** — detail akun, saldo, plan, top-up manual, ubah status (aktif/suspend)
- **Support tickets**, **dashboard popups** (banner/pengumuman), **payment settings** (Tripay)

---

## Arsitektur

```
Browser user ──▶ Nginx (80/443)
                    ├── azkia.cloud / www / admin  ──▶ Laravel (php8.3-fpm, unix socket)
                    └── api.azkia.cloud             ──▶ FastAPI gateway (127.0.0.1:8001)

FastAPI gateway ──▶ PostgreSQL (users, api_keys, usage_logs, plans, ...)
                ──▶ Redis (queue, cache, session)
                ──▶ Upstream AI providers (kunci dienkripsi dgn APP_KEY dashboard)

Laravel workers (systemd):
  azkia-queue            queue:work redis
  azkia-scheduler.timer  schedule:run (tiap menit → tripay:reconcile)
  azkia-exchange-rate.timer  refresh kurs USD→IDR (tiap 30 detik)
  azkia-billing-recovery.timer  pemulihan billing (tiap menit)
```

Alur request API:
1. Klien kirim `POST /v1/chat/completions` dengan `Authorization: Bearer azkia_xxx`
2. Gateway validasi API key → cek user aktif & kuota (plan aktif / saldo PAYG)
3. Cek **rate limit** per menit (dari plan & model)
4. Hitung estimasi biaya → potong kuota/saldo
5. Forward ke upstream provider → stream balik ke klien
6. Catat `usage_logs` (token, biaya, latensi, status) untuk dashboard

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| Dashboard | Laravel 13 (PHP ^8.3), Blade, Vite, Sanctum, PostgreSQL, Redis |
| Gateway | Python 3.10+, FastAPI, uvicorn/gunicorn, httpx, asyncpg, cryptography |
| Database | PostgreSQL |
| Cache / Queue / Session | Redis (phpredis) |
| Web server | Nginx + PHP-FPM 8.3 |
| Payment | Tripay (sandbox/production), kurs realtime open.er-api.com |
| Auth | Google OAuth (Socialite) + email/password (login lama) |
| Deploy | systemd units + timers, certbot SSL |

---

## Struktur Repo

```
azkia-router/
├── dashboard/            # Laravel app (user site + admin panel)
│   ├── app/              # Controllers, Models, Console Commands, Middleware
│   ├── routes/web.php    # Semua route user & admin
│   ├── lang/id,en/       # Terjemahan dashboard + landing
│   ├── resources/views/  # Blade views (user/, admin/, auth/, welcome)
│   └── tests/            # Feature tests
├── gateway/              # FastAPI OpenAI-compatible proxy
│   ├── app/main.py       # Endpoint API
│   ├── app/billing.py    # Billing & kuota engine
│   ├── app/usage.py      # Estimasi & normalisasi token
│   └── tests/            # pytest
├── infra/
│   ├── nginx/azkia-router.conf
│   └── systemd/          # Units + timers
├── scripts/
│   ├── bootstrap-fresh-vps.sh   # ⭐ Instalasi 1 perintah untuk VPS baru
│   ├── deploy-systemd.sh        # System deps + gateway + systemd/nginx
│   └── install-dashboard.sh     # PHP + composer + migrate
└── RUNBOOK.txt           # Catatan operasional asli
```

---

## Instalasi

### Opsi A — VPS baru (disarankan, 1 perintah)

Prasyarat: VPS **Ubuntu 22.04** dengan user `ubuntu` (default) dan akses `sudo`.

```bash
git clone https://github.com/Anseltsmm/azkia-router.git
cd azkia-router
sudo bash scripts/bootstrap-fresh-vps.sh
```

Skrip otomatis mengerjakan:
1. Install system deps: PHP 8.3 (ondrej PPA), Node 20 (NodeSource), Composer, Python 3, PostgreSQL, Redis, Nginx, Certbot
2. Buat role + database PostgreSQL `azkia_router` (password acak)
3. Setup gateway: venv + `pip install` + `.env`
4. Setup dashboard: `composer install`, `.env` production (pgsql + redis), `key:generate`, `migrate`, `npm install && npm run build`
5. Sinkronkan `APP_KEY` dashboard → `AZKIA_LARAVEL_APP_KEY` gateway
6. Pasang systemd units + nginx (path & user disesuaikan otomatis)
7. Aktifkan semua service & timer

Setelah skrip selesai (langkah manual):
1. **DNS** — arahkan `azkia.cloud`, `www.azkia.cloud`, `api.azkia.cloud`, `admin.azkia.cloud` ke IP VPS
2. **Kredensial** — isi `dashboard/.env`: `TRIPAY_API_KEY`, `TRIPAY_PRIVATE_KEY`, `TRIPAY_MERCHANT_CODE`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, lalu `php artisan config:clear`
3. **SSL** — `sudo certbot --nginx -d azkia.cloud -d www.azkia.cloud -d api.azkia.cloud -d admin.azkia.cloud`
4. **Admin** — tandai user sebagai `is_admin=true` di DB, atau via `tinker`, lalu login di `admin.azkia.cloud`
5. Tambahkan **provider & model** beserta pricing lewat panel admin

### Opsi B — Manual step-by-step

```bash
# 1. System deps
sudo apt-get update && sudo apt-get install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y php8.3-cli php8.3-fpm php8.3-pgsql php8.3-redis php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-tokenizer php8.3-dom \
  unzip curl git python3-venv python3-pip postgresql redis-server nginx certbot python3-certbot-nginx

# 2. PostgreSQL
sudo -u postgres psql -c "CREATE ROLE azkia_router LOGIN PASSWORD '<password>';"
sudo -u postgres createdb -O azkia_router azkia_router

# 3. Gateway
cd gateway
python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env   # lalu isi AZKIA_DATABASE_URL & AZKIA_ADMIN_BOOTSTRAP_KEY

# 4. Dashboard
cd ../dashboard
composer install
cp .env.example .env   # lalu sesuaikan DB_*, APP_URL, dst.
php artisan key:generate
php artisan migrate --force
php artisan storage:link
npm install && npm run build

# 5. Sinkronkan APP_KEY ke gateway (wajib!)
#    Salin nilai APP_KEY dari dashboard/.env ke AZKIA_LARAVEL_APP_KEY di gateway/.env

# 6. Service
sudo bash ../scripts/deploy-systemd.sh
sudo systemctl enable --now azkia-queue azkia-scheduler.timer azkia-exchange-rate.timer
```

---

## Konfigurasi

### `dashboard/.env` (variabel penting)

| Variabel | Deskripsi |
|---|---|
| `APP_ENV` | `production` (jangan `local`/`debug=true` di produksi) |
| `APP_KEY` | Kunci enkripsi Laravel — **wajib sama** dengan `AZKIA_LARAVEL_APP_KEY` gateway |
| `APP_URL` | `https://azkia.cloud` |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Koneksi PostgreSQL |
| `SESSION_DRIVER` / `QUEUE_CONNECTION` / `CACHE_STORE` | `redis` (produksi) |
| `EXCHANGE_RATE_API_URL` | Sumber kurs USD→IDR (default open.er-api.com) |
| `EXCHANGE_RATE_FALLBACK` | Kurs cadangan bila API mati (default `16000`) |
| `EXCHANGE_RATE_CACHE_TTL` | Cache kurs (detik, default 43200) |
| `GATEWAY_HEALTH_URL` / `GATEWAY_HEALTH_MODELS_URL` | Endpoint health gateway untuk halaman API Health |
| `GATEWAY_HEALTH_TOKEN` | Token header `X-Health-Token` (opsional) |
| `TRIPAY_MODE` | `sandbox` / `production` |
| `TRIPAY_API_KEY` / `TRIPAY_PRIVATE_KEY` / `TRIPAY_MERCHANT_CODE` | Kredensial Tripay |
| `TRIPAY_MINIMUM_TOPUP` | Minimum top-up IDR (default 10000) |
| `TRIPAY_EXPIRY_HOURS` | Masa berlaku order pembayaran (default 24) |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | Google OAuth — kosongkan untuk menyembunyikan tombol login Google |
| `APP_LOCALE` | Bahasa default (`id` / `en`) |

### `gateway/.env` (variabel penting)

| Variabel | Deskripsi |
|---|---|
| `AZKIA_API_HOST` / `AZKIA_API_PORT` | Bind gateway (default `127.0.0.1:8001`) |
| `AZKIA_DATABASE_URL` | `postgresql://azkia_router:<pass>@127.0.0.1:5432/azkia_router` |
| `AZKIA_REDIS_URL` | `redis://127.0.0.1:6379/0` |
| `AZKIA_LARAVEL_APP_KEY` | **Harus sama** dengan `APP_KEY` dashboard (dekripsi kunci provider) |
| `AZKIA_DEFAULT_UPSTREAM_BASE_URL` / `AZKIA_DEFAULT_UPSTREAM_API_KEY` | Fallback upstream bila model tanpa provider |
| `AZKIA_ADMIN_BOOTSTRAP_KEY` | Kunci bootstrap admin |

---

## Penggunaan API

### Informasi Dasar

| | |
|---|---|
| **Base URL** | `https://api.azkia.cloud/v1` |
| **Autentikasi** | `Authorization: Bearer azkia_xxxxx` |
| **Format** | OpenAI-compatible (JSON) |
| **Streaming** | Didukung (`"stream": true`) |

### Endpoint

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/v1/models` | Daftar model yang tersedia |
| `POST` | `/v1/chat/completions` | Chat completion |
| `POST` | `/v1/completions` | Text completion |
| `POST` | `/v1/embeddings` | Embeddings |
| `GET` | `/health` | Health gateway |
| `GET` | `/health/models` | Status & latensi tiap model |

### Contoh — cURL

```bash
curl https://api.azkia.cloud/v1/chat/completions \
  -H "Authorization: Bearer azkia_xxxxx" \
  -H "Content-Type: application/json" \
  -d '{"model":"deepseek/deepseek-v4-flash","messages":[{"role":"user","content":"Hello"}]}'
```

### Contoh — Python (OpenAI SDK)

```python
from openai import OpenAI

client = OpenAI(base_url="https://api.azkia.cloud/v1", api_key="azkia_xxxxx")
response = client.chat.completions.create(
    model="deepseek/deepseek-v4-flash",
    messages=[{"role": "user", "content": "Hello!"}],
)
print(response.choices[0].message.content)
```

### Contoh — Node.js (OpenAI SDK)

```js
import OpenAI from "openai";

const client = new OpenAI({
  baseURL: "https://api.azkia.cloud/v1",
  apiKey: "azkia_xxxxx",
});

const res = await client.chat.completions.create({
  model: "deepseek/deepseek-v4-flash",
  messages: [{ role: "user", content: "Hello!" }],
});
console.log(res.choices[0].message.content);
```

### Tools yang Kompatibel

OpenCode, Aider, Cline, Roo Code, Continue, Cherry Studio, Chatbox, Open WebUI, LibreChat — semuanya tinggal diarahkan ke `https://api.azkia.cloud/v1` + API key Azkia (panduan konfigurasi lengkap ada di halaman **Docs** dashboard).

---

## Sistem Plan, Kuota & Billing

### Kuota & Biaya
- **Free plan** — kuota token harian otomatis untuk setiap akun baru
- **Plan berbayar** — kuota token dengan batas waktu (ada plan dengan **reset harian**), harga IDR, stok terbatas, badge promo
- **PAYG** — mode per-request; biaya dihitung dari harga model (per 1M token input/output) dan dipotong dari saldo
- **Rate limit** — batas request per menit ditentukan per plan & per model

### Saldo & Top-up
- Top-up dalam **IDR** via Tripay (VA/QRIS/e-wallet)
- Saldo tersimpan dalam **USD** — konversi memakai kurs realtime (cache 12 jam, fallback 16000)
- **Redeem code** — isi saldo/kuota tanpa pembayaran online

### Siklus Billing Otomatis
| Service | Jadwal | Fungsi |
|---|---|---|
| `azkia-queue` | kontinu | Proses job antrian (billing, notifikasi) |
| `azkia-scheduler.timer` | tiap menit | `tripay:reconcile` — cocokkan status order Tripay |
| `azkia-exchange-rate.timer` | tiap 30 detik | Refresh kurs USD→IDR |
| `azkia-billing-recovery.timer` | tiap menit | Pemulihan/pengecekan billing yang gagal |

---

## Panel Admin

Akses: `https://admin.azkia.cloud` (login email/password — user dengan `is_admin=true`).

Fitur utama:
- **Providers** — kelola upstream & kunci API (tersimpan terenkripsi)
- **Models** — aktif/nonaktif, rate limit per menit, harga per 1M token (input/output, cache read/write), badge promo
- **Plans** — kuota, harga, stok, reset harian, rate limit per menit
- **Payment Gateway** — konfigurasi Tripay
- **Deposit** — rekonsiliasi order, manual credit, export
- **Redeem Codes** — generate & kelola batch
- **Billing Monitoring** — audit billing event
- **Request Logs / Rejections** — inspeksi request & alasan penolakan
- **Users** — kelola akun, saldo, status, kirim pesan inbox
- **Support** — kelola tiket pengguna
- **Dashboard Popups** — pengumuman/banner

> ⚠️ Tindakan sensitif (manual credit, generate redeem code) memerlukan **password admin saat ini** sebagai verifikasi.

---

## Deployment & Service

### systemd units

| Unit | Fungsi |
|---|---|
| `azkia-gateway.service` | gunicorn + uvicorn worker, bind `127.0.0.1:8001` |
| `azkia-queue.service` | `php artisan queue:work redis` |
| `azkia-scheduler.service` + `.timer` | `php artisan schedule:run` tiap menit |
| `azkia-exchange-rate.service` + `.timer` | refresh kurs tiap 30 detik |
| `azkia-billing-recovery.service` + `.timer` | recovery billing tiap menit |

### Perintah operasional

```bash
# Status
systemctl status azkia-gateway azkia-queue
systemctl list-timers | grep azkia

# Restart
sudo systemctl restart azkia-gateway

# Log
journalctl -u azkia-gateway -f
tail -f dashboard/storage/logs/laravel.log

# Rebuild frontend (setelah update asset)
cd dashboard && npm install && npm run build

# Clear cache setelah ubah .env
cd dashboard && php artisan config:clear && php artisan optimize:clear
```

### Update dari repo

```bash
cd /home/ubuntu/azkia-router
git pull
cd dashboard && composer install --no-dev && php artisan migrate --force && npm install && npm run build
sudo systemctl restart azkia-gateway azkia-queue
```

---

## Backup & Migrasi

### Backup database (PostgreSQL)

```bash
sudo -u postgres pg_dump -Fc azkia_router > azkia_router_$(date +%F).dump
```

### Restore ke server lain

```bash
sudo -u postgres pg_restore -d azkia_router --clean --if-exists azkia_router.dump
```

### ⚠️ Aturan emas saat migrasi
1. **APP_KEY tidak boleh berubah** — kunci API provider tersimpan **terenkripsi** dengan `APP_KEY` dashboard. Restore dump ke server baru **wajib** memakai `APP_KEY` lama (isi manual di `.env` sebelum `migrate`), lalu salin ke `AZKIA_LARAVEL_APP_KEY` gateway. Jika `APP_KEY` baru di-generate, semua kunci provider harus diinput ulang.
2. Salin juga `.env` kedua app (dashboard & gateway) atau isi ulang nilainya.
3. Backup sertifikat SSL bila perlu (`/etc/letsencrypt/`) — atau jalankan ulang certbot.

---

## Troubleshooting

| Gejala | Kemungkinan penyebab | Solusi |
|---|---|---|
| Gateway 503 di `/health` | Service mati / DB down | `systemctl status azkia-gateway`; cek log |
| Kunci provider tidak terbaca | `AZKIA_LARAVEL_APP_KEY` ≠ `APP_KEY` dashboard | Samakan kedua nilai |
| Saldo tidak bertambah setelah bayar | Order Tripay belum di-reconcile | Tunggu `azkia-scheduler.timer` (tiap menit) atau jalankan `php artisan tripay:reconcile` |
| Kurs tidak update | API kurs mati | Cek `EXCHANGE_RATE_FALLBACK`; log `azkia-exchange-rate` |
| Halaman dashboard tanpa CSS | Asset belum di-build | `npm install && npm run build` |
| `date_trunc` error di test | Test memakai SQLite, produksi PostgreSQL | Jalankan test dengan PostgreSQL, atau sesuaikan test |

---

## Keamanan

- **Jangan commit `.env`** — sudah di-ignore; hanya `.env.example` dengan placeholder yang di-commit
- Kunci API upstream & kredensial Tripay tersimpan **terenkripsi** di database
- Admin panel terpisah domain (`admin.azkia.cloud`) dengan verifikasi password untuk aksi sensitif
- Rate limiting pada endpoint login/purchase/redeem (throttle)
- Bahasa default & fallback dikelola via `APP_LOCALE` / `APP_FALLBACK_LOCALE`
- Update dependency secara berkala (`composer update`, `pip install -U -r requirements.txt`, `npm audit`)

---

## Lisensi

Proyek **private** — hak milik pemilik Azkia Router. Tidak untuk didistribusikan tanpa izin.
