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
- **DNS flaky de Docker**: `getaddrinfo for redis` (y postgres/minio) fallaba intermitente → errores 500. Solucionado con red dedicada (`ttnet`) + IPs fijas + `extra_hosts` para resolver por `/etc/hosts`. La vieja red `transportetito_appnet` no se borra (otra app, `organizing-web`, la comparte).
- **favicon.ico vacío (0 bytes)** en `laravel/public/favicon.ico`: regenerado favicon válido.
- **Flash messages invisibles**: `flash.success`/`flash.error` no se compartían a Inertia → mensajes ARCA invisibles (`015fdf0`).
- **Contabilización desbalanceada**: debe≠haber cuando subtotal/IVA=0; `tipoLabel` snake_case; `contabilidad:recontabilizar --force` borra asientos previos (`e7151ea`).
- **Plan de cuentas no colapsable**: `isExpanded` se evaluaba una sola vez en `setup` (`944ba21`).
- **Pantalla blanca carga directa**: faltaba `formatFecha` en `<script setup>` (`9aafb93`).
- **`flete_final` no importado**: al crear ítems desde presupuesto no se llevaba al importe (`4e50974`).
- **`valor_declarado` en 0**: comprobantes importados quedaban en 0 (`3009206`).
- **Fechas ISO en Libro Diario**: formato `T` y labels (`8a862ae`, `d51c224`).
- **Compensación OP total=0**: ya registra aplicaciones; cheque físico/echéq; borrado de OP anulada (`68107c3`).
- **CUITs con formato mixto**: normalizados para matching ARCA (`546b3b0`).
- **`openssl_*_free()` eliminadas**: no existen en PHP 8.0+ (`86727ae`).
- **Redis OOM**: agregado `mem_limit` 256m (`c610591`, `0eaf72d`); códigos de subcuentas corregidos (`b02917a`).
- **Endpoint chofer invokable roto**: `POST /repartidor/ubicacion` registrado como controlador invokable (`RepartoUbicacionController` sin `__invoke`) → pings de geolocalización devolvían 500. Corregido a `[..., 'store']` y método renombrado `update`→`store` (`3677539`).

### Features / changes

