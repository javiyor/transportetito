# Plan de Modificaciones — TransporteTito

## 1. Manifiestos Show — Pedidos multi-empresa

**Archivos:** `ManifiestoIngresoController.php` (show), `Show.vue`

**Cambios:**
- En `show()`, cargar todos los pedidos pendientes del sistema (sin filtrar por empresa_id)
- Sección "Pedidos de otras empresas" en la vista
- Columna: N° Hoja Ruta Original (nuevo campo `numero_hoja_ruta_origen`)
- Columna: Valor Declarado
- Si recepción = error → permitir corregir datos (bultos, palets, valor_declarado) y marcar como corregido
- Si corregido con observaciones → permitir adjuntar foto de los bultos
- Facturación: botón "Facturar" o "Devolver"

**Migración (completada):**
- `numero_hoja_ruta_origen` (varchar) en `pedidos`
- `foto_bultos` (text) en `pedidos`
- `recepcion_corregido_por_user_id` (FK→users) en `pedidos`
- `recepcion_corregido_at` (timestamp) en `pedidos`
- `recepcion_facturacion_estado` (varchar) y `recepcion_facturacion_observacion` (text) en `pedidos`

**Rutas nuevas:**
- `POST /operacion/manifiestos/{manifiesto}/pedidos/{pedido}/corregir`
- `POST /operacion/manifiestos/{manifiesto}/pedidos/{pedido}/foto-bultos`
- `POST /operacion/manifiestos/{manifiesto}/pedidos/{pedido}/marcar-facturacion`
- `POST /operacion/manifiestos/{manifiesto}/pedidos/{pedido}/asignar`

---

## 2. Facturación — Selección de empresa

**Archivos:** `Manifiestos/Show.vue`

**Cambio:**
- En el modal/bloque de facturación, si el pedido no tiene empresa asignada, mostrar selector de empresa antes de facturar
- Al seleccionar empresa, usar esa empresa para emitir el comprobante

---

## 3. Repartos/Hojas — Pedidos multi-empresa

**Archivos:** `HojaRutaIndexController.php`, `HojasIndex.vue`

**Cambios:**
- En el listado de hojas de ruta y al crear nuevas, mostrar/comprobar pedidos de todas las empresas pendientes de entrega
- Los items de hoja de ruta se vinculan a comprobante_id — los comprobantes son multi-empresa

---

## 4. Hoja de Ruta — Observaciones + Notificaciones

**Archivos:** `HojaRutaCerrarController`, `HojaRutaShowController`, `Facturas.vue`, `Hoja.vue`

**Migración (completada):**
- `observacion_reparto` (text) en `hojas_ruta`
- `notificaciones_enviadas` (json) en `hojas_ruta`

**Por implementar:**
- Al cerrar hoja de ruta, enviar email al destinatario (email_contacto del item o TerceroCuenta.email)
- Enviar WhatsApp via Meta Cloud API (usando WhatsAppClient)
- Mostrar observacion_reparto en la vista de la hoja

---

## 5. WhatsApp — Integración Meta Cloud API

**Archivos creados:**
- `config/whatsapp.php` — configuración (API URL, phone number ID, access token)
- `app/Services/WhatsApp/WhatsAppClient.php` — servicio con métodos sendText, sendDocument
- `AppServiceProvider.php` — binding singleton

**Migración (completada):**
- `whatsapp_numero` (varchar) en `tercero_cuentas`

**Config necesaria en .env:**
```
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_FROM_NUMBER=
```

---

## 6. Registrar Entrega — Estados + Formas de Pago

**Archivos:** `HojaRutaItemUpdateController`, `RepartidorController`, `Hoja.vue`, `Repartidor/Delivery.vue`

**Backend (completado):**
- `estado_entrega` acepta: pendiente, entregado, entregado_con_diferencia, no_entregado
- Nuevo campo: `forma_pago` (cuenta_corriente, efectivo, cheque, transferencia)
- Nuevo campo: `importe_cobrado` (decimal)
- Subida de `foto_comprobante_pago` (storage/public/comprobantes-pago)
- Subida de `foto_remito_firmado` (storage/public/remitos-firmados)

**Migración (completada):**
- `forma_pago` (varchar), `importe_cobrado` (decimal), `foto_comprobante_pago` (text), `foto_remito_firmado` (text) en `hoja_ruta_items`

**Frontend (pendiente):**
- Actualizar modal de entrega en Hoja.vue y Delivery.vue con:
  - Radio buttons: Entregado / Entregado con diferencia / No entregado
  - Select forma de pago
  - Campo importe cobrado
  - Upload foto comprobante
  - Upload foto remito firmado (para cobranzas-admin)

