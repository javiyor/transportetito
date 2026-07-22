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

## Session log (Jul 2026)

### Bugs found and fixed

- **Facturar/entrega cuenta nullable**: `facturar_cuenta_id` y `entrega_cuenta_id` eran NOT NULL pero los importers los mandaban como null.
- **Flash key mismatch**: Controllers usaban `flash.importResult`, HandleInertiaRequests leía `tt.import_result`. Unificado a `tt.import_result`.
- **Race condition `numero_interno`**: `max('numero_interno')` dentro del loop sin incrementar en memoria. Movido fuera del loop.
- **ARCA controller sin transaction**: Faltaba `DB::transaction()` y `entrega_cuenta_id`.
- **Moneda inválida en CSV**: Validación `in:ARS,USD,EUR,BRL` rechazaba "PES", "$". Se agregó mapeo frontend+backend.
- **Compras CSV no reconocía columnas Emisor**: HeaderMap solo tenía "Receptor".
- **Orden de variables en `<script setup>`** causaba white screen en terceros (empresaFiltroId usada antes de definir).
- **Pantalla blanca**: `empresaFiltroId` definido después de su uso en `useForm()`.

### Features / changes

| Commit | Descripción |
|--------|-------------|
| `5b46694` | Migración FKs nullables, ARCA/CSV controllers fixes |
| `327a9ec` | Normalización de moneda, error display en Importar |
| `90d9a40` | Compras import: columna Emisor, moneda, errores |
| `da23e46` | Toggle compartidos en manifiestos y comprobantes |
| `f122a7b` | Artisan command `empresa:trasladar-clientes` |
| `b0be8e7` | Condición IVA en Empresas (select desde ARCA) |
| `198fbb8` | Saldo pendiente en repartidor, menú dropdowns, estadísticas placeholder |
| `c69a9b7` | Clientes: filtro empresa, tabla compacta, CUIT más ancho |
| `a8bfd14` | Clientes: combo empresa en editar, buscador por nombre |
| `f02f741` | Fix white screen terceros (orden variables script setup) |
| `af37cbd` | Blanqueo Ventas/Compras en Configuración |
| `5869029` | Retenciones+multi factura en recibos, impuestos en ventas, resumen ARCA |

### Implemented

| Cambio | Descripción |
|--------|-------------|
| `CargaDirectaCreateController` | Nuevo controlador Inertia que renderiza form con cuentas, cotizaciones |
| `CargaDirectaStoreController` | Valida pedidos, crea terceros, tarifas, comprobante, pedidos, contabiliza, mail |
| `Facturacion/CargaDirecta/Create.vue` | Form con tabla dinámica de pedidos, buscador de cuentas, totales |
| `routes/web.php` | GET/POST `/facturacion/carga-directa` |
| `AppLayout.vue` | Link "Carga directa" en dropdown Facturación (desktop + mobile) |

### Relevant files

**Backend:**
- `laravel/app/Http/Controllers/Cobranzas/CuentaCorrienteReciboStoreController.php` — multi factura, retenciones, saldo a cuenta
- `laravel/app/Http/Controllers/Facturacion/ImportarFacturasCsvStoreController.php` — +impuestos
- `laravel/app/Http/Controllers/Facturacion/ImportarFacturasArcaStoreController.php` — +impuestos WSFE
- `laravel/app/Http/Controllers/Finanzas/ResumenArcaController.php` — libro IVA + dashboard
- `laravel/app/Http/Controllers/Compras/ImportarComprasCsvStoreController.php`
- `laravel/app/Http/Controllers/Operacion/ManifiestoIngresoController.php`
- `laravel/app/Http/Controllers/Operacion/Comprobantes/ComprobanteIndexController.php`
- `laravel/app/Http/Controllers/Admin/EmpresaAdminController.php`
- `laravel/app/Http/Controllers/Admin/EstadisticasController.php`
- `laravel/app/Http/Controllers/Admin/TerceroAdminController.php`
- `laravel/app/Http/Controllers/Admin/BlanqueoController.php`
- `laravel/app/Http/Controllers/Operacion/Repartos/RepartidorController.php`
- `laravel/app/Console/Commands/TrasladarClientesEmpresa.php`
- `laravel/app/Http/Middleware/HandleInertiaRequests.php`
- `laravel/app/Services/Arca/WsfeClient.php` — +IVA/tributos en consultarComprobante

