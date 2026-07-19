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

export HOLIZUKI_DEPLOY_ROOT="$temporary_directory/server"
fake_bin="$temporary_directory/bin"
log="$temporary_directory/commands.log"
port_forward_ready="$temporary_directory/port-forward-ready"
mkdir -p \
    "$fake_bin" \
    "$HOLIZUKI_DEPLOY_ROOT/config" \
    "$HOLIZUKI_DEPLOY_ROOT/kubeconfig"

printf '{}\n' >"$HOLIZUKI_DEPLOY_ROOT/config/production-overrides.yaml"
printf 'apiVersion: v1\n' >"$HOLIZUKI_DEPLOY_ROOT/kubeconfig/production.yaml"

cat >"$fake_bin/helm" <<'SCRIPT'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'helm %s\n' "$*" >>"$TEST_COMMAND_LOG"
if [[ "${1:-}" == history ]]; then
    if [[ -n "${TEST_HELM_HISTORY_REVISION:-}" ]]; then
        printf '[{"revision":%s}]\n' "$TEST_HELM_HISTORY_REVISION"
        exit 0
    fi

    exit 1
fi
SCRIPT

cat >"$fake_bin/kubectl" <<'SCRIPT'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'kubectl %s\n' "$*" >>"$TEST_COMMAND_LOG"
touch "$TEST_PORT_FORWARD_READY"
while true; do
    sleep 1
done
SCRIPT

cat >"$fake_bin/curl" <<'SCRIPT'
#!/usr/bin/env bash
set -Eeuo pipefail
printf 'curl %s\n' "$*" >>"$TEST_COMMAND_LOG"
if [[ "$*" == *'127.0.0.1'* ]]; then
    for _ in {1..50}; do
        [[ -f "$TEST_PORT_FORWARD_READY" ]] && break
        sleep 0.02
    done

    [[ -f "$TEST_PORT_FORWARD_READY" ]]

    if [[ "$*" == *'/ready'* && "${TEST_READY_FAILURE:-false}" == true ]]; then
        exit 1
    fi
fi
if [[ "$*" == *"%{http_code}"* ]]; then
    printf '%s' "${TEST_EXTERNAL_STATUS:-200}"
fi
SCRIPT

cat >"$fake_bin/flock" <<'SCRIPT'
#!/usr/bin/env bash
exit 0
SCRIPT

chmod +x "$fake_bin"/*
export PATH="$fake_bin:$PATH"
export TEST_COMMAND_LOG="$log"
export TEST_PORT_FORWARD_READY="$port_forward_ready"
export HOLIZUKI_HEALTH_ATTEMPTS=2
export HOLIZUKI_HEALTH_RETRY_DELAY=0

chart="$temporary_directory/holizuki-0.1.0.tgz"
manifest="$temporary_directory/release-manifest.json"
candidate_manifest="$temporary_directory/candidate-manifest.json"
environment_values="$temporary_directory/production-values.yaml"
printf 'chart payload\n' >"$chart"
printf 'environment: production\n' >"$environment_values"
"$script_dir/build-release.sh" \
    v1.2.3-rc.1 \
    aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa \
    ghcr.io/example/holizuki \
    sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb \
    dunglas/frankenphp:1-php8.5@sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc \
    "$candidate_manifest" >/dev/null
jq --exit-status '.environment == "staging" and .runtime.phpVersion == "8.5" and has("promotedFrom") == false' "$candidate_manifest" >/dev/null

"$script_dir/build-release.sh" \
    v1.2.3 \
    aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa \
    ghcr.io/example/holizuki \
    sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb \
    dunglas/frankenphp:1-php8.5@sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc \
    "$manifest" \
    v1.2.3-rc.1 >/dev/null

chart_checksum="$(shasum -a 256 "$chart" | awk '{print $1}')"
manifest_checksum="$(shasum -a 256 "$manifest" | awk '{print $1}')"
environment_values_checksum="$(shasum -a 256 "$environment_values" | awk '{print $1}')"

"$deployment_script" \
    production \
    v1.2.3 \
    "$chart" \
    "$chart_checksum" \
    "$manifest" \
    "$manifest_checksum" \
    "$environment_values" \
    "$environment_values_checksum" \
    app.example.test \
    https://app.example.test

grep -Fq 'helm upgrade holizuki' "$log"
grep -Fq -- '--set-string image.digest=sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' "$log"
grep -Fq 'kubectl --kubeconfig' "$log"
jq --exit-status '.release == "v1.2.3" and .deployedAt != null' "$HOLIZUKI_DEPLOY_ROOT/history/production/v1.2.3.json" >/dev/null

rm -f "$HOLIZUKI_DEPLOY_ROOT/history/production/v1.2.3.json" "$port_forward_ready"
: >"$log"
export TEST_READY_FAILURE=true

if "$deployment_script" \
    production \
    v1.2.3 \
    "$chart" \
    "$chart_checksum" \
    "$manifest" \
    "$manifest_checksum" \
    "$environment_values" \
    "$environment_values_checksum" \
    app.example.test \
    https://app.example.test; then
    printf 'A failed readiness check unexpectedly succeeded.\n' >&2
    exit 1
fi

grep -Fq 'helm uninstall holizuki' "$log"
[[ ! -f "$HOLIZUKI_DEPLOY_ROOT/history/production/v1.2.3.json" ]]

rm -f "$port_forward_ready"
: >"$log"
export TEST_READY_FAILURE=false
export TEST_EXTERNAL_STATUS=503
export TEST_HELM_HISTORY_REVISION=7

if "$deployment_script" \
    production \
    v1.2.3 \
    "$chart" \
    "$chart_checksum" \
    "$manifest" \
    "$manifest_checksum" \
    "$environment_values" \
    "$environment_values_checksum" \
    app.example.test \
    https://app.example.test; then
    printf 'A failed external health check unexpectedly succeeded.\n' >&2
    exit 1
fi

grep -Fq 'helm rollback holizuki 7' "$log"
if grep -Fq 'helm uninstall holizuki' "$log"; then
    printf 'An existing release was uninstalled instead of rolled back.\n' >&2
    exit 1
fi

printf 'Helm deployment tests passed.\n'
