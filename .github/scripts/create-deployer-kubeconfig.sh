#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

environment="${1:-}"
server="${2:-}"
output="${3:-}"

die() {
    printf '%s\n' "$1" >&2
    exit 1
}

[[ "$environment" == production || "$environment" == staging ]] || die 'The environment must be production or staging.'
[[ "$server" =~ ^https://[A-Za-z0-9.:-]+$ ]] || die 'The Kubernetes API URL is invalid.'
[[ "$output" == /* ]] || die 'The output path must be absolute.'

certificate_authority_file="$(mktemp)"

cleanup() {
    rm -f -- "$certificate_authority_file"
}

trap cleanup EXIT

token=''

for _ in {1..30}; do
    token_data="$(kubectl \
        --namespace "$environment" \
        get secret github-deployer-token \
        --output jsonpath='{.data.token}')"
    certificate_authority_data="$(kubectl \
        --namespace "$environment" \
        get secret github-deployer-token \
        --output jsonpath='{.data.ca\.crt}')"

    if [[ -n "$token_data" && -n "$certificate_authority_data" ]]; then
        token="$(base64 --decode <<<"$token_data")"
        base64 --decode <<<"$certificate_authority_data" >"$certificate_authority_file"
        break
    fi

    sleep 1
done

[[ -n "$token" ]] || die 'The deployment service account token is empty.'
[[ -s "$certificate_authority_file" ]] || die 'The cluster certificate authority is empty.'

mkdir -p "$(dirname -- "$output")"

kubectl config \
    --kubeconfig "$output" \
    set-cluster holizuki \
    --certificate-authority="$certificate_authority_file" \
    --embed-certs=true \
    --server="$server" >/dev/null

kubectl config \
    --kubeconfig "$output" \
    set-credentials github-deployer \
    --token="$token" >/dev/null

kubectl config \
    --kubeconfig "$output" \
    set-context "$environment" \
    --cluster=holizuki \
    --namespace="$environment" \
    --user=github-deployer >/dev/null

kubectl config \
    --kubeconfig "$output" \
    use-context "$environment" >/dev/null

printf 'Created the namespace-scoped kubeconfig at %s.\n' "$output"
