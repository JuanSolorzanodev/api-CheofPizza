#!/bin/sh

set -eu

echo "Starting CheofPizza queue worker..."
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
    --no-interaction
