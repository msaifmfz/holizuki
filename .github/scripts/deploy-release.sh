#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

root="${1:-}"
tag="${2:-}"
archive="${3:-}"
expected_checksum="${4:-}"
php_binary="${5:-php}"
keep_releases="${6:-5}"
healthcheck_url="${7:-}"

die() {
    printf '%s\n' "$1" >&2
    exit 1
}

checksum() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
        return
    fi

    if command -v shasum >/dev/null 2>&1; then
        shasum -a 256 "$1" | awk '{print $1}'
        return
    fi

    die 'Neither sha256sum nor shasum is available on the deployment host.'
}

switch_current() {
    local target="$1"
    local pending_link="$root/.current-${RANDOM}-${RANDOM}"

    ln -s "$target" "$pending_link"

    if mv --help 2>&1 | grep -q -- ' -T'; then
        mv -Tf "$pending_link" "$root/current"
    else
        mv -fh "$pending_link" "$root/current"
    fi
}

list_releases() {
    if stat --version >/dev/null 2>&1; then
        find "$releases" -mindepth 1 -maxdepth 1 -type d -name 'v*' -exec stat -c '%Y %n' {} +
    else
        find "$releases" -mindepth 1 -maxdepth 1 -type d -name 'v*' -exec stat -f '%m %N' {} +
    fi
}

resolve_directory() {
    CDPATH='' cd -- "$1" && pwd -P
}

[[ "$root" =~ ^/[A-Za-z0-9._/-]+$ ]] || die 'DEPLOY_PATH must be an absolute path containing only safe characters.'
[[ "$root" != '/' && "$root" != *'/../'* && "$root" != */.. ]] || die 'DEPLOY_PATH is unsafe.'
[[ "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-rc\.[0-9]+)?$ ]] || die 'The release tag is invalid.'
[[ -f "$archive" ]] || die 'The release archive does not exist.'
[[ "$expected_checksum" =~ ^[a-f0-9]{64}$ ]] || die 'The expected SHA-256 checksum is invalid.'
[[ "$php_binary" =~ ^[A-Za-z0-9._/-]+$ ]] || die 'The PHP binary contains unsafe characters.'
[[ "$keep_releases" =~ ^[1-9][0-9]*$ ]] || die 'The release retention count must be a positive integer.'
[[ -z "$healthcheck_url" || "$healthcheck_url" =~ ^https?:// ]] || die 'The health-check URL must use HTTP or HTTPS.'

actual_checksum="$(checksum "$archive")"
[[ "$actual_checksum" == "$expected_checksum" ]] || die 'The release archive checksum does not match.'

if tar -tzf "$archive" | grep -Eq '(^|/)\.env($|/)'; then
    die 'The release archive contains an environment file.'
fi

while IFS= read -r entry; do
    [[ "$entry" != /* && "$entry" != ../* && "$entry" != *'/../'* ]] || die 'The release archive contains an unsafe path.'
done < <(tar -tzf "$archive")

releases="$root/releases"
shared="$root/shared"
final_release="$releases/$tag"

[[ -f "$shared/.env" ]] || die "Missing shared environment file: $shared/.env"
[[ ! -e "$final_release" ]] || die "Release already exists: $final_release"

mkdir -p \
    "$releases" \
    "$shared/storage/app/public" \
    "$shared/storage/framework/cache/data" \
    "$shared/storage/framework/sessions" \
    "$shared/storage/framework/views" \
    "$shared/storage/logs"

temporary_release="$(mktemp -d "$releases/.deploy-${tag}.XXXXXX")"
release_path="$temporary_release"
previous_release=""
switched=false
successful=false

cleanup() {
    if [[ "$successful" == true ]]; then
        return
    fi

    if [[ "$switched" == true ]]; then
        if [[ -n "$previous_release" && -d "$previous_release" ]]; then
            switch_current "$previous_release"
        else
            rm -f "$root/current"
        fi
    fi

    rm -rf -- "$release_path"
}

trap cleanup EXIT

tar --no-same-owner --no-same-permissions -xzf "$archive" -C "$temporary_release"

for required_path in artisan bootstrap/app.php public/build/manifest.json vendor/autoload.php; do
    [[ -e "$temporary_release/$required_path" ]] || die "Release is missing: $required_path"
done

rm -rf -- "$temporary_release/storage"
ln -s "$shared/storage" "$temporary_release/storage"
ln -s "$shared/.env" "$temporary_release/.env"
rm -rf -- "$temporary_release/public/storage"
ln -s "$shared/storage/app/public" "$temporary_release/public/storage"

mv "$temporary_release" "$final_release"
release_path="$final_release"

cd "$final_release"
"$php_binary" artisan migrate --force --isolated
"$php_binary" artisan optimize

if [[ -L "$root/current" ]]; then
    previous_release="$(resolve_directory "$root/current")"
fi

switch_current "$final_release"
switched=true

if [[ -n "$healthcheck_url" ]]; then
    command -v curl >/dev/null 2>&1 || die 'curl is required when a health-check URL is configured.'
    curl --fail --location --retry 5 --retry-connrefused --show-error --silent --max-time 15 "$healthcheck_url" >/dev/null
fi

"$php_binary" artisan queue:restart || printf 'Warning: queue workers could not be restarted.\n' >&2

successful=true

retained=0
while read -r _ release_directory; do
    retained=$((retained + 1))

    if (( retained > keep_releases )) && [[ "$release_directory" != "$final_release" ]]; then
        rm -rf -- "$release_directory"
    fi
done < <(list_releases | sort -rn)

printf 'Activated %s at %s/current.\n' "$tag" "$root"
