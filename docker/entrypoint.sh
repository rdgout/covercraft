#!/bin/sh

set -e

# Only run migrations and cache warming for the main app process
if echo "$1" | grep -q "frankenphp"; then
    echo "[entrypoint] Running migrations..."
    php artisan migrate --force --no-interaction

    echo "[entrypoint] Linking storage..."
    php artisan storage:link --force

    echo "[entrypoint] Caching config, routes, views, and events..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
