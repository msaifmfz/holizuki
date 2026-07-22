#!/usr/bin/env bash

set -Eeuo pipefail

script_dir="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repository_root="$(CDPATH='' cd -- "$script_dir/../.." && pwd)"
temporary_directory="$(mktemp -d)"

cleanup() {
    rm -rf -- "$temporary_directory"
}

trap cleanup EXIT

production_manifest="$temporary_directory/production.yaml"
staging_manifest="$temporary_directory/staging.yaml"
platform_manifest="$temporary_directory/platform.yaml"

helm template holizuki "$repository_root/deploy/helm/holizuki" \
    --namespace production \
    --values "$repository_root/deploy/helm/holizuki/values-production.yaml" \
    >"$production_manifest"
helm template holizuki "$repository_root/deploy/helm/holizuki" \
    --namespace staging \
    --values "$repository_root/deploy/helm/holizuki/values-staging.yaml" \
    >"$staging_manifest"
helm template holizuki-platform "$repository_root/deploy/helm/platform" \
    --set backup.enabled=true \
    --set monitoring.enabled=true \
    --set postgres.monitoringEnabled=true \
    >"$platform_manifest"

for manifest in "$production_manifest" "$staging_manifest"; do
    [[ "$(grep -c '^kind: Deployment$' "$manifest")" -eq 1 ]]
    if grep -Fq 'kind: CronJob' "$manifest"; then
        printf 'Application manifest unexpectedly contains a CronJob.\n' >&2
        exit 1
    fi
    grep -Fq 'replicas: 1' "$manifest"
    grep -Fq '        - name: optimize' "$manifest"
    grep -Fq '        - name: web' "$manifest"
    grep -Fq '        - name: worker' "$manifest"
    grep -Fq '        - name: scheduler' "$manifest"
    grep -Fq 'CACHE_STORE: "database"' "$manifest"
    grep -Fq 'DB_HOST: "holizuki-postgres-rw.database.svc.cluster.local"' "$manifest"
    if grep -Fq 'holizuki-postgres-app' "$manifest"; then
        printf 'Application manifest references the retired database secret.\n' >&2
        exit 1
    fi
done

grep -Fq 'DB_DATABASE: "holizuki_production"' "$production_manifest"
grep -Fq 'DB_USERNAME: "holizuki_production"' "$production_manifest"
grep -Fq 'DB_DATABASE: "holizuki_staging"' "$staging_manifest"
grep -Fq 'DB_USERNAME: "holizuki_staging"' "$staging_manifest"

[[ "$(grep -c '^kind: Cluster$' "$platform_manifest")" -eq 1 ]]
[[ "$(grep -c '^kind: Database$' "$platform_manifest")" -eq 1 ]]
[[ "$(grep -c '^kind: ObjectStore$' "$platform_manifest")" -eq 1 ]]
[[ "$(grep -c '^kind: ScheduledBackup$' "$platform_manifest")" -eq 1 ]]
grep -Fq '  namespace: database' "$platform_manifest"
grep -Fq '      database: holizuki_production' "$platform_manifest"
grep -Fq '  name: holizuki_staging' "$platform_manifest"
grep -Fq '      shared_buffers: "128MB"' "$platform_manifest"
grep -Fq '      max_connections: "50"' "$platform_manifest"
grep -Fq '      - hostssl all holizuki_production all reject' "$platform_manifest"
grep -Fq '      - hostssl all holizuki_staging all reject' "$platform_manifest"
if grep -Fq 'namespace=~"production|staging",value="ready"' "$platform_manifest"; then
    printf 'Database alerts still target the retired per-environment clusters.\n' >&2
    exit 1
fi

printf 'Helm manifest contract tests passed.\n'
