#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="/home/ubuntu/azkia-router"
DASHBOARD_DIR="$PROJECT_DIR/dashboard"

if ! command -v php >/dev/null 2>&1; then
  sudo apt-get update
  sudo apt-get install -y php-cli php-fpm php-pgsql php-redis php-mbstring php-xml php-curl php-zip php-bcmath php-tokenizer php-dom unzip curl
fi

if ! command -v composer >/dev/null 2>&1; then
  EXPECTED_SIGNATURE="$(curl -fsSL https://composer.github.io/installer.sig)"
  php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
  ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
  test "$EXPECTED_SIGNATURE" = "$ACTUAL_SIGNATURE"
  sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm /tmp/composer-setup.php
fi

if [ ! -f "$DASHBOARD_DIR/artisan" ]; then
  printf 'Dashboard Laravel tidak ditemukan di %s\n' "$DASHBOARD_DIR" >&2
  exit 1
fi

cd "$DASHBOARD_DIR"
composer install
if [ ! -f .env ]; then
  cp .env.example .env
fi
if ! grep -Eq '^APP_KEY=.+$' .env; then
  php artisan key:generate
fi
php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider" || true
php artisan migrate
