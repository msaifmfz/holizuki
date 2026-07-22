# Holizuki single-server K3s setup

This runbook prepares one Ubuntu 24.04 x86-64 server for isolated production and staging application workloads. Both environments use one CloudNativePG cluster, with separate databases, login roles, passwords, and host-based access rules. The server remains one failure domain, so keep database, upload, and K3s backups off the server.

## 1. Before touching the server

- For this pre-launch configuration, use at least 4 vCPU, 8 GiB RAM, and 200 GiB SSD/NVMe. Move to 16 GiB before sustained traffic, memory-heavy jobs, or keeping staging online continuously.
- Point the production and staging DNS `A`/`AAAA` records at the server.
- Choose the two hosts, for example `app.example.com` and `staging.example.com`.
- Restrict SSH/22 to administrator and GitHub Actions egress where possible. Expose only 80 and 443 publicly. Do not expose the K3s API on 6443.
- Use the hostname `holizuki-01`. If a different hostname is required, update `deploy/server/k3s-config.yaml` and `nodeHostname` in the platform values together.
- Put `/srv/holizuki` on its own ext4 or XFS logical volume. The declared persistent volumes total 101 GiB: 50 GiB for PostgreSQL, 40 GiB for uploads, and 11 GiB for monitoring. A 200 GiB disk leaves room for Ubuntu, K3s/container images, snapshots, logs, and growth.

With staging stopped, the explicitly configured steady workload requests are about 1.9 GiB across production, PostgreSQL and its backup sidecar, monitoring, and cluster operators. Starting staging adds 416 MiB. The remaining RAM is reserved for K3s/Traefik, the OS page cache, rollouts, migration jobs, backups, and traffic bursts; do not size the server from Kubernetes requests alone.

K3s and host firewalls can conflict. Prefer the provider firewall. If UFW must remain enabled, follow the current K3s networking documentation and allow the pod and service CIDRs; do not blindly enable UFW after installing K3s.

## 2. Create users and storage

Run these commands as an administrator. Replace the SSH public key before continuing.

```bash
sudo hostnamectl set-hostname holizuki-01
sudo groupadd --system k3s-admin
sudo groupadd --system holizuki-deploy
sudo usermod --append --groups k3s-admin "$USER"
sudo adduser --disabled-password --gecos '' deploy-production
sudo adduser --disabled-password --gecos '' deploy-staging
sudo usermod --append --groups holizuki-deploy deploy-production
sudo usermod --append --groups holizuki-deploy deploy-staging

for account in deploy-production deploy-staging; do
  sudo install -d -m 0700 -o "$account" -g "$account" "/home/$account/.ssh"
  sudoedit "/home/$account/.ssh/authorized_keys"
  sudo chown "$account:$account" "/home/$account/.ssh/authorized_keys"
  sudo chmod 0600 "/home/$account/.ssh/authorized_keys"
done

sudo install -d -m 0750 -o root -g holizuki-deploy /srv/holizuki
sudo install -d -m 0700 -o 26 -g 26 /srv/holizuki/database/postgres
sudo install -d -m 0770 -o 10001 -g 10001 /srv/holizuki/production/uploads
sudo install -d -m 0770 -o 10001 -g 10001 /srv/holizuki/staging/uploads
sudo install -d -m 0700 -o root -g root /srv/holizuki/k3s
sudo install -d -m 0700 -o root -g root /srv/holizuki/k3s-snapshots
sudo install -d -m 0750 -o root -g holizuki-deploy \
  /srv/holizuki/config \
  /srv/holizuki/kubeconfig \
  /srv/holizuki/locks \
  /srv/holizuki/tmp
sudo install -d -m 0700 -o deploy-production -g deploy-production \
  /srv/holizuki/history/production \
  /srv/holizuki/tmp/production
sudo install -d -m 0700 -o deploy-staging -g deploy-staging \
  /srv/holizuki/history/staging \
  /srv/holizuki/tmp/staging
sudo install -m 0640 -o deploy-production -g holizuki-deploy /dev/null /srv/holizuki/locks/production.lock
sudo install -m 0640 -o deploy-staging -g holizuki-deploy /dev/null /srv/holizuki/locks/staging.lock
```

UID 26 is the PostgreSQL user in the pinned CloudNativePG operand image. UID 10001 is the non-root application user in this repository's image.

Log out and back in before continuing so your new `k3s-admin` group membership is active.

## 3. Install K3s

Use a trusted checkout of this repository on the server. Review the pinned versions in `deploy/platform/versions.env` before installing or upgrading anything.

