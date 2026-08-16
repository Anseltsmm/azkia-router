#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="/home/ubuntu/azkia-router"
GATEWAY_DIR="$PROJECT_DIR/gateway"

sudo apt-get update
sudo apt-get install -y python3-venv python3-pip postgresql redis-server nginx certbot python3-certbot-nginx

cd "$GATEWAY_DIR"
python3 -m venv .venv
. .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt

if [ ! -f "$GATEWAY_DIR/.env" ]; then
  cp "$GATEWAY_DIR/.env.example" "$GATEWAY_DIR/.env"
fi

sudo cp "$PROJECT_DIR/infra/systemd/azkia-gateway.service" /etc/systemd/system/azkia-gateway.service
sudo cp "$PROJECT_DIR/infra/systemd/azkia-queue.service" /etc/systemd/system/azkia-queue.service
sudo cp "$PROJECT_DIR/infra/systemd/azkia-scheduler.service" /etc/systemd/system/azkia-scheduler.service
sudo cp "$PROJECT_DIR/infra/systemd/azkia-scheduler.timer" /etc/systemd/system/azkia-scheduler.timer
sudo cp "$PROJECT_DIR/infra/systemd/azkia-exchange-rate.service" /etc/systemd/system/azkia-exchange-rate.service
sudo cp "$PROJECT_DIR/infra/systemd/azkia-exchange-rate.timer" /etc/systemd/system/azkia-exchange-rate.timer
sudo cp "$PROJECT_DIR/infra/systemd/azkia-billing-recovery.service" /etc/systemd/system/azkia-billing-recovery.service
sudo cp "$PROJECT_DIR/infra/systemd/azkia-billing-recovery.timer" /etc/systemd/system/azkia-billing-recovery.timer
sudo cp "$PROJECT_DIR/infra/nginx/azkia-router.conf" /etc/nginx/sites-available/azkia-router.conf
sudo ln -sf /etc/nginx/sites-available/azkia-router.conf /etc/nginx/sites-enabled/azkia-router.conf

sudo systemctl daemon-reload
sudo systemctl enable --now redis-server nginx azkia-gateway azkia-scheduler.timer azkia-billing-recovery.timer
sudo nginx -t
sudo systemctl reload nginx
