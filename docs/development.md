# Development and operations guide

The root [README](../README.md) is the canonical first-clone procedure. This
document maps the ongoing processes, verification gates, and operational seams
maintainers need after setup.

## Toolchain and setup

Devbox pins PHP 8.5, Node 24, npm 11, PostgreSQL 18, SQLite, Composer, and PHP
extensions. Use the pinned environment rather than host-installed tools:

```bash
export DEVBOX_USE_VERSION=0.17.5
devbox run setup
devbox run doctor
```

`devbox run setup` creates `.env`, installs PHP and JavaScript dependencies,
generates the key, initializes/migrates PostgreSQL, links public storage,
installs hooks, and builds assets. It is designed to be rerunnable.

Tests default to in-memory SQLite. The running application uses the local
PostgreSQL service on port `54320`, matching the production database family.

Setup does not seed data or create an administrator. Create a verified
administrator interactively with:

```bash
php artisan user:create
```

`php artisan db:seed` is local fixture data, not an administrator-bootstrap
command. It creates the known `test@example.com` / `password` account and
upserts curated posts by slug; rerunning it can overwrite edits to those local
posts. Never seed production.

## Daily process topology

```bash
devbox run dev
```

starts the following registered processes:

| Process     | Command                                          | Responsibility                       |
| ----------- | ------------------------------------------------ | ------------------------------------ |
| `server`    | `php artisan serve --host=localhost --port=8000` | Laravel HTTP server                  |
| `scheduler` | `php artisan schedule:work`                      | Local scheduled commands             |
| `queue`     | `php artisan queue:listen --tries=1 --timeout=0` | Default database queue               |
| `logs`      | `php artisan pail --timeout=0`                   | Application log stream               |
| `vite`      | `npm run dev`                                    | Vite HMR and development SSR support |

Inspect the live definition with `php artisan dev:list`. Stop the background
PostgreSQL service with `devbox run services:stop`.

