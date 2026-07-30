# LLM contributor guide

This guide is a repository map for coding agents. Mandatory project rules live
in [AGENTS.md](../AGENTS.md); this document supplements them with a fast,
evidence-first workflow.

## Read order

Before changing code:

1. Read `AGENTS.md` and activate every skill matching the requested domain.
2. Read [Known issues](known-issues.md) so an existing runtime/schema gap is
   not mistaken for a regression caused by the task.
3. Read the relevant section of [Architecture](architecture.md).
4. Read the relevant schema group in
   [Database ERD and data dictionary](database-erd.md) for persistence work.
5. Inspect sibling implementation and focused tests. Tests often state the
   contract more precisely than comments.
6. Use Laravel Boost `search-docs` before Laravel ecosystem changes and Boost
   schema/route tools before manual alternatives.

Never treat documentation as a substitute for reading affected code.

## Source priority

Use this order when evidence conflicts:

1. User request and repository `AGENTS.md` constraints.
2. Executable architecture/security tests and focused feature tests.
3. Current code, migrations, routes, configuration, and service-provider
   registrations.
4. The target environment's migrated schema and runtime state, interpreted as
   possible drift from source.
5. Documentation and comments.

Report disagreement instead of silently choosing whichever source is easiest.
Do not "repair" drift by editing an already-run migration.

## Task locator

| Task                                      | Start here                                                                        | Usually verify with                                                          |
| ----------------------------------------- | --------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| Post draft/autosave/conflict              | `Publishing/Actions/SavePostDraft.php`, admin post/autosave controllers           | `tests/Feature/Publishing/PostDraftLifecycleTest.php`, post management tests |
| Publish/schedule/unpublish/trash/feature  | `Publishing/Models/Post.php`, matching Publishing action/controller               | Publishing lifecycle tests and `routes/console.php` for scheduling           |
| Revisions/restore/media/source            | `Publishing/Actions`, models, admin scoped controllers                            | Matching `tests/Feature/Publishing/*` test                                   |
| Category/tag                              | `Domain/Taxonomy`, admin controller, public taxonomy controller                   | `tests/Feature/Taxonomy` and affected Reading tests                          |
| Public article/discovery/search/SEO       | `Domain/Reading`, `Http/Public`, `pages/public`                                   | `tests/Feature/Reading`, public component Vitest, browser reader tests       |
| Reader/admin auth, profile, 2FA, passkeys | `Domain/Identity`, `Http/Auth`, `Http/Settings`, `FortifyServiceProvider`         | `tests/Feature/Identity`                                                     |
| Comments/newsletter                       | `Domain/Community`, community routes/controllers/components                       | `tests/Feature/Community`, analytics community browser test                  |
| Contact inbox                             | `Domain/Inbox`, public contact and admin inbox controllers                        | `tests/Feature/Inbox`                                                        |
| Analytics collection/dashboard/insight    | `Domain/Analytics`, analytics controller/pages, `config/analytics.php`            | `tests/Feature/Analytics` and dashboard Vitest                               |
| Interactive AI proposal                   | Assistant turn actions/services, SSE controllers/trait, assistant hook/components | `tests/Feature/Assistant` and SSE/hook/component Vitest                      |
| Guided AI authoring                       | `Assistant/Jobs/RunAuthoringTask.php`, authoring actions/services/controllers     | Authoring artifact/task tests; also verify worker topology                   |
| Shared Inertia props/layout/errors        | `HandleInertiaRequests`, `AppServiceProvider`, `resources/js/app.tsx`             | Dashboard/public/error feature and browser tests                             |
| Route or frontend backend call            | route file, controller signature, generated Wayfinder consumer                    | route list, Wayfinder generation, TypeScript check                           |
| Database schema                           | migration, domain model/cast/factory, relevant query/action                       | focused SQLite test plus PostgreSQL-sensitive test where required            |
| Local/CI tooling                          | `devbox.json`, `devbox.d`, Composer/npm scripts, `.github/workflows`              | existing shell/workflow tests and `devbox run doctor/check`                  |
| Container/deployment/recovery             | Dockerfile, entrypoint, Helm chart, deployment scripts                            | existing container/Helm tests and deployment runbooks                        |

Paths under `app/Domain` in this table are relative to that directory.

## Change recipes

### Backend-only domain change

1. Locate the owning bounded context; do not place business logic in a
   controller.
2. Inspect sibling actions/models and their tests.
3. Keep state transitions on the aggregate model and orchestration in an
   action.
