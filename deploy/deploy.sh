#!/usr/bin/env bash
# Ambil versi terbaru dari branch situs, lalu build dan migrasi.
#   bash deploy.sh demo          update situs demo (branch demo)
#   bash deploy.sh qa            update situs QA (branch qa)
#   bash deploy.sh qa --fresh    pertama kali / reset: hapus semua data, isi data contoh
set -euo pipefail
umask 002

site="${1:?Pakai: deploy.sh demo|qa [--fresh]}"
fresh="${2:-}"
dir="/var/www/kabelota-$site"
cd "$dir"

echo "==> Kode terbaru dari branch $site"
git fetch origin "$site"
git reset --hard "origin/$site"

echo "==> Dependensi dan aset"
composer install --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build
rm -f public/hot

grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
[ -f database/database.sqlite ] || { grep -q '^DB_CONNECTION=sqlite' .env && touch database/database.sqlite; } || true

php artisan down --retry=15 || true
if [ "$fresh" = "--fresh" ]; then
    php artisan kabelota:reset-demo --force
else
    php artisan migrate --force
fi
php artisan storage:link 2>/dev/null || true
php artisan optimize
php artisan filament:optimize
php artisan queue:restart
php artisan up

# PHP-FPM dan queue berjalan sebagai www-data.
sudo chgrp -R www-data storage bootstrap/cache database
sudo chmod -R ug+rwX storage bootstrap/cache database

echo "Selesai: $(grep '^APP_URL=' .env | cut -d= -f2)  ($(git log -1 --format='%h %s'))"