```bash
sudo apt-get update
sudo apt-get install --yes apache2-utils ca-certificates curl jq openssl

sudo install -d -m 0755 /etc/rancher/k3s
sudo install -m 0644 deploy/server/k3s-config.yaml /etc/rancher/k3s/config.yaml

set -a
source deploy/platform/versions.env
set +a
curl --fail --location --silent --show-error https://get.k3s.io \
  | sudo INSTALL_K3S_VERSION="$K3S_VERSION" sh -

sudo systemctl enable --now k3s
sudo systemctl status k3s --no-pager
kubectl get nodes -o wide
```

Set `KUBECONFIG=/etc/rancher/k3s/k3s.yaml` in every administrator shell that runs Helm:

```bash
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
```

The supplied K3s configuration keeps containerd, images, and dynamically provisioned monitoring volumes under `/srv/holizuki/k3s`, uses a single-node embedded etcd datastore, encrypts Kubernetes secrets at rest, and takes compressed etcd snapshots every six hours. K3s includes Traefik, which handles both hosts on ports 80 and 443.

## 4. Install cluster operators

Install Helm on the server at the pinned version if it is not already present. Then install cert-manager, CloudNativePG, and the Barman Cloud backup plugin in this order.

```bash
set -a
source deploy/platform/versions.env
set +a

helm_archive="helm-$HELM_VERSION-linux-amd64.tar.gz"
curl --fail --location --silent --show-error \
  --output "/tmp/$helm_archive" \
  "https://get.helm.sh/$helm_archive"
helm_checksum="$(curl --fail --location --silent --show-error "https://get.helm.sh/$helm_archive.sha256sum" | awk '{print $1}')"
echo "$helm_checksum  /tmp/$helm_archive" | sha256sum --check
tar -xzf "/tmp/$helm_archive" -C /tmp
sudo install -o root -g root -m 0755 /tmp/linux-amd64/helm /usr/local/bin/helm
rm -rf "/tmp/$helm_archive" /tmp/linux-amd64
helm version --short

helm repo add jetstack https://charts.jetstack.io
helm repo add cloudnative-pg https://cloudnative-pg.github.io/charts
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm repo update

helm upgrade --install cert-manager jetstack/cert-manager \
  --namespace cert-manager \
  --create-namespace \
  --version "$CERT_MANAGER_VERSION" \
  --set crds.enabled=true \
  --values deploy/platform/cert-manager-values.yaml \
  --rollback-on-failure --wait --timeout 10m

helm upgrade --install cloudnative-pg cloudnative-pg/cloudnative-pg \
  --namespace cnpg-system \
  --create-namespace \
  --version "$CLOUDNATIVE_PG_CHART_VERSION" \
  --values deploy/platform/cloudnative-pg-values.yaml \
  --rollback-on-failure --wait --timeout 10m

helm upgrade --install plugin-barman-cloud cloudnative-pg/plugin-barman-cloud \
  --namespace cnpg-system \
  --version "$BARMAN_CLOUD_PLUGIN_CHART_VERSION" \
  --values deploy/platform/plugin-barman-cloud-values.yaml \
  --rollback-on-failure --wait --timeout 10m
```

Confirm the deployed operator images match the application versions pinned in `versions.env`.

## 5. Install the platform chart

Create `/srv/holizuki/config/platform-values.yaml`:

```yaml
nodeHostname: holizuki-01

clusterIssuer:
  email: operations@example.com

backup:
  enabled: false
```

Install the chart once with backups and Prometheus resources disabled. This creates the production, staging, and database namespaces; resource quotas; static volumes; the shared PostgreSQL cluster; the TLS issuer; and deployment RBAC.

```bash
sudo chown root:k3s-admin /srv/holizuki/config/platform-values.yaml
sudo chmod 0640 /srv/holizuki/config/platform-values.yaml

helm upgrade --install holizuki-platform deploy/helm/platform \
  --namespace default \
  --values /srv/holizuki/config/platform-values.yaml \
  --rollback-on-failure --wait --timeout 15m

kubectl get clusters.postgresql.cnpg.io --all-namespaces
```

The PostgreSQL cluster cannot initialize until the credentials in the next section exist. Helm does not place database passwords in its release values.

Production and staging share the PostgreSQL process and 50 GiB volume, but not a database or login role. Their upload claims and host paths remain separate. A PostgreSQL failure or restore affects both environments.

## 6. Create application secrets

Generate a different, permanent `APP_KEY` and database password for each environment. Store the source values in your password manager, not in Git, terminal history, or GitHub variables.

