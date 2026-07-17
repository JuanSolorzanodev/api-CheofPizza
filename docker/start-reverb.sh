#!/usr/bin/env sh

set -eu

echo "Starting CheofPizza Reverb server..."

if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY is not configured."
    exit 1
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan config:clear
php artisan event:clear || true

php artisan config:cache
php artisan event:cache || true

exec php artisan reverb:start \
    --host=0.0.0.0 \
    --port="${PORT:-8080}" \
    --no-interaction
