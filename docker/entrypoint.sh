#!/usr/bin/env bash

set -Eeuo pipefail

role="${1:-web}"

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    /tmp/caddy/config \
    /tmp/caddy/data

case "$role" in
    web)
        php artisan optimize
        exec frankenphp php-server --listen :8080 --root public/
        ;;
    worker)
        php artisan optimize
        exec php artisan queue:work \
            --backoff=3 \
            --max-time=3600 \
            --sleep=3 \
            --timeout=90 \
            --tries=3
        ;;
    scheduler)
        exec php artisan schedule:run --no-interaction
        ;;
    migrate)
        exec php artisan migrate --force --no-interaction
        ;;
    *)
        exec "$@"
        ;;
esac
