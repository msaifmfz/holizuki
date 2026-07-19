#!/usr/bin/env bash

set -Eeuo pipefail

script_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
classifier="$script_dir/classify-release.sh"

assert_classification() {
    local tag="$1"
    local expected="$2"
    local actual

    actual="$($classifier "$tag")"

    if [[ "$actual" != "$expected" ]]; then
        printf 'Expected %s to classify as %s, got %s.\n' "$tag" "$expected" "$actual" >&2
        exit 1
    fi
}

assert_rejected() {
    local tag="$1"

    if "$classifier" "$tag" >/dev/null 2>&1; then
        printf 'Expected %s to be rejected.\n' "$tag" >&2
        exit 1
    fi
}

assert_classification 'v1.2.3' 'production'
assert_classification 'v0.0.1' 'production'
assert_classification 'v1.2.3-rc.1' 'staging'
assert_classification 'v10.20.30-rc.12' 'staging'

assert_rejected ''
assert_rejected '1.2.3'
assert_rejected 'v1.2'
assert_rejected 'v1.2.3-rc'
assert_rejected 'v1.2.3-rc.0.1'
assert_rejected 'v1.2.3-beta.1'
assert_rejected 'v1.2.3+build.1'
assert_rejected 'v01.2.3'
assert_rejected 'v1.02.3'
assert_rejected 'v1.2.03'
assert_rejected 'v1.2.3-rc.01'

printf 'Release classification tests passed.\n'