```bash
printf 'base64:%s\n' "$(openssl rand -base64 32)"
```

First create CloudNativePG basic-auth secrets in the shared database namespace. The files must use these exact usernames and independently generated passwords:

```text
username=holizuki_production
password=replace-with-production-database-password
```

```text
username=holizuki_staging
password=replace-with-staging-database-password
```

```bash
sudoedit /root/production-db.env
sudo kubectl -n database create secret generic holizuki-production-db \
  --type=kubernetes.io/basic-auth \
  --from-env-file=/root/production-db.env
sudo kubectl -n database label secret holizuki-production-db cnpg.io/reload=true
sudo shred --remove /root/production-db.env

sudoedit /root/staging-db.env
sudo kubectl -n database create secret generic holizuki-staging-db \
  --type=kubernetes.io/basic-auth \
  --from-env-file=/root/staging-db.env
sudo kubectl -n database label secret holizuki-staging-db cnpg.io/reload=true
sudo shred --remove /root/staging-db.env

kubectl wait --for=condition=Ready cluster/holizuki-postgres -n database --timeout=10m
kubectl wait --for=jsonpath='{.status.applied}'=true database/holizuki-staging -n database --timeout=5m
```

Then create each application runtime secret. `DB_PASSWORD` must duplicate the corresponding password above; this is necessary because Kubernetes secrets cannot be referenced across namespaces. Add the real mail provider values for production. Staging should use a non-delivering mail provider or `MAIL_MAILER=log`.

```text
APP_KEY=base64:replace-me
DB_PASSWORD=replace-with-the-matching-database-password
MAIL_MAILER=log
```

```bash
sudoedit /root/production-runtime.env
sudo kubectl -n production create secret generic holizuki-runtime \
  --from-env-file=/root/production-runtime.env
sudo shred --remove /root/production-runtime.env

sudoedit /root/staging-runtime.env
sudo kubectl -n staging create secret generic holizuki-runtime \
  --from-env-file=/root/staging-runtime.env
sudo shred --remove /root/staging-runtime.env
```

Environment variables are read when a pod starts. Rotate the CloudNativePG and matching runtime-secret password together. Then restart the single `holizuki-web` Deployment in that environment and wait for its rollout; its pod contains the web, worker, and scheduler containers.

Protect staging with Traefik basic authentication:

```bash
sudo sh -c 'htpasswd -nB staging-user > /root/staging-users'
sudo kubectl -n staging create secret generic holizuki-staging-basic-auth \
  --from-file=users=/root/staging-users
sudo shred --remove /root/staging-users
```

The image package should be made public in GitHub Container Registry. If it must remain private, create a `ghcr-pull` docker-registry secret in both namespaces and add this server override in each environment:

```bash
read -rsp 'GHCR read:packages token: ' GHCR_TOKEN
echo
for namespace in production staging; do
  kubectl -n "$namespace" create secret docker-registry ghcr-pull \
    --docker-server=ghcr.io \
    --docker-username=YOUR_GITHUB_USER \
    --docker-password="$GHCR_TOKEN"
done
unset GHCR_TOKEN
```

```yaml
image:
  pullSecrets:
    - ghcr-pull
```

## 7. Prepare the deployment account

Keep environment behavior in the versioned `values-production.yaml` and `values-staging.yaml` files shipped by CI. Server override files contain only server-specific settings:

```bash
printf '{}\n' | sudo tee /srv/holizuki/config/production-overrides.yaml >/dev/null
printf '{}\n' | sudo tee /srv/holizuki/config/staging-overrides.yaml >/dev/null
sudo chown deploy-production:holizuki-deploy /srv/holizuki/config/production-overrides.yaml
sudo chown deploy-staging:holizuki-deploy /srv/holizuki/config/staging-overrides.yaml
sudo chmod 0600 /srv/holizuki/config/*-overrides.yaml

sudo install -o root -g root -m 0755 \
  .github/scripts/deploy-release.sh \
  /usr/local/bin/holizuki-deploy
sudo install -o root -g root -m 0755 \
  .github/scripts/control-staging.sh \
  /usr/local/bin/holizuki-staging

sudo .github/scripts/create-deployer-kubeconfig.sh \
  production https://127.0.0.1:6443 \
  /srv/holizuki/kubeconfig/production.yaml
sudo .github/scripts/create-deployer-kubeconfig.sh \
  staging https://127.0.0.1:6443 \
  /srv/holizuki/kubeconfig/staging.yaml
sudo chown deploy-production:holizuki-deploy /srv/holizuki/kubeconfig/production.yaml
sudo chown deploy-staging:holizuki-deploy /srv/holizuki/kubeconfig/staging.yaml
sudo chmod 0600 /srv/holizuki/kubeconfig/*.yaml
```

