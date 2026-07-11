#!/usr/bin/env bash

set -Eeuo pipefail

source devbox.d/local-env.sh

php artisan config:clear --no-interaction

if ! pg_isready --host=127.0.0.1 --port="$PGPORT" --username=holizuki --quiet; then
    devbox services start postgresql
fi

if ! pg_isready \
    --host=127.0.0.1 \
    --port="$PGPORT" \
    --username=holizuki \
    --dbname=holizuki \
    --quiet; then
    echo 'PostgreSQL is not initialized; run: devbox run setup' >&2
    exit 1
fi

composer dev