| Commit | Descripción |
|--------|-------------|
| `a65698e` | Compat chillerlan/php-qrcode (string outputType/eccLevel) |
| `072aa7d` | Logo empresa en navbar web y encabezado de impresiones |
| `3009206` | valor_declarado 0 en comprobantes importados |
| `f6868c8` | Mostrar importes en comprobantes importados sin detalle_facturacion |
| `8ceaafd` | Plan de cuentas jerárquico (árbol colapsable, filtro contabilizable) |
| `f0ba250` | Botón nueva cuenta abre modal correctamente |
| `80d6e6a` | Módulo contabilización automática + configuración por empresa |
| `98b4702` | Vistas contables: Libro Diario/Mayor/Balance + export CSV |
| `4280275` | Rutas libro-diario/libro-mayor usan `[Controller::class, 'index']` |
| `b48a6eb` | Mover Libro Diario/Mayor/Balance a submenu Contabilidad (mobile) |
| `29ce2f3` | Agregar Libro Diario/Mayor/Balance al dropdown Finanzas desktop |
| `8a862ae` | Fecha DD-MM-YYYY en Libro Diario; `factura_interna`→`factura` |
| `d51c224` | Fecha ISO con `T` en Libro Diario |
| `9d66aab` | Auto-corrección tipo comprobante según condición cliente + fixes contables |
| `d4c8888` | Logo jpeg; miles en saldo proveedores; cheques manual; unique plan cuentas |
| `da56caa` | Créditos en CC (OPs/recibos sin imputación); fix signo notas crédito |
| `f4adfa8` | Miles en cobranzas CC/comprobantes; términos legales en factura |
| `d88ca40` | Fecha en checkboxes; créditos con signo en CC; NC en OPs |
| `a35e0e5` | Cierre div faltante en listado cobranzas CC |
| `68ca31c` | Total siempre visible; eliminar recibo con botón en movimientos |
| `68107c3` | Compensación OP total=0 registra aplicaciones; cheque físico/echéq; borrar OP anulada; link recibos |
| `91382e1` | Command `cuentas:reparar-plan` (capítulos 1 y 2) |
| `944ba21` | Fix toggle expandir/colapsar plan de cuentas |
| `015fdf0` | Share flash.success/error a Inertia |
| `86727ae` | Eliminadas `openssl_*_free()` (PHP 8.0+) |
| `b3431bd` | Eliminar manifiesto; pago origen/destino en print; miles en create |
| `068eb29` | Carga directa de factura con pedidos (sin manifiesto) |
| `3ee39c6` | UI carga directa: dropdown cierra al seleccionar; tipo nombre completo; obs en pie; remito ancho; headers alineados |
| `8f36e6f` | Importe a facturar editable por fila; auto-calc desde valor declarado/IVA/seguro/CR |
| `57bfaca` | Seguro y CR editables por fila; separadores de miles |
| `100447f` | Rename 'Carga directa'→'Factura'; reorder menú (Manifiestos > Factura > Comprobantes) |
| `96bbbb1` | deposito_id null en pedidos carga directa → depósito central empresa |
| `0eaf72d` | mem_limit a redis (evita OOM) |
| `12cef4c` | Link +Nuevo en origen y destino de carga directa |
| `b02917a` | Corregidos códigos de subcuentas (Deudores Morosos, Cheques Diferidos) |
| `c610591` | Aumentar mem_limit redis 256m |
| `c952694` | Tabla comprobantes más compacta (text-xs, sin wrap) |
| `060a0fc` | Fix typo IVA en detalle de cotización pendiente |
| `4008801` | Filtro saldo distinto de 0 por defecto en cta cte proveedores |
| `546b3b0` | Normalizar CUITs de terceros (formato mixto) para matching ARCA |
| `5d3904a` | Controles por vehículo (RTO, matafuegos, etc.) con alertas de vencimiento |
| `9aafb93` | pantalla blanca carga directa - faltaba formatFecha |
| `f715169` | Mostrar presupuestos vigentes en carga directa (origen/destino coinciden) |
| `e7d3fdb` | Consultas con mismos datos que pendientes (remitente, CUIT, origen, items, valor declarado) |
| `707a360` | Mostrar remitente CUIT, origen, destinatario, items, valor declarado en pendientes cotizar |
| `7d2f34a` | Badge con cantidad de cotizaciones pendientes en menú |
| `be4c0f2` | Command `comprobantes:normalizar-tipos` (FM→factura_m, etc.) |
| `c087d37` | Nombre completo del tipo de comprobante en preview CSV |
| `be8e9ef` | Egreso cheque: propio (banco/número/importe/venc) o tercero (desde cartera) |
| `0867de2` | Cotizaciones, retenciones sums, egresos/ingresos multi-categoría, movimientos bancarios, proveedores cuenta contable |
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
| `9611ba0` | Campos de importe más anchos en carga directa (soporta hasta 100M) |
| `826cdc6` | Inputs de importe en carga directa usan v-model.number type=number (evita salto a decimales) |
| `82e6704` | Tabla comprobantes más compacta (text-xs, sin wrap) |
| `ac37a29` | Script de chequeo de salud del sitio (app vs edge del hosting) |
| `c4c3081` | Red dedicada `ttnet` + IPs fijas + extra_hosts (evita DNS flaky de Docker) |
| `0cba337` | Red dedicada `ttnet` + IPs fijas + extra_hosts (redirecciones/edge) |
| `fb6305a` | Permitir facturar manifiestos sin control de recepción; hoja de ruta solo para pedidos controlados y facturados |
| `c13986d` | Mapa de reparto en tiempo real (admin, Leaflet) con polling 5s de choferes activos |
| `3677539` | PWA instalable: manifest + service worker shell (precache vía /build/manifest.json), cache runtime tiles OSM + ubicaciones; icons + meta app.blade.php |

### Implemented