---

## 7. Cobranzas — Roles, Recibos, Pre-recibos

**Archivos:** `CuentaCorrienteShow.vue`, `CuentaCorrienteShowController.php`, `ReciboPrintController.php`, `AppLayout.vue`

**Por implementar:**
- Perfil cobrador: solo recibos (ocultar ajustes, NC, ND)
- Perfil cobranzas-admin: todo
- Pre-recibos con foto_remito_firmado para respaldar cobro
- Envío de recibo por email al confirmar (Mailable + PDF)
- Control hoja de reparto: resumen por hoja con totales cobrados

---

## 8. Tarifas — Globales + % Variable

**Archivos:** `TarifaRelacionAdminController`, `Admin/Tarifas/Index.vue`, `TarifaRelacion` model, `Manifiestos/Show.vue`

**Ya implementado:**
- `resolveTarifa()` en Show.vue usa DEFAULT_TARIFA si no hay match por (remitente, destinatario)
- En facturación, el override del % ya funciona sin re-escribir en BD

**Pendiente:**
- Al crear tarifa, permitir guardar con remitente/destinatario = null (tarifa global por defecto)
- Cuando se ingresa % en facturación y no existe tarifa, guardar como nueva tarifa

---

## 9. Consulta de Fletes

**Archivos a crear:** `FleteController`, `Operacion/Fletes/Index.vue`

**Por implementar:**
- Ruta: GET /operacion/fletes
- Tabla con todos los pedidos del sistema
- Columnas: ID, remitente, destinatario, origen, destino, bultos, palets, valor declarado, estado, fecha, manifiesto, hoja ruta, comprobante
- Filtros: remitente, destinatario, estado, fechas, empresa
- Exportable a CSV

---

## 10. Combustibles — Monto Fijo Mensual

**Archivos:** `PagoCuentaCombustibleIndexController`, `Compras/Combustibles/Index.vue`

**Completado:**
- Migración: `monto_fijo_mes` en `pago_cuenta_combustibles`
- Backend: validación + guardado
- Frontend: campo en formulario + columna en tabla

---

## 11. Órdenes de Pago — Cheques de Terceros y Propios

**Archivos:** `ProveedorOrdenPagoStoreController`, `ChequeController`, `Admin/Cheques/Index.vue`

**Por implementar:**
- En formulario de OP, nuevo campo "Medio de pago":
  - Transferencia (actual)
  - Cheque propio → crear registro en cheques con origen=propio
  - Cheque de tercero → selector de cheques en cartera (origen=tercero, estado=en_cartera)
- Al seleccionar cheque de tercero, listar cheques disponibles con saldo suficiente
- Al confirmar OP, actualizar estado del cheque a endosado

---

## Resumen de Migraciones (todas creadas)

| Archivo | Tabla | Nuevas columnas |
|---|---|---|
| `2026_06_12_000001` | `pedidos` | `numero_hoja_ruta_origen`, `foto_bultos`, `recepcion_corregido_por_user_id`, `recepcion_corregido_at`, `recepcion_facturacion_estado`, `recepcion_facturacion_observacion` |
| `2026_06_12_000002` | `hojas_ruta` | `observacion_reparto`, `notificaciones_enviadas` |
| `2026_06_12_000003` | `hoja_ruta_items` | `forma_pago`, `importe_cobrado`, `foto_comprobante_pago`, `foto_remito_firmado` |
| `2026_06_12_000004` | `tercero_cuentas` | `whatsapp_numero` |
| `2026_06_12_000005` | `pago_cuenta_combustibles` | `monto_fijo_mes` |

## Rutas Nuevas

| Método | Ruta | Controlador |
|---|---|---|
| POST | `/operacion/manifiestos/{manifiesto}/pedidos/{pedido}/corregir` | ManifiestoIngresoController::corregirPedido |
| POST | `/operacion/manifiestos/{manifiesto}/pedidos/{pedido}/foto-bultos` | ManifiestoIngresoController::adjuntarFotoBultos |
| POST | `/operacion/manifiestos/{manifiesto}/pedidos/{pedido}/marcar-facturacion` | ManifiestoIngresoController::marcarFacturacion |
| POST | `/operacion/manifiestos/{manifiesto}/pedidos/{pedido}/asignar` | ManifiestoIngresoController::asignarPedido |
| GET | `/admin/arca-diagnostic` | EmpresaAdminController::arcaDiagnostic |
