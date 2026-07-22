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
[[ "$KUBECONFORM_CHECKSUM" =~ ^[a-f0-9]{64}$ ]]

[[ "$PHP_BASE_IMAGE_PRODUCTION" =~ php([0-9]+\.[0-9]+) ]]
production_php_version="${BASH_REMATCH[1]}"

grep -Fq "ARG COMPOSER_BASE_IMAGE=\"$COMPOSER_BASE_IMAGE\"" "$repository_root/Dockerfile"
grep -Fq "ARG NODE_BASE_IMAGE=\"$NODE_BASE_IMAGE\"" "$repository_root/Dockerfile"
grep -Fq "ARG PHP_BASE_IMAGE=\"$PHP_BASE_IMAGE_PRODUCTION\"" "$repository_root/Dockerfile"

configured_php_version="$(sed -n 's/^[[:space:]]*phpVersion:[[:space:]]*//p' "$repository_root/deploy/helm/holizuki/values.yaml")"

if [[ "$configured_php_version" != "\"$production_php_version\"" \
    && "$configured_php_version" != "'$production_php_version'" ]]; then
    printf 'Expected Helm PHP version %s.\n' "$production_php_version" >&2
    exit 1
fi

grep -Fq "  image: $POSTGRES_IMAGE" "$repository_root/deploy/helm/platform/values.yaml"
grep -Fq "node-name: holizuki-01" "$repository_root/deploy/server/k3s-config.yaml"
grep -Fq -- '--memory=128' "$repository_root/docker/entrypoint.sh"
grep -Fq 'schedule:work --whisper --no-interaction' "$repository_root/docker/entrypoint.sh"
grep -Fq 'opcache.enable_cli=0' "$repository_root/docker/php.ini"
grep -Fq 'group: deploy-staging' "$repository_root/.github/workflows/staging-control.yml"
grep -Fq 'source deploy/platform/versions.env' "$repository_root/.github/workflows/ci.yml"
grep -Fq 'source deploy/platform/versions.env' "$repository_root/.github/workflows/deploy.yml"

if grep -Eq 'version: v[0-9]+\.[0-9]+\.[0-9]+' "$repository_root/.github/workflows/"*.yml; then
    printf 'Helm workflow versions must come from deploy/platform/versions.env.\n' >&2
    exit 1
fi

printf 'Deployment configuration consistency tests passed.\n'