| Cambio | Descripción |
|--------|-------------|
| `CargaDirectaCreateController` | Nuevo controlador Inertia que renderiza form con cuentas, cotizaciones |
| `CargaDirectaStoreController` | Valida pedidos, crea terceros, tarifas, comprobante, pedidos, contabiliza, mail |
| `Facturacion/CargaDirecta/Create.vue` | Form con tabla dinámica de pedidos, buscador de cuentas, totales |
| `routes/web.php` | GET/POST `/facturacion/carga-directa` |
| `AppLayout.vue` | Link "Carga directa" en dropdown Facturación (desktop + mobile) |
| `ManifiestoFacturarController` / `ManifiestoEmitirGuiasController` | Ya no bloquean por control de recepción |
| `Facturacion/Manifiestos/Show.vue` | Facturar desbloqueado (todo pedido pendiente); avisos informativos |
| `Facturacion/ManifiestoIndexController` | Lista manifiestos con pedidos pendientes sin exigir control |
| `FacturasListController` | Hoja de ruta: solo comprobantes con todos los pedidos controlados |
| `HojaRutaStoreController` | Validación: rechaza comprobantes con pedidos sin controlar |
| `UserUbicacionUpdateController` | Toggle flag `envia_ubicacion` por usuario (admin) |
| `RepartoUbicacionController` | Endpoint choferes para recibir pings de geolocalización |
| `RepartoUbicacion` (modelo) | Tabla `reparto_ubicaciones` para seguimiento de repartos |
| `User.envia_ubicacion` | Flag booleano por usuario; consumido por Delivery.vue para iniciar tracking |
| `Delivery.vue` | Geolocalización habilitada sólo cuando el chofer tiene `envia_ubicacion` |
| `routes/web.php` | `PUT /admin/users/{user}/ubicacion` (`admin.users.ubicacion.update`) y `POST /repartidor/ubicacion` (`repartidor.ubicacion.store`) |
| `migraciones 2026_08_13` | `users.envia_ubicacion` (bool) y tabla `reparto_ubicaciones` (lat/lng/accuracy/hoja_ruta) |
| `RepartoMapController` | `index` (Inertia map) + `ubicaciones` (JSON) gated por `role:admin` |
| `Admin/Reparto/Map.vue` | Mapa Leaflet (CDN), polling 5s, markers con popup chofer/última posición/hoja ruta, listado con badge online |
| `AppLayout.vue` | Link "Mapa reparto" en dropdown Configuración (desktop + mobile), admin-only |
| `manifest.webmanifest` / `sw.js` / `pwa-*.png` | PWA: manifest (start_url `/admin/reparto/mapa`), service worker raíz (precache shell + cache tiles/OSM + ubicaciones), icons generados |
| `scripts/build_pwa_icons.php` | Genera icons PNG a partir de `brand/logo.png` (uso puntual) |

### Relevant files

**Backend:**
- `laravel/app/Http/Controllers/Operacion/Facturacion/ManifiestoFacturarController.php` — facturar sin bloquear por control de recepción
- `laravel/app/Http/Controllers/Operacion/Facturacion/ManifiestoEmitirGuiasController.php` — emitir guías sin bloquear por control de recepción
- `laravel/app/Http/Controllers/Operacion/Repartos/FacturasListController.php` — hoja de ruta filtra comprobantes controlados
- `laravel/app/Http/Controllers/Operacion/Repartos/HojaRutaStoreController.php` — valida comprobantes controlados al crear hoja
- `laravel/app/Http/Controllers/Admin/UserUbicacionUpdateController.php` — toggle `envia_ubicacion`
- `laravel/app/Http/Controllers/Operacion/Repartos/RepartoUbicacionController.php` — recibe pings de geolocalización
- `laravel/app/Models/RepartoUbicacion.php`
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
- `laravel/app/Services/Contabilidad/ContabilizadorService.php` — contabiliza venta/NC/compra/cobro/pago proveedor/gasto operativo
- `laravel/app/Console/Commands/Recontabilizar.php` — `contabilidad:recontabilizar` (`--force`)
- `laravel/app/Console/Commands/RepararPlanCuentas.php` — `cuentas:reparar-plan`
- `laravel/app/Console/Commands/NormalizarTiposComprobantes.php` — `comprobantes:normalizar-tipos`
- `laravel/app/Models/ConfiguracionContable.php` + helper `Empresa::getCuentaContable()`
- `laravel/app/Models/Cotizacion.php`
- `laravel/app/Models/GastoOperativo.php` / `IngresoOperativo.php` / `MovimientoBancario.php`
- `laravel/app/Http/Controllers/Finanzas/LibroDiarioController.php` — asientos paginados, filtros, totales Debe/Haber
- `laravel/app/Http/Controllers/Finanzas/LibroMayorController.php` — movimientos por cuenta + saldo
- `laravel/app/Http/Controllers/Finanzas/BalanceController.php` — sumas/saldos jerárquicos + export CSV
- `laravel/app/Http/Controllers/Finanzas/EgresoIndexController.php` — egreso cheque propio/tercero, multi-categoría
- `laravel/app/Http/Controllers/Finanzas/MovimientoBancarioIndexController.php`
- `laravel/app/Http/Controllers/Compras/IngresoOperativoIndexController.php`
- `laravel/app/Http/Controllers/Compras/ProveedorComprobanteIndexController.php`
- `laravel/app/Models/VehiculoControl.php` — tabla `vehiculo_controles` (RTO, matafuegos)
- `laravel/app/Http/Controllers/Admin/RepartoMapController.php` — mapa en tiempo real (admin)

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
- `laravel/resources/js/Pages/Finanzas/LibroDiario/Index.vue`
- `laravel/resources/js/Pages/Finanzas/LibroMayor/Index.vue`
- `laravel/resources/js/Pages/Finanzas/Balance/Index.vue`
- `laravel/resources/js/Pages/Finanzas/Egresos/Index.vue`
- `laravel/resources/js/Pages/Finanzas/MovimientosBancarios/Index.vue`
- `laravel/resources/js/Pages/Compras/Ingresos/Index.vue`
- `laravel/resources/js/Pages/Compras/Proveedores/Comprobantes/Index.vue`
- `laravel/resources/js/Pages/Facturacion/Cotizaciones/Pendientes.vue`
- `laravel/resources/js/Pages/Facturacion/Cotizaciones/Consultas.vue`
- `laravel/resources/js/Pages/Facturacion/Cotizaciones/PedidoCreate.vue`
- `laravel/resources/js/Pages/Admin/Reparto/Map.vue` — mapa tracking (Leaflet CDN, polling 5s)
- `laravel/public/sw.js`, `manifest.webmanifest`, `pwa-*.png`, `apple-touch-icon.png` — PWA (shell offline + runtime cache)
- `scripts/build_pwa_icons.php` — regenera icons a partir de `brand/logo.png`