**Frontend:**
- `laravel/resources/js/Layouts/AppLayout.vue` — menú reorganizado en dropdowns
- `laravel/resources/js/Pages/Cobranzas/CuentaCorriente/Show.vue` — recibo con retenciones + checkboxes
- `laravel/resources/js/Pages/Facturacion/Importar.vue` — +columnas impuestos en preview
- `laravel/resources/js/Pages/Operacion/Comprobantes/Show.vue` — desglose subtotal/IVA
- `laravel/resources/js/Pages/Finanzas/ResumenArca.vue` — libro IVA + dashboard
- `laravel/resources/js/Pages/Compras/Importar.vue`
- `laravel/resources/js/Pages/Operacion/Manifiestos/Index.vue`
- `laravel/resources/js/Pages/Operacion/Comprobantes/Index.vue`
- `laravel/resources/js/Pages/Admin/Empresas/Index.vue`
- `laravel/resources/js/Pages/Admin/Terceros/Index.vue`
- `laravel/resources/js/Pages/Admin/Reportes/Estadisticas.vue`
- `laravel/resources/js/Pages/Admin/Blanqueo/Index.vue`
- `laravel/resources/js/Pages/Operacion/Repartos/Repartidor/Delivery.vue`

**Routes:**
- `laravel/routes/web.php`

### Plan: Carga directa de factura con pedidos (Jul 2026)

**Objetivo**: Crear una factura con todos los datos de manifiesto/pedidos (remitente, destinatario, bultos, palets, valor declarado, etc.) directamente desde cero, sin importación externa ni manifiesto existente.

**Archivos nuevos:**
- `laravel/app/Http/Controllers/Facturacion/CargaDirectaCreateController.php` — renderiza el form
- `laravel/app/Http/Controllers/Facturacion/CargaDirectaStoreController.php` — procesa y crea comprobante + pedidos
- `laravel/resources/js/Pages/Facturacion/CargaDirecta/Create.vue` — formulario con tabla dinámica de pedidos

**Archivos modificados:**
- `laravel/routes/web.php` — GET/POST `/facturacion/carga-directa`
- `laravel/resources/js/Layouts/AppLayout.vue` — link "Carga directa" en menú Facturación

**Reutiliza:**
- `TarifaResolver` — resuelve tarifa por par (origen, destino)
- `FacturaCalculator` — calcula flete, seguro, comisión CR, IVA
- `ComprobanteMailer` — email al cliente
- `ContabilizadorService` — contabilización

**Formulario:**
- Header: **Origen** (remitente), **Destino** (destinatario/entrega), Checkbox "Facturar a destino"
- Tabla dinámica por item: Descripción, Cant, Tipo (bultos/palets), Valor declarado, CR, Remito, Obs.
- Cálculo automático con tarifas (flete, seguro, comisión CR, IVA, total)

**Lógica del store:**
1. Validar items (array dinámico)
2. Determinar facturar_cuenta_id según checkbox (origen o destino)
3. Resolver tarifa con TarifaResolver por par (origen, destino)
4. Calcular con FacturaCalculator
5. DB::transaction: Comprobante (detalle_facturacion con items[] + calculo) → Pedidos → Sync → CtaCteMovimiento → AuditLog
6. $mailer->enviarSiCorresponde
7. Redirect a facturacion.manifiestos.index

### Pending / Known issues

- Delivery.vue muestra `saldo_pendiente` pero falta verificar con datos reales.
- Al editar un tercero y cambiar empresa, el `numero_cliente` podría colisionar en la empresa destino.
