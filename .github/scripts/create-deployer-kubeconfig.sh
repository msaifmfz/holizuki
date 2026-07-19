#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

environment="${1:-}"
server="${2:-}"
output="${3:-}"
retry_attempts="${HOLIZUKI_KUBECONFIG_ATTEMPTS:-30}"
retry_delay="${HOLIZUKI_KUBECONFIG_RETRY_DELAY:-1}"

die() {
    printf '%s\n' "$1" >&2
    exit 1
}

[[ "$environment" == production || "$environment" == staging ]] || die 'The environment must be production or staging.'
[[ "$server" =~ ^https://[A-Za-z0-9.:-]+$ ]] || die 'The Kubernetes API URL is invalid.'
[[ "$output" == /* ]] || die 'The output path must be absolute.'
[[ "$retry_attempts" =~ ^[1-9][0-9]*$ ]] || die 'The retry attempt count must be a positive integer.'
[[ "$retry_delay" =~ ^[0-9]+([.][0-9]+)?$ ]] || die 'The retry delay must be a non-negative number.'

output_directory="$(dirname -- "$output")"
mkdir -p "$output_directory"
certificate_authority_file="$(mktemp)"
temporary_kubeconfig="$(mktemp "$output_directory/.github-deployer.XXXXXX")"

cleanup() {
    rm -f -- "$certificate_authority_file" "$temporary_kubeconfig"
}

trap cleanup EXIT

token=''

for ((attempt = 1; attempt <= retry_attempts; attempt++)); do
    token_data=''
    certificate_authority_data=''

    if token_data="$(kubectl \
        --namespace "$environment" \
        get secret github-deployer-token \
        --output jsonpath='{.data.token}')" \
        && certificate_authority_data="$(kubectl \
            --namespace "$environment" \
            get secret github-deployer-token \
            --output jsonpath='{.data.ca\.crt}')" \
        && [[ -n "$token_data" && -n "$certificate_authority_data" ]]; then
        token="$(base64 --decode <<<"$token_data")"
        base64 --decode <<<"$certificate_authority_data" >"$certificate_authority_file"
        break
    fi

    sleep "$retry_delay"
done

[[ -n "$token" ]] || die 'The deployment service account token is empty.'
[[ -s "$certificate_authority_file" ]] || die 'The cluster certificate authority is empty.'

kubectl config \
    --kubeconfig "$temporary_kubeconfig" \
    set-cluster holizuki \
    --certificate-authority="$certificate_authority_file" \
    --embed-certs=true \
    --server="$server" >/dev/null

kubectl config \
    --kubeconfig "$temporary_kubeconfig" \
    set-credentials github-deployer \
    --token="$token" >/dev/null

kubectl config \
    --kubeconfig "$temporary_kubeconfig" \
    set-context "$environment" \
    --cluster=holizuki \
    --namespace="$environment" \
    --user=github-deployer >/dev/null

kubectl config \
    --kubeconfig "$temporary_kubeconfig" \
    use-context "$environment" >/dev/null

chmod 0600 "$temporary_kubeconfig"
mv -- "$temporary_kubeconfig" "$output"
temporary_kubeconfig=''

printf 'Created the namespace-scoped kubeconfig at %s.\n' "$output"
