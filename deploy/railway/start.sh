#!/usr/bin/env bash
# Container entrypoint: wire the volume, migrate, then run web + queue + scheduler.
set -euo pipefail
cd /app

DATA="${DATA_DIR:-/data}"
mkdir -p "$DATA/app/public" "$DATA/app/private" "$DATA/app/backups" "$DATA/logs"

# Uploads and logs survive redeploys only if they live on the volume.
rm -rf storage/app storage/logs
ln -s "$DATA/app" storage/app
ln -s "$DATA/logs" storage/logs
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    export DB_DATABASE="${DB_DATABASE:-$DATA/database.sqlite}"
    touch "$DB_DATABASE"
fi

php artisan storage:link --force
php artisan migrate --force
php artisan kabelota:seed-if-empty
php artisan optimize

# Background workers restart themselves if they exit (queue:work stops every hour by design).
( while true; do php artisan queue:work --sleep=3 --tries=3 --max-time=3600 || true; sleep 2; done ) &
( while true; do php artisan schedule:work || true; sleep 5; done ) &

exec frankenphp php-server --root public --listen ":${PORT:-8080}"
