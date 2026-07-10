#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

environment="${1:-}"
tag="${2:-}"
chart="${3:-}"
expected_chart_checksum="${4:-}"
manifest="${5:-}"
expected_manifest_checksum="${6:-}"
environment_values="${7:-}"
expected_environment_values_checksum="${8:-}"
host="${9:-}"
external_url="${10:-}"

root="${HOLIZUKI_DEPLOY_ROOT:-/srv/holizuki}"
release_name=holizuki
namespace="$environment"
server_values="$root/config/$environment-overrides.yaml"
kubeconfig="$root/kubeconfig/$environment.yaml"
lock="$root/locks/$environment.lock"
history_directory="$root/history/$environment"

die() {
    printf '%s\n' "$1" >&2
    exit 1
}

checksum() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | awk '{print $1}'
        return
    fi

    shasum -a 256 "$1" | awk '{print $1}'
}

stop_port_forward() {
    if [[ -n "${port_forward_pid:-}" ]]; then
        kill "$port_forward_pid" >/dev/null 2>&1 || true
        wait "$port_forward_pid" 2>/dev/null || true
    fi
}

rollback() {
    if [[ -n "${previous_revision:-}" ]]; then
        printf 'Health verification failed; rolling back to revision %s.\n' "$previous_revision" >&2
        helm rollback "$release_name" "$previous_revision" \
            --cleanup-on-fail \
            --kubeconfig "$kubeconfig" \
            --namespace "$namespace" \
            --timeout 10m \
            --wait || true
    fi
}

verify_internal_health() {
    local local_port

    if [[ "$environment" == production ]]; then
        local_port=18080
    else
        local_port=18081
    fi

    kubectl \
        --kubeconfig "$kubeconfig" \
        --namespace "$namespace" \
        port-forward "service/$release_name" "$local_port:80" >"$root/tmp/$environment/port-forward.log" 2>&1 &
    port_forward_pid=$!

    for _ in {1..20}; do
        if curl --fail --header "Host: $host" --max-time 3 --silent "http://127.0.0.1:$local_port/up" >/dev/null 2>&1; then
            curl --fail --header "Host: $host" --max-time 3 --silent "http://127.0.0.1:$local_port/ready" >/dev/null
            stop_port_forward
            port_forward_pid=''
            return
        fi

        sleep 1
    done

    stop_port_forward
    port_forward_pid=''
    printf 'The in-cluster health check failed.\n' >&2
    return 1
}

verify_external_health() {
    local actual_status
    local expected_status=200

    if [[ "$environment" == staging ]]; then
        expected_status=401
    fi

    actual_status="$(curl \
        --location \
        --max-time 15 \
        --output /dev/null \
        --show-error \
        --silent \
        --write-out '%{http_code}' \
        "${external_url%/}/up")"

    if [[ "$actual_status" != "$expected_status" ]]; then
        printf 'External health check returned HTTP %s; expected %s.\n' "$actual_status" "$expected_status" >&2
        return 1
    fi
}

