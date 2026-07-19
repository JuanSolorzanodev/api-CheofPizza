#!/bin/sh

set -eu

echo "Starting CheofPizza Reverb server..."
echo "Host: ${REVERB_SERVER_HOST:-0.0.0.0}"
echo "Port: ${REVERB_SERVER_PORT:-8080}"

exec php artisan reverb:start \
    --host="${REVERB_SERVER_HOST:-0.0.0.0}" \
    --port="${REVERB_SERVER_PORT:-8080}" \
    --no-interaction
