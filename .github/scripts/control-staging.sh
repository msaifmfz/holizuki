#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

action="${1:-status}"
root="${HOLIZUKI_DEPLOY_ROOT:-/srv/holizuki}"
kubeconfig="$root/kubeconfig/staging.yaml"
lock="$root/locks/staging.lock"
namespace=staging
deployment=holizuki-web

die() {
    printf '%s\n' "$1" >&2
    exit 1
}

report_status() {
    local deployment_json
    local desired
    local ready
    local available

    deployment_json="$(kubectl \
        --kubeconfig "$kubeconfig" \
        --namespace "$namespace" \
        get deployment "$deployment" \
        --output json)"
    desired="$(jq --raw-output '.spec.replicas // 0' <<<"$deployment_json")"
    ready="$(jq --raw-output '.status.readyReplicas // 0' <<<"$deployment_json")"
    available="$(jq --raw-output '.status.availableReplicas // 0' <<<"$deployment_json")"

    printf 'Staging: desired=%s ready=%s available=%s\n' "$desired" "$ready" "$available"
}

[[ "$action" == start || "$action" == stop || "$action" == status ]] \
    || die 'The action must be start, stop, or status.'
[[ "$root" =~ ^/[A-Za-z0-9._/-]+$ && "$root" != / ]] || die 'The deployment root is unsafe.'
[[ -f "$kubeconfig" ]] || die "Missing staging kubeconfig: $kubeconfig"
[[ -f "$lock" ]] || die "Missing staging deployment lock: $lock"

exec 9>"$lock"
flock -n 9 || die 'A staging deployment or control operation is already running.'

case "$action" in
    start)
        kubectl \
            --kubeconfig "$kubeconfig" \
            --namespace "$namespace" \
            scale deployment "$deployment" \
            --replicas=1
        kubectl \
            --kubeconfig "$kubeconfig" \
            --namespace "$namespace" \
            rollout status "deployment/$deployment" \
            --timeout=10m
        ;;
    stop)
        kubectl \
            --kubeconfig "$kubeconfig" \
            --namespace "$namespace" \
            scale deployment "$deployment" \
            --replicas=0

        pod_names="$(kubectl \
            --kubeconfig "$kubeconfig" \
            --namespace "$namespace" \
            get pods \
            --selector app.kubernetes.io/name=holizuki,app.kubernetes.io/component=web \
            --output name)"

        if [[ -n "$pod_names" ]]; then
            kubectl \
                --kubeconfig "$kubeconfig" \
                --namespace "$namespace" \
                wait \
                --for=delete \
                --selector app.kubernetes.io/name=holizuki,app.kubernetes.io/component=web \
                pod \
                --timeout=2m
        fi
        ;;
esac

report_status
