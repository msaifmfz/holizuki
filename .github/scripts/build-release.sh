#!/usr/bin/env bash

set -Eeuo pipefail

script_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
tag="${1:-}"
commit="${2:-}"
repository="${3:-}"
digest="${4:-}"
php_base_image="${5:-}"
output="${6:-}"
source_release="${7:-}"

die() {
    printf '%s\n' "$1" >&2
    exit 1
}

environment="$("$script_dir/classify-release.sh" "$tag")"

[[ "$commit" =~ ^[a-f0-9]{40}$ ]] || die 'The release commit must be a full Git SHA.'
[[ "$repository" =~ ^[a-z0-9]+([._-][a-z0-9]+)*([/:][a-z0-9]+([._-][a-z0-9]+)*)+$ ]] || die 'The image repository is invalid.'
[[ "$digest" =~ ^sha256:[a-f0-9]{64}$ ]] || die 'The image digest is invalid.'
[[ "$php_base_image" =~ ^[^[:space:]@]+@sha256:[a-f0-9]{64}$ ]] || die 'The PHP base image must be digest-pinned.'
[[ "$php_base_image" =~ php([0-9]+\.[0-9]+) ]] || die 'The PHP base image tag must identify its PHP major and minor version.'
php_version="${BASH_REMATCH[1]}"
[[ -n "$output" ]] || die 'An output manifest path is required.'

if [[ "$environment" == production ]]; then
    [[ "$source_release" =~ ^v[0-9]+\.[0-9]+\.[0-9]+-rc\.[0-9]+$ ]] || die 'Production must identify its promoted release candidate.'
else
    [[ -z "$source_release" ]] || die 'A staging candidate cannot have a source release.'
fi

mkdir -p "$(dirname -- "$output")"

jq --null-input \
    --arg release "$tag" \
    --arg environment "$environment" \
    --arg commit "$commit" \
    --arg repository "$repository" \
    --arg digest "$digest" \
    --arg phpBaseImage "$php_base_image" \
    --arg phpVersion "$php_version" \
    --arg sourceRelease "$source_release" \
    '{
        schemaVersion: 1,
        release: $release,
        environment: $environment,
        commit: $commit,
        image: {
            repository: $repository,
            digest: $digest,
            reference: ($repository + "@" + $digest)
        },
        runtime: {
            phpBaseImage: $phpBaseImage,
            phpVersion: $phpVersion
        }
    } + if $sourceRelease == "" then {} else {promotedFrom: $sourceRelease} end' >"$output"

jq --exit-status . "$output" >/dev/null
printf 'Created release manifest for %s.\n' "$tag"
