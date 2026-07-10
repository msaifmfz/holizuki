#!/usr/bin/env bash

set -Eeuo pipefail

script_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
deployment_script="$script_dir/deploy-release.sh"
temporary_directory="$(mktemp -d)"
temporary_directory="$(CDPATH='' cd -- "$temporary_directory" && pwd -P)"

cleanup() {
    rm -rf -- "$temporary_directory"
}

trap cleanup EXIT

deployment_root="$temporary_directory/application"
fake_php="$temporary_directory/php"

mkdir -p "$deployment_root/shared"
printf 'APP_ENV=testing\n' >"$deployment_root/shared/.env"

# The generated script evaluates its own arguments.
# shellcheck disable=SC2016
printf '%s\n' \
    '#!/usr/bin/env bash' \
    'set -Eeuo pipefail' \
    '[[ "${1:-}" == "artisan" ]]' \
    'case "${2:-}" in' \
    '    migrate|optimize|queue:restart) exit 0 ;;' \
    '    *) exit 1 ;;' \
    'esac' >"$fake_php"
chmod +x "$fake_php"

create_archive() {
    local tag="$1"
    local payload="$temporary_directory/payload-$tag"
    local archive="$temporary_directory/$tag.tar.gz"

    mkdir -p "$payload/bootstrap" "$payload/public/build" "$payload/vendor"
    printf '#!/usr/bin/env php\n' >"$payload/artisan"
    printf '<?php\n' >"$payload/bootstrap/app.php"
    printf '{}\n' >"$payload/public/build/manifest.json"
    printf '<?php\n' >"$payload/vendor/autoload.php"
    tar -czf "$archive" -C "$payload" .

    printf '%s\n' "$archive"
}

archive_one="$(create_archive 'v1.2.3-rc.1')"
checksum_one="$(shasum -a 256 "$archive_one" | awk '{print $1}')"
"$deployment_script" "$deployment_root" 'v1.2.3-rc.1' "$archive_one" "$checksum_one" "$fake_php" 1 ''

[[ "$(CDPATH='' cd -- "$deployment_root/current" && pwd -P)" == "$deployment_root/releases/v1.2.3-rc.1" ]]
[[ -L "$deployment_root/current/.env" ]]
[[ -L "$deployment_root/current/storage" ]]

archive_two="$(create_archive 'v1.2.3')"
checksum_two="$(shasum -a 256 "$archive_two" | awk '{print $1}')"
"$deployment_script" "$deployment_root" 'v1.2.3' "$archive_two" "$checksum_two" "$fake_php" 1 ''

[[ "$(CDPATH='' cd -- "$deployment_root/current" && pwd -P)" == "$deployment_root/releases/v1.2.3" ]]
[[ ! -e "$deployment_root/releases/v1.2.3-rc.1" ]]

printf 'Atomic deployment tests passed.\n'
