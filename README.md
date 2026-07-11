# Holizuki

Holizuki is a Laravel 13 and Inertia React application. Local development uses
[Devbox](https://www.jetify.com/docs/devbox/installing-devbox/) to provide the
pinned PHP, Node.js, npm, PostgreSQL, SQLite, and Xdebug versions declared in
`devbox.json`.

Docker is not required for day-to-day development. The repository's Dockerfile
builds the minimal production image used by the release pipeline.

## First-time setup

Devbox supports macOS and Linux directly. On Windows, run it from WSL2 and keep
the repository in the WSL filesystem.

Install Devbox using its official installer, then restart the terminal if the
installer asks you to:

```bash
curl -fsSL https://get.jetify.com/devbox | bash
```

From the repository root, pin the repository's Devbox version and run the
canonical setup command:

```bash
export DEVBOX_USE_VERSION=0.17.5
devbox run setup
devbox run doctor
```

The setup command creates `.env`, installs the PHP and JavaScript dependencies,
generates the application key, initializes PostgreSQL, creates and migrates the
database, links public storage, installs the Git hooks, and builds the frontend.
It is safe to run again after pulling dependency or environment changes.

`composer setup` remains available as a compatibility alias when Composer is
already available through Devbox, but `devbox run setup` is the canonical entry
point for a fresh clone.

### Optional automatic activation

The committed `.envrc` integrates Devbox with
[direnv](https://www.jetify.com/docs/devbox/ide-configuration/direnv/). After
installing and enabling direnv in your shell, approve this repository once:

```bash
direnv allow
```

This automatically selects the pinned Devbox environment when entering the
repository. Without direnv, export `DEVBOX_USE_VERSION=0.17.5` in each new
terminal before running Devbox commands.

## Daily development

Start the complete development stack:

```bash
devbox run dev
```

This starts the project-local PostgreSQL service when needed, then runs:

- Laravel at <http://localhost:8000>
- The database queue listener
- Laravel Pail for application logs
- Vite with hot module replacement

Press `Ctrl+C` to stop the foreground development processes. PostgreSQL runs as
a background Devbox service; stop it when it is no longer needed:

```bash
devbox run services:stop
```

Useful verification commands:

```bash
devbox run doctor          # Toolchain, configuration, database, and migrations
devbox run test            # Backend Pest test suite
devbox run check           # Full backend and frontend quality suite
devbox run browser:install # Install Chromium once
devbox run test:browser    # Browser smoke tests
```

Tests use in-memory SQLite for isolation and speed. The running application uses
PostgreSQL 18, matching the production database family. Normal development
therefore catches database-specific behavior.

## Troubleshooting

If `doctor` reports a Homebrew, Volta, or system version instead of the pinned
version, a host PATH customization is shadowing Devbox. Start a clean terminal
or verify the isolated environment with:

```bash
devbox services start postgresql
devbox run --pure doctor
```

If PostgreSQL is uninitialized or migrations are missing, rerun:

```bash
devbox run setup
```

If a frontend change does not appear, ensure `devbox run dev` is still running.
For a production asset check, run `devbox run -- npm run build`.

## Production container validation

The production image contains no Composer, Node.js, development dependencies,
tests, or `.env`. It runs as a non-root user under FrankenPHP and expects its
database and secrets to be supplied by the deployment platform. It is therefore
not a replacement for the local Devbox workflow.

When changing the Dockerfile, entrypoint, PHP extensions, or release runtime,
validate the production contract locally with Docker:

```bash
docker build -t holizuki:local .
bash .github/scripts/test-container.sh holizuki:local
```

Deployment and recovery runbooks live in `docs/deployment`.