**Routes:**
- `laravel/routes/web.php`
- Finanzas: GET `/finanzas/libro-diario`, `/finanzas/libro-mayor`, `/finanzas/balance`, `/finanzas/balance/export`, `/finanzas/egresos`, `/finanzas/movimientos-bancarios`
- Compras: `/compras/ingresos`, `/compras/proveedores/comprobantes`, `/proveedores/ordenes-pago`
- Cotizaciones: `/facturacion/cotizaciones/pendientes`, `/facturacion/cotizaciones/consultas`, `/facturacion/cotizaciones/pedido`
- Facturación: `facturacion.carga-directa` renombrado a "Factura" (`100447f`)
- Admin/Reparto: `PUT /admin/users/{user}/ubicacion` (`admin.users.ubicacion.update`), `POST /repartidor/ubicacion` (`repartidor.ubicacion.store`)
- Admin/Reparto mapa: `GET /admin/reparto/mapa` (`admin.reparto.mapa.index`), `GET /admin/reparto/ubicaciones.json` (`admin.reparto.ubicaciones.json`)

### Plan (COMPLETADO): Carga directa de factura con pedidos (Jul 2026)

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
- `envia_ubicacion` + pantalla de mapa implementados: `Delivery.vue` envía pings a `reparto_ubicaciones`; `/admin/reparto/mapa` (Leaflet, polling 5s) muestra choferes activos en tiempo real (admin‑only). Render de tiles depende de OSM (configurable vía `services.openstreetmap`).
- `docs/schema.md` actualizado con tablas nuevas (cotizaciones, gastos/ingresos_operativos, movimientos_bancarios, asientos_contables/configuracion_contable, proveedor_comprobantes/ordenes_pago, reparto_ubicaciones, vehiculo_controles).
- Contabilización de egresos/ingresos/movimientos bancarios implementada vía `ContabilizadorService`; falta UI de conciliación bancaria manual.

### Deploy notes
- El mapa de reparto (`admin/reparto/mapa` + choferes) **requiere aplicar las migraciones** `2026_08_13_*` (`users.envia_ubicacion` + tabla `reparto_ubicaciones`). Tras deploy: `php artisan migrate --force` (opcional `db:seed --force` para defaults de `configuracion_contable`).
- La app es PWA instalable: `manifest.webmanifest` + `public/sw.js` (precarga shell vía `/build/manifest.json`, cache runtime tiles OSM + `ubicaciones.json`). El SW se registra en prod desde `app.js`; purge caché del navegador (unregister SW) tras rebuild de assets.
- Frontend build es *gitignored* (`/public/build`): rebuildear con `docker compose run --rm node` y limpiar caché (`php artisan optimize:clear`).
