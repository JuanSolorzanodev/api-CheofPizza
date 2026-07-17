#!/usr/bin/env sh

set -eu

echo "Starting CheofPizza API..."

if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY is not configured."
    exit 1
fi

if [ "${APP_ENV:-production}" = "production" ] && [ "${APP_DEBUG:-false}" = "true" ]; then
    echo "ERROR: APP_DEBUG must be false in production."
    exit 1
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear || true

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

echo "Configuration cached successfully."

exec php artisan serve \
    --host=0.0.0.0 \
    --port="${PORT:-8080}"
