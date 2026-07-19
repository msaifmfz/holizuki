#!/usr/bin/env bash

set -Eeuo pipefail

script_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
temporary_directory="$(mktemp -d)"
temporary_directory="$(CDPATH='' cd -- "$temporary_directory" && pwd -P)"

cleanup() {
    rm -rf -- "$temporary_directory"
}

trap cleanup EXIT

fake_bin="$temporary_directory/bin"
bundle="$temporary_directory/bundle"
verification="$temporary_directory/staging-verification.json"
log="$temporary_directory/gh.log"
state="$temporary_directory/release-state"
commit=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
mkdir -p "$fake_bin" "$bundle"
printf 'chart\n' >"$bundle/holizuki-0.1.0.tgz"
printf 'environment\n' >"$bundle/environment-values.yaml"
printf '{}\n' >"$bundle/release-manifest.json"
printf '{}\n' >"$verification"

cat >"$fake_bin/gh" <<'SCRIPT'
#!/usr/bin/env bash
set -Eeuo pipefail

printf 'gh' >>"$TEST_COMMAND_LOG"
printf ' %q' "$@" >>"$TEST_COMMAND_LOG"
printf '\n' >>"$TEST_COMMAND_LOG"

if [[ "${1:-}" == api ]]; then
    if [[ "$*" == *'/commits/'* ]]; then
        printf '%s\n' "$TEST_REMOTE_COMMIT"
        exit 0
    fi

    if [[ "$*" == *'/releases/tags/'* ]]; then
        release_state="$(<"$TEST_RELEASE_STATE")"

        if [[ "$release_state" == missing ]]; then
            exit 1
        fi

        if [[ "$release_state" == draft ]]; then
            printf '{"draft":true,"immutable":false,"prerelease":%s}\n' "$TEST_EXPECTED_PRERELEASE"
        else
            printf '{"draft":false,"immutable":true,"prerelease":%s,"html_url":"https://example.test/release"}\n' "$TEST_EXPECTED_PRERELEASE"
        fi

        exit 0
    fi
fi

if [[ "${1:-}" == release && "${2:-}" == create ]]; then
    [[ "$(<"$TEST_RELEASE_STATE")" == missing ]]
    [[ " $* " == *' --draft '* ]]
    [[ " $* " == *' --verify-tag '* ]]

    if [[ "$TEST_EXPECTED_PRERELEASE" == true ]]; then
        [[ " $* " == *' --prerelease '* ]]
    else
        [[ " $* " != *' --prerelease '* ]]
    fi

    printf 'draft\n' >"$TEST_RELEASE_STATE"
    exit 0
fi

if [[ "${1:-}" == release && "${2:-}" == upload ]]; then
    [[ "$(<"$TEST_RELEASE_STATE")" == draft ]]
    [[ " $* " == *' --clobber '* ]]
    exit 0
fi

if [[ "${1:-}" == release && "${2:-}" == edit ]]; then
    [[ "$(<"$TEST_RELEASE_STATE")" == draft ]]
    [[ " $* " == *' --draft=false '* ]]

    if [[ "$TEST_EXPECTED_PRERELEASE" == true ]]; then
        [[ " $* " == *' --prerelease '* ]]
        [[ " $* " == *' --latest=false '* ]]
    else
        [[ " $* " == *' --prerelease=false '* ]]
        [[ " $* " == *' --latest '* ]]
    fi

    printf 'published\n' >"$TEST_RELEASE_STATE"
    exit 0
fi

printf 'Unexpected gh command: %s\n' "$*" >&2
exit 1
SCRIPT

chmod +x "$fake_bin/gh"
export PATH="$fake_bin:$PATH"
export GITHUB_REPOSITORY=example/holizuki
export TEST_COMMAND_LOG="$log"
export TEST_RELEASE_STATE="$state"
export TEST_REMOTE_COMMIT="$commit"

printf 'missing\n' >"$state"
export TEST_EXPECTED_PRERELEASE=true
"$script_dir/publish-release.sh" staging v1.2.3-rc.1 "$commit" "$bundle" "$verification" >/dev/null

create_line="$(grep -n '^gh release create ' "$log" | cut -d: -f1)"
upload_line="$(grep -n '^gh release upload ' "$log" | cut -d: -f1)"
publish_line="$(grep -n '^gh release edit ' "$log" | cut -d: -f1)"
((create_line < upload_line && upload_line < publish_line))

printf 'missing\n' >"$state"
: >"$log"
export TEST_EXPECTED_PRERELEASE=false
"$script_dir/publish-release.sh" production v1.2.3 "$commit" "$bundle" >/dev/null
grep -Fq -- '--prerelease=false' "$log"

printf 'published\n' >"$state"
: >"$log"
if "$script_dir/publish-release.sh" production v1.2.3 "$commit" "$bundle" >/dev/null 2>&1; then
    printf 'An already-published release unexpectedly allowed mutation.\n' >&2
    exit 1
fi
if grep -q '^gh release upload ' "$log"; then
    printf 'An already-published release reached asset upload.\n' >&2
    exit 1
fi

printf 'missing\n' >"$state"
export TEST_REMOTE_COMMIT=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
if "$script_dir/publish-release.sh" production v1.2.3 "$commit" "$bundle" >/dev/null 2>&1; then
    printf 'A moved release tag unexpectedly passed validation.\n' >&2
    exit 1
fi

printf 'Immutable release publication tests passed.\n'
