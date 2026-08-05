#!/usr/bin/env sh

set -eu

echo "Starting CheofPizza scheduler..."

sh docker/prepare-runtime.sh

echo "Application timezone: ${APP_TIMEZONE:-America/Guayaquil}"

exec php artisan schedule:work \
    --no-interaction
