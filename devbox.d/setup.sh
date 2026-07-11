#!/usr/bin/env bash

set -Eeuo pipefail

source devbox.d/local-env.sh

test -f .env || cp .env.example .env
composer install --no-interaction --no-progress --prefer-dist
php artisan config:clear --no-interaction
php artisan config:show database.default --no-interaction | grep -Eq 'pgsql[[:space:]]*$'
npm ci

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --no-interaction
fi

if [[ ! -s "$PGDATA/PG_VERSION" ]]; then
    initdb \
        --username=holizuki \
        --auth-local=trust \
        --auth-host=trust \
        --encoding=UTF8 \
        --no-locale \
        --set=timezone=UTC
fi

if ! pg_isready --host=127.0.0.1 --port="$PGPORT" --username=holizuki --quiet; then
    devbox services start postgresql
fi

for ((attempt = 1; attempt <= 30; attempt++)); do
    if pg_isready --host=127.0.0.1 --port="$PGPORT" --username=holizuki --quiet; then
        break
    fi

    sleep 1
done

pg_isready --host=127.0.0.1 --port="$PGPORT" --username=holizuki --quiet

if ! psql \
    --host=127.0.0.1 \
    --port="$PGPORT" \
    --username=holizuki \
    --dbname=postgres \
    --tuples-only \
    --no-align \
    --command="SELECT 1 FROM pg_database WHERE datname = 'holizuki'" \
    | grep -qx 1; then
    createdb \
        --host=127.0.0.1 \
        --port="$PGPORT" \
        --username=holizuki \
        holizuki
fi

php artisan migrate --force --no-interaction

if [[ ! -L public/storage ]]; then
    php artisan storage:link --no-interaction
fi

npm run prepare
npm run build
