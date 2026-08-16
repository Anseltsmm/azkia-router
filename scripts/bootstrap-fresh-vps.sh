#!/usr/bin/env bash
# ============================================================================
# Azkia Router — bootstrap SATU PERINTAH untuk VPS baru (Ubuntu 22.04)
#
# Cara pakai:
#   git clone https://github.com/Anseltsmm/azkia-router.git
#   cd azkia-router
#   sudo bash scripts/bootstrap-fresh-vps.sh
#
# Yang dikerjakan otomatis:
#   1. System deps: PHP 8.3 (ondrej PPA), Node 20 (NodeSource), Composer,
#      Python 3, PostgreSQL, Redis, Nginx, Certbot
#   2. PostgreSQL: buat role + database azkia_router (password acak)
#   3. Gateway: venv + pip install + .env dari example (DB URL + admin key)
#   4. Dashboard: composer install + .env production + key:generate +
#      migrate + storage:link + npm install && npm run build
#   5. Sinkronisasi APP_KEY dashboard -> AZKIA_LARAVEL_APP_KEY gateway
#   6. Systemd units + nginx conf (path & user disesuaikan ke VPS ini)
#   7. Aktifkan semua service & timer
# ============================================================================
set -euo pipefail

# ---------- Lokasi & user ----------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

if [ "$(id -u)" -ne 0 ]; then
    echo "Jalankan dengan sudo: sudo bash scripts/bootstrap-fresh-vps.sh" >&2
    exit 1
fi
RUN_USER="${SUDO_USER:-ubuntu}"
if [ "$RUN_USER" = "root" ]; then
    RUN_USER="ubuntu"
    echo "! Dijalankan sebagai root — service akan memakai user 'ubuntu'. Pastikan user 'ubuntu' ada." >&2
fi

export DEBIAN_FRONTEND=noninteractive

