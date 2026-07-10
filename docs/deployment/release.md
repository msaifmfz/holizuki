# Release and deployment procedure

## Release contract

- A prerelease tag `vMAJOR.MINOR.PATCH-rc.NUMBER` deploys to staging.
- A stable tag `vMAJOR.MINOR.PATCH` deploys to production.
- CI builds an OCI image only for a release candidate. It pins the PHP base image, builds frontend assets, generates an SBOM/provenance, scans high and critical vulnerabilities, and pushes to GHCR.
- Production never rebuilds the image. It finds a successfully deployed release candidate with the same version and exact Git commit, then promotes that candidate's image digest.
- Helm deploys by `repository@sha256:digest`, not by a mutable image tag.
- Production and staging deployments have separate locks, namespaces, secrets, databases, uploads, resource quotas, ingress hosts, and PHP images.

## Test a different PHP version

1. Update only `PHP_BASE_IMAGE_STAGING` in `deploy/runtime-images.env` to the desired digest-pinned FrankenPHP image.
2. Confirm the application's Composer PHP constraint supports it.
3. Merge the change to `main`, tag a release candidate, and test it in staging.
4. Production continues running its existing digest while staging runs the candidate digest.
5. If accepted, create the stable tag on the exact candidate commit. The stable workflow promotes the tested digest without rebuilding.
6. After promotion, update `PHP_BASE_IMAGE_PRODUCTION` to document production's accepted base image.

Never use floating base tags such as `latest` in release builds.

## Create a staging candidate

```bash
git switch main
git pull --ff-only
candidate_commit="$(git rev-parse HEAD)"
git tag --sign v1.0.0-rc.1 "$candidate_commit"
git push origin v1.0.0-rc.1
gh release create v1.0.0-rc.1 --prerelease --verify-tag --generate-notes
```

The published prerelease starts the deployment workflow. Check:

```bash
kubectl --kubeconfig /srv/holizuki/kubeconfig/staging.yaml -n staging get deployments,pods,jobs
kubectl --kubeconfig /srv/holizuki/kubeconfig/staging.yaml -n staging get deployment holizuki-web \
  -o jsonpath='{.spec.template.spec.containers[0].image}{"\n"}'
```

Exercise login, queues, scheduled tasks, file uploads, email behavior, and database changes. Staging basic auth and `X-Robots-Tag` remain enabled. Use only synthetic staging data.

## Promote to production

Use the exact candidate commit. If any application or deployment file changes, publish another release candidate first.

```bash
candidate_commit="$(git rev-list -n 1 v1.0.0-rc.1)"
git tag --sign v1.0.0 "$candidate_commit"
git push origin v1.0.0
gh release create v1.0.0 --verify-tag --generate-notes
```

The production GitHub environment should require approval. The workflow fails closed unless it finds both a same-version candidate manifest and a successful staging-deployment marker whose commit and image digest match the stable tag.

## What a deployment does

1. CI runs the complete reusable quality/test workflow.
2. It builds a candidate or resolves the tested digest for a stable release.
3. It attests and transfers the chart, environment values, and release manifest with SHA-256 checksums.
4. The server validates every checksum and manifest field.
5. Helm runs the migration hook, then performs an atomic rolling upgrade.
6. Kubernetes startup, readiness, and liveness probes gate availability.
7. The server checks `/up` and `/ready` from inside the namespace and verifies the public ingress/TLS response.
8. A failed Helm upgrade rolls back automatically. A post-upgrade health failure rolls the application back to the previous Helm revision.
9. The deployed digest and timestamp are recorded under `/srv/holizuki/history/ENVIRONMENT`.

Database migrations are not automatically reversed. Every production migration must use an expand-and-contract sequence so the old and new application versions can run against the schema during rollout and rollback.

## Manual inspection and rollback

```bash
export KUBECONFIG=/srv/holizuki/kubeconfig/production.yaml
helm history holizuki -n production
helm status holizuki -n production
kubectl rollout status deployment/holizuki-web -n production --timeout=5m
```

To roll the application workloads back to a known Helm revision:

```bash
helm rollback holizuki REVISION \
  --namespace production \
  --cleanup-on-fail --wait --timeout 10m
```

Do not roll back across a destructive database migration. Restore a database only through the recovery process, with the incident owner approving the recovery point.

## PHP and platform upgrades

- Change staging first and publish an RC.
- Watch memory, CPU, error rate, queue failures, and PostgreSQL connections under realistic load.
- Promote only the same digest that passed staging.
- Upgrade K3s, cert-manager, CloudNativePG, Barman, PostgreSQL, and the monitoring stack separately from an application release.
- Review release notes, update `deploy/platform/versions.env`, validate charts, take fresh off-server backups, and change one platform component at a time.
