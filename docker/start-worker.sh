#!/usr/bin/env sh

set -e

echo "Starting CheofPizza queue worker..."

php artisan config:clear
php artisan config:cache

exec php artisan queue:work database \
    --queue=broadcasts,paypal-webhooks,default \
    --tries=3 \
    --sleep=1 \
    --timeout=120 \
    --backoff=5 \
    --max-time=3600
