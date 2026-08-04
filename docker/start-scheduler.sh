#!/bin/sh

set -eu

echo "Starting CheofPizza scheduler..."
echo "Application timezone: ${APP_TIMEZONE:-America/Guayaquil}"

exec php artisan schedule:work \
    --no-interaction
