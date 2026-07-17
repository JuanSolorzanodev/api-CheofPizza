#!/usr/bin/env sh

set -e

echo "Starting CheofPizza API..."

php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve \
    --host=0.0.0.0 \
    --port="${PORT:-8080}"
