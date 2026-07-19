#!/usr/bin/env bash

set -Eeuo pipefail

tag="${1:-}"

case "$tag" in
    v[0-9]*.[0-9]*.[0-9]*-rc.[0-9]*)
        if [[ "$tag" =~ ^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)-rc\.(0|[1-9][0-9]*)$ ]]; then
            printf 'staging\n'
            exit 0
        fi
        ;;
    v[0-9]*.[0-9]*.[0-9]*)
        if [[ "$tag" =~ ^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
            printf 'production\n'
            exit 0
        fi
        ;;
esac

printf 'Unsupported release tag: %s\n' "$tag" >&2
printf 'Expected vMAJOR.MINOR.PATCH or vMAJOR.MINOR.PATCH-rc.NUMBER.\n' >&2
exit 64