[[ "$environment" == production || "$environment" == staging ]] || die 'The environment must be production or staging.'
if [[ "$environment" == production ]]; then
    [[ "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || die 'Production requires a stable release tag.'
else
    [[ "$tag" =~ ^v[0-9]+\.[0-9]+\.[0-9]+-rc\.[0-9]+$ ]] || die 'Staging requires a release-candidate tag.'
fi
[[ "$root" =~ ^/[A-Za-z0-9._/-]+$ && "$root" != / ]] || die 'The deployment root is unsafe.'
[[ -f "$chart" ]] || die 'The Helm chart does not exist.'
[[ -f "$manifest" ]] || die 'The release manifest does not exist.'
[[ -f "$environment_values" ]] || die 'The environment values file does not exist.'
[[ -f "$server_values" ]] || die "Missing server override file: $server_values"
[[ -f "$kubeconfig" ]] || die "Missing namespace kubeconfig: $kubeconfig"
[[ "$expected_chart_checksum" =~ ^[a-f0-9]{64}$ ]] || die 'The chart checksum is invalid.'
[[ "$expected_manifest_checksum" =~ ^[a-f0-9]{64}$ ]] || die 'The manifest checksum is invalid.'
[[ "$expected_environment_values_checksum" =~ ^[a-f0-9]{64}$ ]] || die 'The environment values checksum is invalid.'
[[ "$(checksum "$chart")" == "$expected_chart_checksum" ]] || die 'The chart checksum does not match.'
[[ "$(checksum "$manifest")" == "$expected_manifest_checksum" ]] || die 'The manifest checksum does not match.'
[[ "$(checksum "$environment_values")" == "$expected_environment_values_checksum" ]] || die 'The environment values checksum does not match.'
[[ "$host" =~ ^[A-Za-z0-9.-]+$ ]] || die 'The application host is invalid.'
[[ "$external_url" == "https://$host" || "$external_url" == "https://$host/" ]] || die 'The external URL must be the HTTPS application origin.'

jq --exit-status \
    --arg release "$tag" \
    --arg environment "$environment" \
    '.schemaVersion == 1 and .release == $release and .environment == $environment and (.commit | test("^[a-f0-9]{40}$")) and (.image.digest | test("^sha256:[a-f0-9]{64}$")) and (.image.reference == (.image.repository + "@" + .image.digest))' \
    "$manifest" >/dev/null || die 'The release manifest is invalid.'

repository="$(jq --raw-output '.image.repository' "$manifest")"
digest="$(jq --raw-output '.image.digest' "$manifest")"
php_base_image="$(jq --raw-output '.runtime.phpBaseImage' "$manifest")"
php_version="$(jq --raw-output '.runtime.phpVersion' "$manifest")"

[[ "$repository" =~ ^[a-z0-9]+([._-][a-z0-9]+)*([/:][a-z0-9]+([._-][a-z0-9]+)*)+$ ]] || die 'The manifest image repository is invalid.'
[[ "$php_base_image" =~ ^[^[:space:]@]+@sha256:[a-f0-9]{64}$ ]] || die 'The manifest PHP base image is not pinned.'
[[ "$php_version" =~ ^[0-9]+\.[0-9]+$ ]] || die 'The manifest PHP version is invalid.'

mkdir -p "$history_directory" "$root/locks" "$root/tmp/$environment"
exec 9>"$lock"
flock -n 9 || die "Another $environment deployment is running."
trap stop_port_forward EXIT

helm lint "$chart" --values "$environment_values" --values "$server_values" >/dev/null

previous_revision="$(helm history "$release_name" \
    --kubeconfig "$kubeconfig" \
    --max 1 \
    --namespace "$namespace" \
    --output json 2>/dev/null | jq --raw-output '.[-1].revision // empty' || true)"

helm upgrade "$release_name" "$chart" \
    --cleanup-on-fail \
    --history-max 10 \
    --install \
    --kubeconfig "$kubeconfig" \
    --namespace "$namespace" \
    --rollback-on-failure \
    --set-string "host=$host" \
    --set-string "image.digest=$digest" \
    --set-string "image.repository=$repository" \
    --set-string "image.phpVersion=$php_version" \
    --set-string "release=$tag" \
    --timeout 10m \
    --values "$environment_values" \
    --values "$server_values" \
    --wait

if ! verify_internal_health || ! verify_external_health; then
    rollback
    exit 1
fi

history_file="$history_directory/$tag.json"
jq \
    --arg deployedAt "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
    --arg host "$host" \
    '. + {deployedAt: $deployedAt, host: $host}' \
    "$manifest" >"$history_file.tmp"
mv "$history_file.tmp" "$history_file"

printf 'Deployed %s to %s using %s@%s.\n' "$tag" "$environment" "$repository" "$digest"
