#!/usr/bin/env bash

set -Eeuo pipefail

image="${1:-holizuki:ci}"
container_id=''

cleanup() {
    if [[ -n "$container_id" ]]; then
        docker rm --force "$container_id" >/dev/null 2>&1 || true
    fi
}

trap cleanup EXIT

[[ "$image" =~ ^[A-Za-z0-9._/:@-]+$ ]]
[[ "$(docker inspect --format '{{.Config.User}}' "$image")" == '10001:10001' ]]

docker run --rm --entrypoint php "$image" -r \
    'exit(extension_loaded("pdo_pgsql") && extension_loaded("intl") && extension_loaded("pcntl") && extension_loaded("zip") ? 0 : 1);'
docker run --rm --entrypoint php "$image" artisan about --only=environment >/dev/null
docker run --rm --entrypoint /bin/sh "$image" -c \
    'test ! -e /app/.env && test ! -d /app/tests && ! command -v composer && ! command -v node'

container_id="$(docker run --detach --read-only \
    --publish 127.0.0.1::8080 \
    --tmpfs /app/bootstrap/cache:uid=10001,gid=10001,mode=0770 \
    --tmpfs /app/storage/app:uid=10001,gid=10001,mode=0770 \
    --tmpfs /app/storage/framework:uid=10001,gid=10001,mode=0770 \
    --tmpfs /tmp:uid=10001,gid=10001,mode=1777 \
    --env APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    --env APP_RELEASE=v0.0.0-container-test \
    --env APP_URL=http://holizuki.test \
    --env CACHE_STORE=array \
    --env DB_CONNECTION=sqlite \
    --env DB_DATABASE=:memory: \
    --env QUEUE_CONNECTION=sync \
    --env SESSION_DRIVER=array \
    --env TRUSTED_HOSTS=holizuki.test \
    "$image")"

host_port="$(docker port "$container_id" 8080/tcp | head -n 1 | awk -F: '{print $NF}')"
[[ "$host_port" =~ ^[0-9]+$ ]]

for _ in {1..30}; do
    if curl --fail --silent --header 'Host: holizuki.test' "http://127.0.0.1:$host_port/up" >/dev/null 2>&1; then
        break
    fi

    sleep 1
done

curl --fail --silent --show-error --header 'Host: holizuki.test' "http://127.0.0.1:$host_port/up" >/dev/null
readiness_headers="$(curl \
    --dump-header - \
    --header 'Host: holizuki.test' \
    --output /dev/null \
    --silent \
    --show-error \
    "http://127.0.0.1:$host_port/ready" | tr -d '\r')"

grep -Fqx 'HTTP/1.1 204 No Content' <<<"$readiness_headers"
grep -Fq 'X-Holizuki-Release: v0.0.0-container-test' <<<"$readiness_headers"

for _ in {1..20}; do
    if [[ "$(docker inspect --format '{{.State.Health.Status}}' "$container_id")" == healthy ]]; then
        break
    fi

    sleep 1
done

[[ "$(docker inspect --format '{{.State.Health.Status}}' "$container_id")" == healthy ]]

printf 'Container runtime contract tests passed.\n'
