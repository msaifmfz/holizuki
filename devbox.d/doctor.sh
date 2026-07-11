#!/usr/bin/env bash

set -Eeuo pipefail

source devbox.d/local-env.sh

check() {
    local description="$1"

    shift

    if "$@"; then
        printf '[ok] %s\n' "$description"

        return
    fi

    printf '[fail] %s\n' "$description" >&2

    return 1
}

expect_value() {
    local description="$1"
    local expected="$2"
    local actual="$3"

    if [[ "$actual" == "$expected" ]]; then
        printf '[ok] %s: %s\n' "$description" "$actual"

        return
    fi

    printf '[fail] %s: expected %s, got %s\n' \
        "$description" "$expected" "${actual:-<empty>}" >&2

    return 1
}

php artisan config:clear --no-interaction >/dev/null

check '.env exists' test -f .env
check 'APP_KEY is configured' grep -Eq '^APP_KEY=base64:.+' .env
check 'public storage is linked' test -L public/storage
check 'Laravel uses PostgreSQL' bash -o pipefail -c \
    "php artisan config:show database.default --no-interaction | grep -Eq 'pgsql[[:space:]]*$'"

expect_value 'PHP version' "${DOCTOR_EXPECTED_PHP:?}" "$(php -r 'echo PHP_VERSION;')"
# shellcheck disable=SC2016
check 'required PHP extensions are loaded' php -r \
    '$required = ["curl", "dom", "fileinfo", "intl", "mbstring", "pcntl", "pdo_pgsql", "pdo_sqlite", "sockets", "sodium", "xml", "xmlwriter", "xdebug", "zip"]; $missing = array_values(array_filter($required, static fn (string $extension): bool => ! extension_loaded($extension))); if ($missing !== []) { fwrite(STDERR, "Missing PHP extensions: ".implode(", ", $missing).PHP_EOL); exit(1); }'
expect_value 'Composer version' "${DOCTOR_EXPECTED_COMPOSER:?}" \
    "$(composer --version --no-ansi 2>/dev/null | awk 'NR == 1 { print $3 }')"
expect_value 'Node.js version' "${DOCTOR_EXPECTED_NODE:?}" "$(node --version)"
expect_value 'npm version' "${DOCTOR_EXPECTED_NPM:?}" "$(npm --version)"
expect_value 'Node.js timezone' 'UTC' \
    "$(node -e 'process.stdout.write(Intl.DateTimeFormat().resolvedOptions().timeZone)')"
expect_value 'PostgreSQL client version' "${DOCTOR_EXPECTED_POSTGRES:?}" \
    "$(psql --version | awk '{ print $3 }')"

check 'PostgreSQL accepts local connections' pg_isready \
    --host=127.0.0.1 \
    --port="$PGPORT" \
    --username=holizuki \
    --dbname=holizuki \
    --quiet
expect_value 'PostgreSQL timezone' 'UTC' \
    "$(psql --host=127.0.0.1 --port="$PGPORT" --username=holizuki --dbname=holizuki --tuples-only --no-align --command='SHOW timezone')"
check 'database has no pending migrations' bash -o pipefail -c \
    "php artisan migrate:status --pending --no-interaction --no-ansi | grep -Fq 'No pending migrations.'"

php artisan about --only=environment,drivers
