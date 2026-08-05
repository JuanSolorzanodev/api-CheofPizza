#!/usr/bin/env sh

set -eu

echo "Starting CheofPizza queue worker..."

sh docker/prepare-runtime.sh

echo "Queue connection: ${QUEUE_CONNECTION:-database}"
echo "Queues: ${QUEUE_NAMES:-broadcasts,paypal-webhooks,default}"
echo "Tries: ${QUEUE_TRIES:-3}"
echo "Timeout: ${QUEUE_WORKER_TIMEOUT:-120}"
echo "Backoff: ${QUEUE_BACKOFF:-5}"

exec php artisan queue:work "${QUEUE_CONNECTION:-database}" \
    --queue="${QUEUE_NAMES:-broadcasts,paypal-webhooks,default}" \
    --tries="${QUEUE_TRIES:-3}" \
    --sleep="${QUEUE_SLEEP:-1}" \
    --timeout="${QUEUE_WORKER_TIMEOUT:-120}" \
    --backoff="${QUEUE_BACKOFF:-5}" \
    --max-time="${QUEUE_MAX_TIME:-3600}" \
    --no-interaction
