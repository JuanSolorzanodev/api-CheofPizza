#!/usr/bin/env sh

set -e

echo "Starting CheofPizza Reverb server..."

php artisan config:clear
php artisan config:cache

exec php artisan reverb:start \
    --host=0.0.0.0 \
    --port="${PORT:-8080}"
