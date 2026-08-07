#!/usr/bin/env sh

set -eu

echo "Preparing CheofPizza runtime..."

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
    storage/app/firebase \
    bootstrap/cache

if [ -n "${FIREBASE_CREDENTIALS_BASE64:-}" ]; then
    FIREBASE_CREDENTIALS_PATH="/var/www/html/storage/app/firebase/firebase-service-account.json"

    printf '%s' "${FIREBASE_CREDENTIALS_BASE64}" \
        | base64 -d \
        > "${FIREBASE_CREDENTIALS_PATH}"

    chmod 600 "${FIREBASE_CREDENTIALS_PATH}"

    export FIREBASE_CREDENTIALS="${FIREBASE_CREDENTIALS_PATH}"

    echo "Firebase credentials prepared successfully."
fi

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear || true

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

echo "Runtime configuration prepared successfully."
