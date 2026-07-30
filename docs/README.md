# Repository documentation

This directory is the canonical entry point for Holizuki's maintainership
documentation. The application code, migrations, configuration, and executable
tests remain the source of truth; these documents explain how those pieces fit
together and where a change belongs.
Update these documents when necessary to align with the actual implementations.

## Documentation map

| Document                                            | Use it for                                                                          |
| --------------------------------------------------- | ----------------------------------------------------------------------------------- |
| [Architecture](architecture.md)                     | System boundaries, portals, domain dependencies, events, and critical request flows |
| [Database ERD and data dictionary](database-erd.md) | Tables, relationships, constraints, ownership, and retention behavior               |
| [Development and operations](development.md)        | Local setup, processes, scheduled work, verification, CI, and production handoffs   |
| [LLM contributor guide](llm-guide.md)               | Fast repository navigation and safe change checklists for coding agents             |
| [Known issues](known-issues.md)                     | Verified repository or environment gaps that still need remediation                 |
| [Release procedure](deployment/release.md)          | Immutable release-candidate and production promotion workflow                       |
| [Server setup](deployment/k3s-server.md)            | Single-server K3s, CloudNativePG, secrets, and platform setup                       |
| [Backup and restore](deployment/backup-restore.md)  | Database, upload, cluster backup, recovery, and drill procedures                    |

The root [README](../README.md) is the short human onboarding path.
[AGENTS.md](../AGENTS.md) contains mandatory instructions automatically loaded
by supported coding agents. [SECURITY.md](../SECURITY.md) defines the private
vulnerability-reporting path.

## Source-of-truth order

When documentation and implementation disagree, investigate in this order:

1. Executable boundaries and invariants in `tests/ArchitectureTest.php` and
   affected feature tests.
2. Current migrations, application code, route files, service providers, and
   configuration.
3. The migrated schema in the target environment. A difference from the
   checked-in migrations is schema drift, not an automatic reason to edit an
   already-run migration.
4. These documents, which describe intent and navigation but do not override
   executable behavior.

The connected development database has a verified drift item. Read
[Known issues](known-issues.md) before making persistence or deployment
changes.

## Keeping the documents current

Update documentation in the same change when any of these contracts move:

| Change                                                                               | Update                                                            |
| ------------------------------------------------------------------------------------ | ----------------------------------------------------------------- |
| Table, foreign key, index, model relationship, or retention rule                     | `database-erd.md`                                                 |
| Bounded context, allowed dependency, domain event, portal, or critical flow          | `architecture.md` and, when agent navigation changes, `AGENTS.md` |
| Setup script, environment group, process, queue, schedule, test gate, or CI workflow | `development.md`                                                  |
| Canonical file location or contributor workflow                                      | `llm-guide.md`                                                    |
| A listed gap is fixed or a new verified gap is discovered                            | `known-issues.md`                                                 |
| Deployment topology, promotion, rollback, or recovery behavior                       | the matching `docs/deployment/` runbook                           |

Do not copy volatile route lists or package patch versions into multiple files.
Prefer a stable map here and use `php artisan route:list`,
`mcp__laravel_boost.application_info`, `composer.lock`, and `package-lock.json`
for exact current output.
