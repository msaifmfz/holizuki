# Known maintenance issues

This file records verified repository or connected-environment gaps that can
mislead maintainers. It is not a speculative backlog. Remove an entry only
after the fix and its runtime verification are complete, then update affected
architecture/database/operations documentation.

## Connected PostgreSQL differs from the portable migration schema

Status: open; observed in the connected development PostgreSQL database on
2026-07-30.

Impact: creating a revision through the current `PostRevisionSnapshotCast` can
attempt to write columns that do not exist in this migrated database. Revision
reference checks and cleanup depend on the projections. The database also does
not enforce the draft-slug uniqueness assumed by fresh installations.

Evidence:

- `database/migrations/2026_07_12_134357_create_post_revisions_table.php`
  defines non-null JSON columns `referenced_paths` and
  `referenced_media_ids`.
- `PostRevisionSnapshotCast::set()` writes both projections with every
  snapshot.
- The connected PostgreSQL `post_revisions` table does not contain either
  column even though `php artisan migrate:status` reports the creating
  migration as already run.
- `database/migrations/2026_07_25_143609_create_post_drafts_table.php`
  declares `post_drafts.slug` unique, while the connected PostgreSQL database
  has a non-unique index for that column.
- A fresh SQLite schema passes
  `tests/Feature/Publishing/PostRevisionTest.php`, including projection
  behavior, so the checked-in fresh-install path and the existing database
  differ.
- A fresh-migration comparison found the same 54 tables and 58 foreign keys in
  both databases. Aside from expected database-engine index representation,
  these two missing columns and the draft-slug uniqueness are the only
  detected logical drift.

Required remediation:

1. Inspect staging and production schemas before choosing scope.
2. Add a new corrective migration; do not edit either already-run creating
   migration.
3. Validate that draft slugs contain no duplicates before adding the unique
   constraint.
4. Backfill existing revision snapshots into the two projection columns before
   enforcing the current non-null contract.
5. Run draft, revision, and media-cleanup tests against PostgreSQL and verify
   schema parity in every environment.
6. Remove this entry after deployment verification.

## Guided authoring jobs are not consumed by the configured workers

Status: open; verified from local and Helm process definitions on 2026-07-30.

Impact: guided plan/research/draft/image authoring tasks can remain queued.
Even if the generic worker were pointed at the queue, its current 90-second
timeout is shorter than the assistant job's configured 360-second runtime, and
the Helm worker does not receive the Claude OAuth secret. Interactive
request-bound SSE assistant turns are a separate web-process path and are not
evidence that queued authoring works.

Evidence:

- `RunAuthoringTask` selects both connection and queue `assistant`.
- `config/queue.php` gives that connection a 480-second `retry_after`.
- the local registered `queue:listen` process uses only the default connection;
- `docker/entrypoint.sh worker` runs a generic default worker with
  `--timeout=90`;
- the Helm worker uses the generic environment helper, while only the web
  environment helper injects `CLAUDE_CODE_OAUTH_TOKEN`.

Required remediation:

1. Define a dedicated local and production assistant worker, or explicitly
   configure an equivalent worker that consumes the `assistant` connection and
   queue.
2. Set its timeout above the job timeout and below `retry_after`, with resource
   limits suitable for the Claude subprocess.
3. Inject only the dedicated assistant credential and required workspace
   storage into that worker.
4. Add a deployment/process contract test proving a queued guided task is
   consumed, authenticated, and can reach a terminal state.
5. Verify default jobs still use the short/default worker, then remove this
   entry.

## Production does not run the built Inertia SSR bundle

Status: open; verified from the image and Helm chart on 2026-07-30.

Impact: public pages are configured as SSR-eligible and the bundle is present
in the production image, but no production process listens at the configured
SSR URL. Public requests therefore cannot rely on production SSR. Authenticated
pages deliberately disable SSR and are unaffected by this intended distinction.

Evidence:

- `config/inertia.php` enables SSR and points to
  `http://127.0.0.1:13714`.
- the Docker build runs `npm run build:ssr` and copies `bootstrap/ssr`.
- `docker/entrypoint.sh` defines an `ssr` role.
- the Helm Deployment starts only `web`, `worker`, and `scheduler` containers;
  no process invokes the `ssr` role.
- the final PHP runtime image does not include Node, while
  `inertia:start-ssr` uses the `node` runtime by default. Invoking the currently
  unused role would therefore still fail without an image or runtime change.

Required remediation:

1. Decide whether production public SSR is an actual runtime requirement.
2. If yes, supply/configure the Node runtime, add a supervised SSR
   process/container, loopback or service connectivity, resources, probes,
   shutdown behavior, and a production contract test using
   `inertia:check-ssr`.
3. If no, disable SSR explicitly in production and stop presenting the SSR
   build as an active runtime capability.
4. Update architecture, operations, Helm, and release documentation, then
   remove this entry.

## Readiness and alerts do not cover background capabilities

Status: open; verified from readiness, deployment, container-test, and
monitoring definitions on 2026-07-30.

Impact: a release may pass `/up` and `/ready` while queue workers, schedules,
guided authoring, SSR, mail, or persistent storage are unavailable. A
living-but-stuck worker or scheduler has no current queue-depth, failed-job, or
schedule-freshness alert.

Evidence:

- `ReadinessController` performs only a database `select 1` and a default-cache
  round trip.
- container and deployment health checks use `/up` and `/ready`.
- current Prometheus rules cover deployment availability, migrations,
  database backups, and WAL health, but not queue/scheduler/Assistant/SSR
  progress.

Required remediation:

1. Define explicit service-level checks for queue consumption, scheduler
   freshness, storage writes, and every enabled external capability.
2. Add alerts for queue depth/age, failed jobs, and stale scheduled work.
3. Add Assistant and SSR checks only when those features are enabled.
4. Keep liveness checks cheap; use deployment verification and monitoring for
   deeper dependencies.
5. Update the release runbook and remove this entry after alert/test evidence.

## File maintenance mode is not cluster-wide in Helm

Status: open; verified from production values and volume topology on
2026-07-30.

Impact: production uses Laravel's file maintenance driver while
`storage/framework` is an `emptyDir` per pod. During a rolling deployment with
surge capacity, `php artisan down` affects only the pod/filesystem where it ran
and is not a reliable application-wide maintenance switch.

Required remediation:

1. Choose a shared maintenance mechanism or an ingress/replica procedure that
   stops all writes predictably.
2. Test it with multiple old/new pods during a rolling update.
3. Document the exact enter/exit and failure-recovery commands in the release
   runbook.
4. Until then, do not use `artisan down` as proof that production writes have
   stopped.
