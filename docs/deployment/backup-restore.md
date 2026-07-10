# Backup and restore runbook

Backups are incomplete until they are off the server, monitored, and restored successfully in a drill. Production and staging use different object-store prefixes.

Run the administrative commands with `KUBECONFIG=/etc/rancher/k3s/k3s.yaml`.

## PostgreSQL backups

Create an S3-compatible bucket with versioning and object lock/retention if the provider supports them. Use credentials limited to that bucket. Create the secret in both namespaces without committing it:

```bash
kubectl -n production create secret generic holizuki-backup-object-store \
  --from-literal=ACCESS_KEY_ID='replace-me' \
  --from-literal=ACCESS_SECRET_KEY='replace-me'
kubectl -n staging create secret generic holizuki-backup-object-store \
  --from-literal=ACCESS_KEY_ID='replace-me' \
  --from-literal=ACCESS_SECRET_KEY='replace-me'
```

Update `/srv/holizuki/config/platform-values.yaml`:

```yaml
backup:
  enabled: true
  endpointURL: https://s3.example.com
  destinationPath: s3://holizuki-backups/database
  schedule: "0 30 2 * * *"
  retentionPolicy: 30d
```

Apply and verify:

```bash
helm upgrade --install holizuki-platform deploy/helm/platform \
  --namespace default \
  --values /srv/holizuki/config/platform-values.yaml \
  --rollback-on-failure --wait --timeout 15m

kubectl get objectstores.barmancloud.cnpg.io,scheduledbackups.postgresql.cnpg.io --all-namespaces
backup_name="holizuki-manual-$(date -u +%Y%m%d%H%M%S)"
sed "s/BACKUP_NAME/$backup_name/" <<'YAML' | kubectl create -f -
apiVersion: postgresql.cnpg.io/v1
kind: Backup
metadata:
  name: BACKUP_NAME
  namespace: production
spec:
  cluster:
    name: holizuki-postgres
  method: plugin
  pluginConfiguration:
    name: barman-cloud.cloudnative-pg.io
YAML
kubectl get backups.postgresql.cnpg.io -n production --watch
```

The scheduled backup uses CloudNativePG's six-field cron syntax. WAL archiving provides point-in-time recovery between base backups. Alert on failed backups and a recoverability point that stops advancing.

## Upload and K3s backups

Back up these paths to a separate provider/account with an encrypted tool such as restic:

- `/srv/holizuki/production/uploads`
- `/srv/holizuki/staging/uploads` if staging uploads matter
- `/srv/holizuki/k3s-snapshots`
- an encrypted export of the runtime, backup, TLS, and deployment-token secrets
- `/srv/holizuki/config` and `/srv/holizuki/history`

Do not copy the live PostgreSQL data directory as a database backup. CloudNativePG base backups and WAL archives are the supported database recovery source.

Run off-server backups after the nightly database base backup, retain daily/weekly/monthly copies according to business requirements, and monitor the backup command's exit status. The K3s snapshots are created locally every six hours by the supplied K3s configuration; the off-server job must copy them before a disk failure can destroy them.

## Monthly restore drill

CloudNativePG recovery creates a new cluster; it does not overwrite the source cluster. Drill in a disposable namespace and directory, never over production.

1. Choose a completed production backup or point-in-time target.
2. Create a disposable namespace, a new local path/PV, the object-store credentials, and an `ObjectStore` pointing at the production backup prefix.
3. Create a new `Cluster` with a unique name and `bootstrap.recovery.source`.
4. Wait for recovery, connect read-only, and verify migrations, row counts, recent records, authentication data, and application-critical queries.
5. Record recovery point objective (RPO), recovery time objective (RTO), backup ID, target time, and evidence.
6. Delete only the disposable cluster/PVC/PV after the drill is signed off.

The recovery portion of the disposable `Cluster` follows this shape:

```yaml
bootstrap:
  recovery:
    source: production-backup
    # For PITR, uncomment and supply an approved UTC timestamp.
    # recoveryTarget:
    #   targetTime: "2026-07-10T02:00:00Z"
externalClusters:
  - name: production-backup
    plugin:
      name: barman-cloud.cloudnative-pg.io
      parameters:
        barmanObjectName: production-restore-object-store
        serverName: holizuki-postgres
```

Use a distinct `ObjectStore` name and a distinct destination for any new backups taken by the restored cluster. Never disable the empty-WAL-archive safety check during a routine drill. Refer to the CloudNativePG 1.29 recovery and Barman Cloud Plugin documentation before applying the full recovery manifest.

## Production recovery decision

During an incident, first stop writes by scaling the application web and worker deployments to zero. Preserve the failed volumes and Kubernetes resources for investigation. The incident owner must choose an approved recovery target and decide whether uploads also need a coordinated restore.

Recover to a new PostgreSQL cluster and validate it before changing the application's database host. Keep the old cluster untouched until business validation completes. Rotate database/application credentials after recovery if compromise is suspected.

For a total server loss:

1. Rebuild Ubuntu and K3s with `k3s-server.md`.
2. Restore only the K3s snapshot if its trust and consistency are known; otherwise reconstruct platform state from Git and your secrets vault.
3. Recreate local volumes and restore uploads.
4. Install cert-manager, CloudNativePG, and Barman.
5. Recover production PostgreSQL to a new cluster from object storage.
6. Deploy the last approved image digest from its release manifest.
7. Validate internally, then switch DNS/traffic.

Never declare recovery complete until login, database writes, queues, schedules, uploads, TLS, and an off-server backup of the recovered system have all been verified.
