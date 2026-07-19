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
log="$temporary_directory/kubectl.log"
attempt_counter="$temporary_directory/attempts"
output="$temporary_directory/kubeconfig/staging.yaml"
mkdir -p "$fake_bin"
printf '0\n' >"$attempt_counter"

cat >"$fake_bin/kubectl" <<'SCRIPT'
#!/usr/bin/env bash
set -Eeuo pipefail

printf 'kubectl %s\n' "$*" >>"$TEST_COMMAND_LOG"

if [[ "$*" == *'get secret github-deployer-token'* ]]; then
    attempts="$(<"$TEST_ATTEMPT_COUNTER")"
    attempts=$((attempts + 1))
    printf '%s\n' "$attempts" >"$TEST_ATTEMPT_COUNTER"

    if ((attempts <= 2)); then
        exit 1
    fi

    if [[ "$*" == *'{.data.token}'* ]]; then
        printf 'ZGVwbG95ZXItdG9rZW4='
    else
        printf 'Y2VydGlmaWNhdGUtYXV0aG9yaXR5'
    fi

    exit 0
fi

if [[ "$*" == *'set-credentials github-deployer'* && "${TEST_CONFIG_FAILURE:-false}" == true ]]; then
    exit 1
fi

SCRIPT

chmod +x "$fake_bin/kubectl"
export PATH="$fake_bin:$PATH"
export TEST_ATTEMPT_COUNTER="$attempt_counter"
export TEST_COMMAND_LOG="$log"
export HOLIZUKI_KUBECONFIG_ATTEMPTS=4
export HOLIZUKI_KUBECONFIG_RETRY_DELAY=0

"$script_dir/create-deployer-kubeconfig.sh" \
    staging \
    https://127.0.0.1:6443 \
    "$output"

[[ -f "$output" ]]
[[ "$(<"$attempt_counter")" == 4 ]]
grep -Fq 'set-cluster holizuki' "$log"
grep -Fq 'set-credentials github-deployer --token=deployer-token' "$log"
grep -Fq 'use-context staging' "$log"

printf 'existing kubeconfig\n' >"$output"
export TEST_CONFIG_FAILURE=true

if "$script_dir/create-deployer-kubeconfig.sh" \
    staging \
    https://127.0.0.1:6443 \
    "$output" >/dev/null 2>&1; then
    printf 'A failed kubeconfig build unexpectedly succeeded.\n' >&2
    exit 1
fi

grep -Fxq 'existing kubeconfig' "$output"

printf 'Deployment kubeconfig retry tests passed.\n'