# ---------- Helper: set key di file .env (ganti jika ada, tambah jika belum) ----------
set_env() {
    local file="$1" key="$2" value="$3"
    if grep -q "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

echo "==> [1/7] Install dependency sistem (PHP 8.3, Node 20, PostgreSQL, Redis, Nginx)..."

# PHP 8.3 via ondrej PPA (Laravel butuh ^8.3; Ubuntu 22.04 default cuma 8.1)
if ! command -v php >/dev/null 2>&1 || ! php -r 'exit(version_compare(PHP_VERSION, "8.3", ">=") ? 0 : 1);' 2>/dev/null; then
    apt-get update -qq
    apt-get install -y -qq software-properties-common
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
    apt-get update -qq
fi

# Node 20 via NodeSource (butuh >=18 untuk Vite)
if ! command -v node >/dev/null 2>&1 || [ "$(node -v 2>/dev/null | tr -d 'v' | cut -d. -f1)" -lt 18 ]; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null 2>&1
fi

apt-get install -y -qq \
    php8.3-cli php8.3-fpm php8.3-pgsql php8.3-redis php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-bcmath php8.3-tokenizer php8.3-dom \
    unzip curl git openssl \
    python3-venv python3-pip postgresql redis-server nginx \
    certbot python3-certbot-nginx

# Composer (verifikasi signature)
if ! command -v composer >/dev/null 2>&1; then
    EXPECTED_SIGNATURE="$(curl -fsSL https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    test "$EXPECTED_SIGNATURE" = "$ACTUAL_SIGNATURE"
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer >/dev/null
    rm -f /tmp/composer-setup.php
fi

systemctl enable --now postgresql redis-server php8.3-fpm >/dev/null 2>&1 || true

# ---------- PostgreSQL: role + database ----------
echo "==> [2/7] Siapkan PostgreSQL (role & database azkia_router)..."
PG_USER="azkia_router"
PG_DB="azkia_router"
PG_PASS="$(openssl rand -hex 16)"

if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${PG_USER}'" | grep -q 1; then
    sudo -u postgres psql -q -c "CREATE ROLE ${PG_USER} LOGIN PASSWORD '${PG_PASS}'"
else
    echo "   ! Role ${PG_USER} sudah ada — password tidak diubah."
fi
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${PG_DB}'" | grep -q 1; then
    sudo -u postgres createdb -O "${PG_USER}" "${PG_DB}"
fi

# ---------- Gateway (FastAPI) ----------
echo "==> [3/7] Setup gateway (venv + dependency + .env)..."
cd "$PROJECT_DIR/gateway"
python3 -m venv .venv
# shellcheck disable=SC1091
. .venv/bin/activate
pip install --upgrade pip -q
pip install -r requirements.txt -q

[ -f .env ] || cp .env.example .env
ADMIN_KEY="$(openssl rand -hex 24)"
set_env .env AZKIA_ENV "production"
set_env .env AZKIA_DATABASE_URL "postgresql://${PG_USER}:${PG_PASS}@127.0.0.1:5432/${PG_DB}"
set_env .env AZKIA_ADMIN_BOOTSTRAP_KEY "$ADMIN_KEY"

# ---------- Dashboard (Laravel) ----------
echo "==> [4/7] Setup dashboard (composer + .env + migrate + npm build)..."
cd "$PROJECT_DIR/dashboard"
composer install --no-interaction --prefer-dist --no-progress

[ -f .env ] || cp .env.example .env
set_env .env APP_ENV "production"
set_env .env APP_URL "https://azkia.cloud"
set_env .env DB_CONNECTION "pgsql"
set_env .env DB_HOST "127.0.0.1"
set_env .env DB_PORT "5432"
set_env .env DB_DATABASE "$PG_DB"
set_env .env DB_USERNAME "$PG_USER"
set_env .env DB_PASSWORD "$PG_PASS"
set_env .env SESSION_DRIVER "redis"
set_env .env QUEUE_CONNECTION "redis"
set_env .env CACHE_STORE "redis"

if ! grep -Eq '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider" >/dev/null 2>&1 || true
php artisan migrate --force
php artisan storage:link >/dev/null 2>&1 || true

npm install --no-progress 2>&1 | tail -1
npm run build 2>&1 | tail -2

# ---------- Sinkronisasi APP_KEY ke gateway ----------
echo "==> [5/7] Sinkronisasi APP_KEY dashboard -> gateway..."
APP_KEY="$(grep -E '^APP_KEY=' "$PROJECT_DIR/dashboard/.env" | cut -d= -f2-)"
set_env "$PROJECT_DIR/gateway/.env" AZKIA_LARAVEL_APP_KEY "$APP_KEY"

# ---------- Systemd units + nginx ----------
echo "==> [6/7] Pasang systemd units + nginx conf (path & user disesuaikan)..."
for svc in azkia-gateway azkia-queue azkia-scheduler azkia-exchange-rate azkia-billing-recovery; do
    cp "$PROJECT_DIR/infra/systemd/${svc}.service" "/etc/systemd/system/${svc}.service"
done
cp "$PROJECT_DIR/infra/systemd/azkia-scheduler.timer" /etc/systemd/system/
cp "$PROJECT_DIR/infra/systemd/azkia-exchange-rate.timer" /etc/systemd/system/
cp "$PROJECT_DIR/infra/systemd/azkia-billing-recovery.timer" /etc/systemd/system/
sed -i "s|/home/ubuntu/azkia-router|${PROJECT_DIR}|g; s|^User=.*|User=${RUN_USER}|" /etc/systemd/system/azkia-*.service

cp "$PROJECT_DIR/infra/nginx/azkia-router.conf" /etc/nginx/sites-available/azkia-router.conf
sed -i "s|/home/ubuntu/azkia-router|${PROJECT_DIR}|g" /etc/nginx/sites-available/azkia-router.conf
ln -sf /etc/nginx/sites-available/azkia-router.conf /etc/nginx/sites-enabled/azkia-router.conf

chown -R "$RUN_USER":"$RUN_USER" "$PROJECT_DIR"

systemctl daemon-reload
systemctl enable --now azkia-gateway azkia-queue \
    azkia-scheduler.timer azkia-exchange-rate.timer azkia-billing-recovery.timer \
    nginx >/dev/null 2>&1 || true
nginx -t
systemctl reload nginx

# ---------- Ringkasan ----------
echo "==> [7/7] Selesai!"
echo "======================================================================"
echo "  ✅ Azkia Router siap digunakan di VPS ini"
echo "     Project : ${PROJECT_DIR}"
echo "     Database: postgresql://${PG_USER}@127.0.0.1:5432/${PG_DB}"
echo "     APP_KEY : ${APP_KEY}"
echo ""
echo "  Langkah berikutnya:"
echo "  1. Pastikan DNS mengarah ke IP VPS ini:"
echo "     azkia.cloud  www.azkia.cloud  api.azkia.cloud  admin.azkia.cloud"
echo "  2. Isi kredensial di ${PROJECT_DIR}/dashboard/.env:"
echo "     TRIPAY_API_KEY / TRIPAY_PRIVATE_KEY / TRIPAY_MERCHANT_CODE"
echo "     GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET"
echo "     lalu: cd ${PROJECT_DIR}/dashboard && php artisan config:clear"
echo "  3. Pasang SSL (setelah DNS mengarah):"
echo "     sudo certbot --nginx -d azkia.cloud -d www.azkia.cloud -d api.azkia.cloud -d admin.azkia.cloud"
echo "  4. Tambahkan kunci provider & model lewat panel admin"
echo "     (admin: https://admin.azkia.cloud — pakai akun is_admin)"
echo "======================================================================"
