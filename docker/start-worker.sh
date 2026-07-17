#!/usr/bin/env sh

set -eu

echo "Starting CheofPizza queue worker..."

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

QUEUE_CONNECTION_NAME="${QUEUE_CONNECTION:-database}"
QUEUE_NAMES="${QUEUE_NAMES:-broadcasts,paypal-webhooks,default}"
QUEUE_TRIES="${QUEUE_TRIES:-3}"
QUEUE_SLEEP="${QUEUE_SLEEP:-1}"
QUEUE_TIMEOUT="${QUEUE_WORKER_TIMEOUT:-120}"
QUEUE_BACKOFF="${QUEUE_BACKOFF:-5}"
QUEUE_MAX_TIME="${QUEUE_MAX_TIME:-3600}"

echo "Queue connection: ${QUEUE_CONNECTION_NAME}"
echo "Queues: ${QUEUE_NAMES}"

exec php artisan queue:work "${QUEUE_CONNECTION_NAME}" \
    --queue="${QUEUE_NAMES}" \
    --tries="${QUEUE_TRIES}" \
    --sleep="${QUEUE_SLEEP}" \
    --timeout="${QUEUE_TIMEOUT}" \
    --backoff="${QUEUE_BACKOFF}" \
    --max-time="${QUEUE_MAX_TIME}" \
    --no-interaction


