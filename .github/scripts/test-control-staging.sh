#!/usr/bin/env bash

set -Eeuo pipefail

script_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
control_script="$script_dir/control-staging.sh"
temporary_directory="$(mktemp -d)"
temporary_directory="$(CDPATH='' cd -- "$temporary_directory" && pwd -P)"

cleanup() {
    rm -rf -- "$temporary_directory"
}

trap cleanup EXIT

export HOLIZUKI_DEPLOY_ROOT="$temporary_directory/server"
export TEST_COMMAND_LOG="$temporary_directory/commands.log"
export TEST_REPLICA_STATE="$temporary_directory/replicas"
fake_bin="$temporary_directory/bin"

mkdir -p \
    "$fake_bin" \
    "$HOLIZUKI_DEPLOY_ROOT/kubeconfig" \
    "$HOLIZUKI_DEPLOY_ROOT/locks"
printf 'apiVersion: v1\n' >"$HOLIZUKI_DEPLOY_ROOT/kubeconfig/staging.yaml"
printf '0\n' >"$TEST_REPLICA_STATE"
: >"$HOLIZUKI_DEPLOY_ROOT/locks/staging.lock"

cat >"$fake_bin/kubectl" <<'SCRIPT'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'kubectl %s\n' "$*" >>"$TEST_COMMAND_LOG"

if [[ "$*" == *'scale deployment holizuki-web --replicas=1'* ]]; then
    printf '1\n' >"$TEST_REPLICA_STATE"
    exit 0
fi

if [[ "$*" == *'scale deployment holizuki-web --replicas=0'* ]]; then
    printf '0\n' >"$TEST_REPLICA_STATE"
    exit 0
fi

if [[ "$*" == *'get deployment holizuki-web --output json'* ]]; then
    replicas="$(<"$TEST_REPLICA_STATE")"
    printf '{"spec":{"replicas":%s},"status":{"readyReplicas":%s,"availableReplicas":%s}}\n' \
        "$replicas" "$replicas" "$replicas"
    exit 0
fi

if [[ "$*" == *'get pods'* ]]; then
    exit 0
fi

if [[ "$*" == *'rollout status deployment/holizuki-web'* ]]; then
    exit 0
fi

exit 1
SCRIPT

cat >"$fake_bin/flock" <<'SCRIPT'
#!/usr/bin/env bash
exit 0
SCRIPT

chmod +x "$fake_bin"/*
export PATH="$fake_bin:$PATH"

status_output="$($control_script status)"
[[ "$status_output" == 'Staging: desired=0 ready=0 available=0' ]]

start_output="$($control_script start)"
[[ "$start_output" == 'Staging: desired=1 ready=1 available=1' ]]
grep -Fq 'scale deployment holizuki-web --replicas=1' "$TEST_COMMAND_LOG"
grep -Fq 'rollout status deployment/holizuki-web --timeout=10m' "$TEST_COMMAND_LOG"

stop_output="$($control_script stop)"
[[ "$stop_output" == 'Staging: desired=0 ready=0 available=0' ]]
grep -Fq 'scale deployment holizuki-web --replicas=0' "$TEST_COMMAND_LOG"

if "$control_script" restart >/dev/null 2>&1; then
    printf 'An invalid staging action unexpectedly succeeded.\n' >&2
    exit 1
fi

printf 'Staging control tests passed.\n'