The registered queue process listens to the default queue only. Guided
authoring targets the dedicated `assistant` connection/queue and currently
needs a topology fix before it can run locally or in Helm; see
[Known issues](known-issues.md#guided-authoring-jobs-are-not-consumed-by-the-configured-workers).

## Configuration groups

`.env.example` documents safe local defaults. Application code reads
environment variables only through configuration files.

| Concern                                       | Primary configuration                   | Sensitive values                                                              |
| --------------------------------------------- | --------------------------------------- | ----------------------------------------------------------------------------- |
| Application/database/session/cache/queue/mail | `.env.example`, `config/*.php`          | `APP_KEY`, database password, mail credentials                                |
| Authentication/passkeys                       | `config/fortify.php`, `config/auth.php` | `PASSKEYS_USER_HANDLE_SECRET` (falls back to `APP_KEY`)                       |
| AI assistant                                  | `config/assistant.php`                  | `CLAUDE_CODE_OAUTH_TOKEN`; assistant home/workspaces contain private material |
| Analytics                                     | `config/analytics.php`                  | Base64 service account, property and stream identifiers                       |
| Community                                     | `config/community.php`                  | `COMMUNITY_EMAIL_HASH_KEY` (falls back to `APP_KEY`)                          |
| Trusted ingress                               | `config/deployment.php`                 | Correct `APP_URL` and `TRUSTED_HOSTS`                                         |
| Storage                                       | `config/filesystems.php`                | Object-store credentials when S3 is selected                                  |

Never commit runtime secrets or copy them into logs, assistant prompts,
fixtures, screenshots, or documentation. Production/staging secrets are
created out of band as described in
[Server setup](deployment/k3s-server.md).

Use dedicated stable `COMMUNITY_EMAIL_HASH_KEY` and
`PASSKEYS_USER_HANDLE_SECRET` values in deployed environments instead of their
`APP_KEY` fallbacks. `APP_PREVIOUS_KEYS` can preserve Laravel-encrypted values
during an application-key rotation, but it cannot preserve HMAC-derived
community lookups, reader-view hashes, or passkey user handles. Coordinate key
rotation as a data migration and explicitly restart the application Deployment
after changing a Secret.

## Change workflow

Start with the narrowest context and follow sibling conventions.

| Change                       | Required implementation seams                                                                       | Minimum focused verification                                                                                                  |
| ---------------------------- | --------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Domain/backend behavior      | Context model/action/query, HTTP request/controller if needed, factory, mirrored feature/unit test  | `php artisan test --compact path/to/test.php`                                                                                 |
| PHP file                     | Existing or updated Pest coverage                                                                   | `vendor/bin/pint --dirty --format agent`, then rerun the focused test                                                         |
| Migration/model relationship | Inspect live schema, add a new migration, update model casts/relations/factory and database doc     | Focused feature test on SQLite; add/run PostgreSQL-sensitive coverage when behavior differs                                   |
| Inertia page/form/navigation | Controller props, `resources/js/pages`, shared types/components, Wayfinder import, colocated Vitest | `npm run test -- path-or-pattern`, `npm run types:check`                                                                      |
| Route/controller signature   | Named route plus Wayfinder consumers                                                                | `php artisan wayfinder:generate --with-form --no-interaction`, `npm run types:check`                                          |
| Styling/component            | Existing CSS tokens and shared primitives before new abstractions                                   | Relevant Vitest; `npm run lint:check`; visual/browser test when layout or interaction changes                                 |
| Auth/role behavior           | Fortify provider/responses, Identity action, middleware/policy, reader/admin redirect tests         | Focused `tests/Feature/Identity/` test                                                                                        |
| Queue/schedule               | Job/command, retry/timeout/lock behavior, worker topology, failure path                             | Focused feature test plus `php artisan schedule:list` or queue fake assertions                                                |
| Deployment/runtime           | Dockerfile, entrypoint, Helm values/template, workflow contract, runbook                            | Existing shell/Helm checks and container test                                                                                 |
| Documentation only           | Update links and diagrams with implementation evidence                                              | Explicit Markdown Prettier check plus a one-off relative-link resolution check; normal npm formatting does not cover Markdown |

Do not create a model/controller/policy with the default `make:*` target: models
and HTTP classes live in domain/portal namespaces rather than `App\Models` and
the default controller tree. Follow a sibling manually. Generic PHP classes may
use `php artisan make:class --no-interaction` when the generated path is
correct.

## Verification commands

### Fast feedback

```bash
php artisan test --compact tests/Feature/Publishing/PostManagementTest.php
npm run test -- resources/js/lib/post-editor.test.ts
npm run types:check
vendor/bin/pint --dirty --format agent
```

### Normal repository gates

```bash
devbox run test
devbox run check
devbox run browser:install
devbox run test:browser
```

`devbox run check` resolves to `composer ci:check`, which runs PHP formatting
checks, Larastan, Rector dry-run, dependency analysis, 100% type coverage,
backend tests, frontend formatting/lint/types/dead-code/Vitest checks, and a
production frontend build.

Additional targeted commands:

```bash
composer test:coverage
composer test:mutation
npm run test:coverage
npm run build
npm run build:ssr
php artisan route:list --except-vendor
php artisan schedule:list
npx prettier --check path/to/changed.md
```

Backend coverage must remain at least 90%. The weekly deep-quality workflow runs
mutation testing with a 30% minimum and frontend coverage. Browser tests use
Pest Browser/Chromium and live under `tests/Browser`.

CI also repeats persistence-sensitive Reading, Publishing, Analytics, and
Community tests against PostgreSQL 18. A SQLite pass is not sufficient proof
for full-text search, JSON behavior, database locking, or PostgreSQL-specific
indexes.

## Scheduled work

All recurring tasks use overlap protection and one-server locks where
applicable. The application and Devbox toolchain use UTC, so the schedule times
below are UTC.

| Cadence          | Work                                                                                                                                                |
| ---------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Every minute     | Publish due scheduled post revisions                                                                                                                |
| Every four hours | Refresh recent Google Analytics data                                                                                                                |
| Daily            | Prune post views, unused media, community data, eligible Assistant turns/sessions/workspaces/transcripts, and expired analytics operational records |
| 02:15 daily      | Refresh the wider daily analytics window                                                                                                            |
| 03:00 daily      | Generate analytics insights                                                                                                                         |
| 03:10 daily      | Evaluate analytics milestones                                                                                                                       |

Read the executable schedule in `routes/console.php`. Production runs
`schedule:work` in the scheduler container, so every new scheduled task must be
safe during rolling deployment and must use a shared lock when duplicate work
would be harmful.

## Queues and long-running work

The default `database` connection has a 90-second `retry_after` by default;
Helm sets it to 120 seconds while its generic worker keeps a 90-second timeout.
The separate `assistant` database connection has a 480-second `retry_after`
because `RunAuthoringTask` may run for 360 seconds. A worker's timeout must stay
below its connection's `retry_after` and above the job's intended runtime.

Analytics month backfills and custom snapshot preparation also dispatch jobs.
Every job must define or inherit a clear failure path, be idempotent or
deduplicated, and be covered by a test that does not require a real external
API or Claude process.

## Domain-specific operational commands

```bash
php artisan user:create
php artisan assistant:doctor --no-interaction
php artisan assistant:prune --no-interaction
php artisan analytics:health --no-interaction
php artisan analytics:reconcile --no-interaction
php artisan analytics:sync-recent --no-interaction
php artisan analytics:backfill --help
php artisan posts:rebuild-metadata --help
php artisan posts:prune-media --no-interaction
php artisan community:prune --no-interaction
```

`assistant:doctor` validates the server-side Claude binary/version and OAuth
authentication. `analytics:health` is the non-mutating prerequisite for a
sync; `analytics:reconcile` is the explicit configuration-mutating operation.

## CI and release boundaries

The primary CI workflow verifies:

- reproducible Devbox setup and doctor checks;
- PHP quality, coverage, and tests;
- selected PostgreSQL persistence behavior;
- frontend production SSR build and full checks;
- browser smoke tests;
- shell/action workflow integrity;
- Helm linting, rendered contract tests, and Kubernetes schema validation.

Security workflows run dependency review/audits, CodeQL, Gitleaks, Trivy,
Zizmor, and OpenSSF Scorecard checks. Keep actions digest-pinned.

Production images are non-root FrankenPHP images without development
dependencies, Composer, Node, tests, or `.env`. Release candidates are built
once and deployed to staging; a stable release promotes the exact tested image
digest. Start with [Release procedure](deployment/release.md) and never improvise
a production migration rollback.

When the Docker/runtime contract changes, use the existing container check:

```bash
docker build -t holizuki:local .
bash .github/scripts/test-container.sh holizuki:local
```

Production currently builds but does not run the public SSR bundle. Do not
claim SSR is active until
[the known issue](known-issues.md#production-does-not-run-the-built-inertia-ssr-bundle)
is resolved and probed.

## Health and observability limits

`/up` proves that the web process can answer. `/ready` performs a database
`select 1` and a default-cache round trip. These probes do not validate the
queue, scheduler, assistant/Claude, SSR, mail, persistent storage, or analytics
API. The current Prometheus rules cover deployment availability, migrations,
database backup, and WAL health, but do not alert on queue depth/failed jobs,
stuck schedules, guided-authoring progress, or SSR.

Use capability-specific checks during deployment and incident response. The
probe/alerting blind spot is tracked in
[Known issues](known-issues.md#readiness-and-alerts-do-not-cover-background-capabilities).

Production uses file-based maintenance mode while framework storage is
pod-local. Do not rely on `php artisan down` as a cluster-wide switch; follow
the tracked
[maintenance-mode issue](known-issues.md#file-maintenance-mode-is-not-cluster-wide-in-helm).

## Troubleshooting map

| Symptom                                      | First checks                                                                                                          |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Environment/tool version mismatch            | `devbox run doctor`; check that host PATH tools are not shadowing Devbox                                              |
| PostgreSQL unavailable or migrations pending | `devbox run setup`; `php artisan migrate:status --pending`                                                            |
| Frontend change not visible                  | Ensure `devbox run dev` is active; for production assets run `npm run build`                                          |
| Wayfinder import missing/stale               | `php artisan route:clear`; regenerate Wayfinder; rerun TypeScript check                                               |
| Assistant unavailable                        | `php artisan assistant:doctor --no-interaction`; inspect recent logs and configured token/version                     |
| Guided authoring remains queued              | Check for an assistant-queue worker; see the known worker-topology issue                                              |
| Analytics dashboard stale                    | Check feature flags, `analytics:health`, last sync run, scheduled worker, and sanitized logs                          |
| Public data stale                            | Check Publishing/Taxonomy/Identity events and `Reading\Listeners\FlushPublicCache`                                    |
| Production health failure                    | Remember that `/ready` covers DB/cache only; use capability checks plus Helm/Kubernetes inspection and rollback steps |
| Data-loss incident                           | Stop writes and follow the backup/restore runbook; do not copy the live PostgreSQL directory                          |
