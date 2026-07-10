#!/usr/bin/env bash

set -Eeuo pipefail

script_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repository_root="$(CDPATH='' cd -- "$script_dir/../.." && pwd)"

# shellcheck disable=SC1091
source "$repository_root/deploy/runtime-images.env"
# shellcheck disable=SC1091
source "$repository_root/deploy/platform/versions.env"

assert_digest_reference() {
    local reference="$1"

    if [[ ! "$reference" =~ ^[^[:space:]@]+@sha256:[a-f0-9]{64}$ ]]; then
        printf 'Expected a digest-pinned image, got %s.\n' "$reference" >&2
        exit 1
    fi
}

assert_digest_reference "$PHP_BASE_IMAGE_PRODUCTION"
assert_digest_reference "$PHP_BASE_IMAGE_STAGING"
assert_digest_reference "$NODE_BASE_IMAGE"
assert_digest_reference "$COMPOSER_BASE_IMAGE"
assert_digest_reference "$POSTGRES_IMAGE"

[[ "$PHP_BASE_IMAGE_PRODUCTION" =~ php([0-9]+\.[0-9]+) ]]
production_php_version="${BASH_REMATCH[1]}"

grep -Fq "ARG COMPOSER_BASE_IMAGE=\"$COMPOSER_BASE_IMAGE\"" "$repository_root/Dockerfile"
grep -Fq "ARG NODE_BASE_IMAGE=\"$NODE_BASE_IMAGE\"" "$repository_root/Dockerfile"
grep -Fq "ARG PHP_BASE_IMAGE=\"$PHP_BASE_IMAGE_PRODUCTION\"" "$repository_root/Dockerfile"
grep -Fq "  phpVersion: \"$production_php_version\"" "$repository_root/deploy/helm/holizuki/values.yaml"
grep -Fq "  image: $POSTGRES_IMAGE" "$repository_root/deploy/helm/platform/values.yaml"
grep -Fq "node-name: holizuki-01" "$repository_root/deploy/server/k3s-config.yaml"

printf 'Deployment configuration consistency tests passed.\n'
