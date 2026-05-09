# Agent Instructions (TransporteTito)

This repo is an infra-first Laravel MVP scaffold for ARCA/WSFE electronic invoicing.
The Laravel application lives in `laravel/` and is created by bootstrap.

## Quick Orientation

- Runtime: PHP 8.3 (FPM), Laravel (generated into `laravel/`), Postgres 16, Redis 7, MinIO, Nginx, Caddy.
- Entry points: `docker-compose.yml`, `scripts/bootstrap.sh`, `scripts/setup-auth.sh`, `docs/schema.md`.
- Current state: `laravel/` is a placeholder until you run bootstrap.

## Build / Lint / Test

All commands are intended to run from the repo root.
Prefer running commands inside containers to match production dependencies.

### Bootstrap / Start (Containers)

- First-time setup (creates `laravel/` if missing, then starts stack):
  - `bash scripts/bootstrap.sh`
- Start stack (after initial setup):
  - `docker compose up -d --build`
- Stop stack:
  - `docker compose down`

### PHP dependencies

- Install composer deps (inside app container):
  - `docker compose run --rm app composer install`
- Update deps:
  - `docker compose run --rm app composer update`

### Laravel app commands

- Generate app key:
  - `docker compose run --rm app php artisan key:generate`
- Migrations:
  - `docker compose run --rm app php artisan migrate`

- One-off Artisan:
  - `docker compose exec -T app php artisan <command>`

### Frontend build

The `node` service builds assets inside the `laravel/` volume.

- Build assets:
  - `docker compose run --rm node`

If you need a one-off Node command:
  - `docker compose run --rm node sh -lc "npm ci && npm run build"`

### Tests

Laravel tests are expected to be run via Artisan (PHPUnit under the hood).

- Run full test suite:
  - `docker compose exec -T app php artisan test`

- Run a single test file:
  - `docker compose exec -T app php artisan test tests/Feature/FooTest.php`

- Run a single test method (filter):
  - `docker compose exec -T app php artisan test --filter FooTest::test_it_does_x`

- Run a single test by name pattern:
  - `docker compose exec -T app php artisan test --filter "test_it_does_x"`

- Run tests in parallel (if set up in the generated app):
  - `docker compose exec -T app php artisan test --parallel`

If you need raw PHPUnit (when present): `docker compose exec -T app ./vendor/bin/phpunit --filter "pattern"`

### Lint / Format / Static Analysis

Linters/formatters may not be committed until `laravel/` exists. Once present:

- PHP formatting (Laravel Pint), if installed:
  - `docker compose exec -T app ./vendor/bin/pint`
  - Single file: `docker compose exec -T app ./vendor/bin/pint app/Models/Foo.php`

- Static analysis (PHPStan/Psalm), if installed:
  - `docker compose exec -T app ./vendor/bin/phpstan analyse`

Prefer: Pint for formatting, PHPStan for analysis, PHPUnit via `php artisan test`.

## Repo Conventions (Agents)

### General workflow

- Work in small, reviewable changes; keep diffs focused per feature/fix.
- Prefer containerized commands (`docker compose exec -T app ...`) for repeatability.
- Do not commit secrets. `.env` is local-only; `.env.example` is the template.
- Do not commit generated dirs: `laravel/vendor/`, `laravel/node_modules/`, `laravel/storage/`, `laravel/bootstrap/cache/`.

### VPS workflow (when applicable)

- Prefer git deploys; if you must sync the app folder from a VPS, exclude `.env`, `vendor/`, `node_modules/`, `storage/`.
- After deploy: `docker compose exec -T app php artisan migrate --force` and (if needed) `docker compose exec -T app php artisan db:seed --force`.

### PHP / Laravel style

- Formatting:
  - Follow PSR-12 + Laravel conventions; if Pint exists, it is the source of truth.
  - One class per file; keep files in their conventional directories.

- Imports:
  - Use explicit `use` imports; do not rely on fully-qualified names in-line.
  - Remove unused imports; group imports by vendor (Laravel/framework, third-party, app).
  - Prefer importing classes over importing functions/consts.

- Types:
  - Use scalar/union types and return types wherever practical.
  - Prefer DTOs/value objects for ARCA request/response payloads rather than loose arrays.

- Naming:
  - Classes: `StudlyCase`; methods/vars: `camelCase`; constants: `SCREAMING_SNAKE_CASE`.
  - Use Spanish domain terms only where the business domain requires it (e.g., `cuit`,
    `comprobante`, `manifiesto`); keep technical names in English.
  - DB:
    - Tables: `snake_case` plural (Laravel default).
    - Columns: `snake_case`; foreign keys: `<model>_id`.

- Error handling:
  - For domain/validation errors: throw typed exceptions; convert to HTTP responses in
    Laravel exception handler or a dedicated layer.
  - For external ARCA/WSAA/WSFE failures: include enough context (endpoint, request id,
    pv/numero/cuit) in logs, but never log private keys/certs or full tokens.

- Logging:
  - Log meaningful events with structured context arrays; never log secrets.

- Queues/Jobs:
  - Use queued jobs for slow/fragile IO; ensure idempotency.

### Frontend

If Jetstream/Inertia is installed, follow its patterns; avoid inline styles (use Tailwind).

### Database / Migrations

- Use `docs/schema.md` as the source document for the first migrations.
- Add constraints and indexes intentionally (unique CUIT, foreign keys, etc.).
- Prefer `bigint` ids (Laravel default) and timestamps.

### Infrastructure notes

- HTTP flow: Caddy (TLS) -> Nginx -> PHP-FPM (`app:9000`).
- Storage is S3-compatible via MinIO; env vars are in `.env.example`.

## Cursor / Copilot rules

No Cursor rules found in `.cursor/rules/` or `.cursorrules`.
No Copilot instructions found in `.github/copilot-instructions.md`.