4. Use a request class for HTTP validation and a policy/Gate for authorization.
5. If another context must react, dispatch an immutable event and register a
   listener in the reacting/owning provider without calling its actions
   directly.
6. Add/update the narrow Pest test, format dirty PHP, and rerun the test.

### End-to-end Inertia change

1. Inspect the route, controller props, page type, page, and closest shared
   component.
2. Search version-specific Inertia/Wayfinder documentation.
3. Return an Inertia page for navigation or JSON for a standalone `useHttp`
   request, following the existing endpoint.
4. Import generated named routes/controller actions from `@/routes` or
   `@/actions`; never hard-code the URL.
5. Reuse shared UI primitives and semantic tokens before adding a component or
   color/spacing convention.
6. Test PHP behavior and colocated React behavior; run TypeScript and build
   checks proportional to the change.

### Migration or relationship change

1. Inspect the target database with Laravel Boost `database-schema`.
2. Read all migrations affecting the table and the model/cast/query consumers.
3. Create a new migration. Never rewrite a migration that may have run outside
   a disposable environment.
4. Define foreign-key deletion behavior and indexes deliberately.
5. Update casts, relations, factories, seeders, and focused tests.
6. Test SQLite and PostgreSQL when using JSON, full-text, locks, partial
   indexes, or driver-specific expressions.
7. Update `docs/database-erd.md`.

### Assistant change

Treat model output, persisted transcripts, and workspace files as untrusted:

- maintain size/time/tool/environment limits;
- do not expose application secrets to the Claude child process;
- preserve per-post and global leases;
- validate structured output before creating proposals/artifacts;
- never let `App\Domain\Assistant` call Publishing write paths;
- apply accepted work only through HTTP-layer review and
  `Publishing\Actions\SavePostDraft`;
- test with supplied fake runners/binaries, never a live paid process.

For queued guided authoring, verify the dedicated `assistant` connection,
queue, worker timeout, OAuth environment, storage, and locks together.

### Analytics change

- Keep external API calls behind the reporting/admin gateway contracts.
- Health-check configuration before sync.
- Preserve the global import lock and transaction around persistence.
- Store sanitized errors only.
- Keep the dashboard reading local aggregates rather than calling GA inline.
- Use fake gateways in tests and verify URL alias projection after publishing
  changes.

## Non-negotiable repository invariants

- `App\Domain` never depends on `App\Http`.
- `App\Support` is context-free.
- Identity is a leaf; Reading is read-only; no context depends on Assistant.
- Cross-context reactions use immutable events.
- Models are not under `App\Models`; factories explicitly target their model
  and policies use `#[UsePolicy]`.
- A post's draft is not the same data record as its published snapshot.
- Post mutations honor optimistic locking and revision creation.
- Reader and administrator account surfaces remain separate.
- Public URLs use named routes and frontend callers use Wayfinder.
- No dependency, top-level directory, destructive operation, or production
  mutation is introduced without the authority required by `AGENTS.md` and the
  user request.

Run `php artisan test --compact tests/ArchitectureTest.php` whenever a change
touches context imports or moves PHP classes.

## Efficient inspection

- Prefer Laravel Boost for application info, versioned docs, schema, database
  queries, and URLs.
- Prefer `php artisan route:list` and `config:show` for resolved Laravel state.
- Prefer symbol/usage search before opening large files.
- Use `rg`/`rg --files` for repository-wide discovery.
- Inspect generated Wayfinder output when debugging, but edit the PHP route or
  controller source, not `resources/js/actions` or `resources/js/routes`.
- Ignore stale logs and inspect only recent relevant entries.
- Do not use Tinker to create records. Use factories in tests; use read-only
  database queries for diagnostics.

## Completion checklist

Before handing a change back:

- [ ] The owning context and portal are correct.
- [ ] Existing components/actions/queries were reused where appropriate.
- [ ] Authorization, validation, locking, events, and deletion behavior were
      considered.
- [ ] A focused automated test proves the change.
- [ ] Dirty PHP was formatted with the project's Pint command.
- [ ] Affected TypeScript, Vitest, build, route, queue, schedule, or Helm checks
      were run where applicable.
- [ ] Architecture and database tests still enforce the intended boundary.
- [ ] Documentation and known-issue status were updated when a contract moved.
- [ ] `git diff` contains no unrelated user changes, generated route files,
      secrets, debug statements, or accidental dependency updates.
- [ ] The final handoff names files changed, checks run, and any remaining
      verified gap.
