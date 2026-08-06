#!/usr/bin/env sh

set -eu

echo "Starting CheofPizza API with FrankenPHP..."

sh docker/prepare-runtime.sh

echo "HTTP port: ${PORT:-8080}"
echo "Healthcheck: /health"

exec frankenphp run \
    --config /etc/frankenphp/Caddyfile \
    --adapter caddyfile
