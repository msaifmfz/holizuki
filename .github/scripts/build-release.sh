#!/usr/bin/env bash

set -Eeuo pipefail

script_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repository_root="$(CDPATH='' cd -- "$script_dir/../.." && pwd)"
tag="${1:-}"
archive="${2:-}"

die() {
    printf '%s\n' "$1" >&2
    exit 1
}

[[ -n "$archive" ]] || die 'An output archive path is required.'
"$script_dir/classify-release.sh" "$tag" >/dev/null

cd "$repository_root"

[[ ! -e .env ]] || die 'Refusing to package a repository that contains an .env file.'

for required_path in artisan bootstrap/app.php composer.lock public/build/manifest.json vendor/autoload.php; do
    [[ -e "$required_path" ]] || die "Missing release input: $required_path"
done

mkdir -p "$(dirname -- "$archive")"
rm -f -- "$archive"

tar \
    --exclude='./.env*' \
    --exclude='./.git' \
    --exclude='./.github' \
    --exclude='./.idea' \
    --exclude='./.vscode' \
    --exclude='./coverage' \
    --exclude='./dist' \
    --exclude='./node_modules' \
    --exclude='./playwright-report' \
    --exclude='./storage/logs/*' \
    --exclude='./test-results' \
    --exclude='./tests' \
    -czf "$archive" \
    .

if tar -tzf "$archive" | grep -Eq '(^|/)\.env($|/)'; then
    die 'The release archive unexpectedly contains an environment file.'
fi

printf 'Built %s for %s.\n' "$archive" "$tag"