These kubeconfigs use long-lived service-account tokens because the deployment runs locally over SSH. They are namespace-scoped, never sent to GitHub, and must be rotated after an SSH or server compromise. Verify the boundary:

```bash
sudo -u deploy-staging kubectl --kubeconfig /srv/holizuki/kubeconfig/staging.yaml auth can-i create deployments -n staging
sudo -u deploy-staging kubectl --kubeconfig /srv/holizuki/kubeconfig/staging.yaml auth can-i create deployments -n production
sudo -u deploy-staging test ! -r /srv/holizuki/kubeconfig/production.yaml
```

The expected answers are `yes` and `no`.

The staging control command uses the same namespace-scoped kubeconfig and lock as staging deployments:

```bash
sudo -u deploy-staging /usr/local/bin/holizuki-staging status
sudo -u deploy-staging /usr/local/bin/holizuki-staging start
sudo -u deploy-staging /usr/local/bin/holizuki-staging stop
```

## 8. Configure GitHub environments

Create protected GitHub environments named `staging` and `production`. Require a production reviewer, prevent self-approval, and disable administrator bypass where repository policy allows it. Set these values in each environment:

| Name | Production example | Staging example |
| --- | --- | --- |
| `APP_HOST` | `app.example.com` | `staging.example.com` |
| `DEPLOY_HOST` | server DNS or IP | same server |
| `DEPLOY_PORT` | `22` | `22` |
| `DEPLOY_URL` | `https://app.example.com` | `https://staging.example.com` |
| `DEPLOY_USER` | `deploy-production` | `deploy-staging` |

Add `DEPLOY_SSH_PRIVATE_KEY` and a verified `DEPLOY_KNOWN_HOSTS` entry as environment secrets. Verify the SSH host-key fingerprint through a separate channel before saving it. Optionally set repository variable `CONTAINER_PLATFORMS`; it defaults to `linux/amd64`.

Use the manual **Control staging** workflow to start, stop, or inspect staging. A release-candidate deployment always scales staging to one pod so it can run deployment verification. Stop it again after testing to release its 416 MiB steady memory request. The workflow shares the `deploy-staging` concurrency lock with releases, and a successful start verifies that the basic-auth-protected `/up` endpoint returns HTTP 401.

## 9. Monitoring

Create the Grafana credential secret and install the pinned monitoring stack. Grafana has no public ingress; access it with an SSH tunnel or `kubectl port-forward`.

```bash
export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
set -a
source deploy/platform/versions.env
set +a
kubectl create namespace monitoring
kubectl -n monitoring create secret generic holizuki-grafana-admin \
  --from-literal=admin-user=admin \
  --from-literal=admin-password='replace-with-a-long-random-password'

helm upgrade --install kube-prometheus-stack prometheus-community/kube-prometheus-stack \
  --namespace monitoring \
  --version "$KUBE_PROMETHEUS_STACK_VERSION" \
  --values deploy/monitoring/kube-prometheus-stack-values.yaml \
  --rollback-on-failure --wait --timeout 15m
```

Set `monitoring.enabled: true` and `postgres.monitoringEnabled: true` in `platform-values.yaml`, then upgrade the platform chart again. Configure Alertmanager receivers before launch.

The slim monitoring values allocate 11 GiB total and retain Prometheus data for seven days. Kubernetes cannot shrink existing PVCs; if the older, larger pre-launch monitoring claims already exist, retain their sizes or recreate only those disposable monitoring claims after confirming no metrics are needed.

## 10. Pre-launch checks

```bash
kubectl get pods --all-namespaces
kubectl get pv,pvc --all-namespaces
kubectl get clusterissuer
kubectl get clusters.postgresql.cnpg.io --all-namespaces
kubectl get databases.postgresql.cnpg.io -n database
sudo -u deploy-production test -x /usr/local/bin/holizuki-deploy
sudo -u deploy-staging test -x /usr/local/bin/holizuki-deploy
sudo -u deploy-staging test -x /usr/local/bin/holizuki-staging
```

Complete the backup setup and a staging restore drill in `backup-restore.md` before production receives real data.

This layout intentionally starts with a fresh database cluster. If the retired pre-launch per-environment clusters still exist, confirm they contain no required data before deleting their retained clusters, PVCs, PVs, and host directories. They are not migrated or removed automatically.
