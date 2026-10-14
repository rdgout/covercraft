#!/bin/sh
set -eu

APP_STORAGE_PATH="${APP_STORAGE_PATH:-/app/storage}"
APP_KEY_FILE="${APP_KEY_FILE:-$APP_STORAGE_PATH/app/app_key}"

log() {
    echo "[entrypoint] $*"
}

if [ "${1:-}" = "init-storage" ]; then
    log "Preparing shared storage for www-data..."
    mkdir -p "$APP_STORAGE_PATH/app/private/coverage" \
        "$APP_STORAGE_PATH/app/public" \
        "$APP_STORAGE_PATH/framework/cache/data" \
        "$APP_STORAGE_PATH/framework/sessions" \
        "$APP_STORAGE_PATH/framework/views" \
        "$APP_STORAGE_PATH/logs"
    chown -R www-data:www-data "$APP_STORAGE_PATH"
    exit 0
fi

if [ -z "${APP_KEY:-}" ]; then
    if [ -f "$APP_KEY_FILE" ]; then
        APP_KEY="$(cat "$APP_KEY_FILE")"
        log "Loaded the persisted application key."
    elif [ "${1:-}" = "frankenphp" ]; then
        APP_KEY="$(php artisan key:generate --show --no-interaction)"
        mkdir -p "$(dirname "$APP_KEY_FILE")"
        (umask 077 && printf '%s' "$APP_KEY" > "$APP_KEY_FILE")
        log "Generated and persisted the application key."
    else
        log "APP_KEY is missing. Start the app container first or provide APP_KEY."
        exit 1
    fi
    export APP_KEY
fi

log "Caching configuration and routes..."
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction

if [ "${1:-}" = "frankenphp" ] && [ "${RUN_MIGRATIONS:-true}" != "false" ]; then
    log "Running database migrations..."
    php artisan migrate --force --no-interaction
fi

if command -v docker-entrypoint.sh >/dev/null 2>&1; then
    exec docker-entrypoint.sh "$@"
fi

exec "$@"
