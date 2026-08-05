#!/usr/bin/env sh

set -eu

echo "Starting CheofPizza API..."

sh docker/prepare-runtime.sh

exec php artisan serve \
    --host=0.0.0.0 \
    --port="${PORT:-8080}"
