# Esquema (borrador)

Nota: este documento define entidades y relaciones para migraciones Laravel.

## Core
- empresas
  - id
  - razon_social, cuit, condicion_iva
  - arca_pv_default (ej 2)
  - arca_env (homologacion/produccion)

- depositos
  - id, empresa_id
  - nombre, direccion
  - punto_venta_numero (ej 2)

- terceros
  - id
  - cuit (unique)
  - razon_social, condicion_iva, domicilio_fiscal (snapshot)

- tercero_empresa
  - id, empresa_id, tercero_id
  - flags: es_cliente, es_proveedor
  - parametros comerciales: zona_id, tarifario_id
  - % seguro override, % CR override (vigencia)
  - limite_credito (solo aviso)
  - alertas_mora (30 dias desde fecha factura)

## Operacion
- manifiestos_ingreso
  - id, empresa_id, deposito_id
  - transporte (texto), chofer, patente_camion, patente_acoplado
  - valor_asegurado, fecha, ciudad_origen, ciudad_destino, gastos_envio

- envios_consolidados
  - id, empresa_id, manifiesto_id

- pedidos
  - id, empresa_id, deposito_id, envio_consolidado_id
  - remitente_tercero_id, destinatario_tercero_id
  - paga (origen/destino)
  - remito_numero (opcional externo) + remito_interno_pv/nro
  - bultos, palets, valor_declarado
  - es_devolucion (bool)
  - cr_importe (nullable)
  - estado (en_deposito/en_reparto/...) + flags POD/CR confirmados (post-MVP)

## Comprobantes
- comprobantes
  - id, empresa_id, pedido_id (nullable para NC/ND no asociadas)
  - tipo (FA/FB/FCE/NCA/NCB/NDA/NDB)
  - pv, numero
  - fecha_emision
  - receptor_tercero_id
  - pagador_tercero_id
  - moneda (PES)
  - caenumero (CAE), caevto, arca_resultado
  - estado (borrador/emitido/cancelado)

- comprobante_items
  - id, comprobante_id
  - descripcion
  - cantidad
  - unidad_medida (generica)
  - precio_unitario
  - alicuota_iva
  - importe_iva

## Cuenta corriente
- cuentas_corrientes
  - id, empresa_id, tercero_id, tipo (cliente/proveedor)

- movimientos_cc
  - id, cuenta_corriente_id
  - fecha
  - tipo (factura/nc/nd/recibo/pago/ajuste)
  - referencia (comprobante/recibo/etc)
  - debe/haber
  - saldo_acumulado (opcional)

- recibos
  - id, empresa_id
  - pv, numero
  - tercero_id
  - fecha
  - total
  - estado (borrador/confirmado/anulado)

- recibo_aplicaciones
  - id, recibo_id, comprobante_id
  - importe_aplicado

## Rendiciones cobrador (provisional)
- rendiciones
  - id, empresa_id
  - usuario_responsable_id (cobrador)
  - usuario_registrador_id
  - fecha
  - estado (pendiente/confirmada/ajustada/rechazada)

- rendicion_items
  - id, rendicion_id
  - tercero_id
  - medio (efectivo/transfer/cheque)
  - importe
  - adjunto_key (S3)
  - impacto_cc_provisional (bool)

## Parametrizacion
- parametros
  - id, empresa_id
  - clave (seguro_pct_global, cr_pct_global, tope_cf_identificacion, ...)
  - valor (json)
  - vigencia_desde, vigencia_hasta

## Contabilidad
- cuentas_contables
  - id, empresa_id
  - codigo, codigo_completo, codigo_corto
  - nombre, tipo, naturaleza, nivel (capitulo/categoria/cuenta)
  - parent_id (auto-referencia) → árbol jerárquico colapsable
  - moneda, activo, contabilizable, orden
  - timestamps; unique (empresa_id, codigo)
- configuracion_contable
  - id, empresa_id, clave (ej. caja, deudores, iva_ars, ...), cuenta_contable_id
  - unique (empresa_id, clave); sembrado por `ConfiguracionContableSeeder`; acceso vía `Empresa::getCuentaContable()`
- asientos_contables
  - id, empresa_id, fecha, moneda (ARS), estado (confirmado), referencia_tipo/referencia_id, descripcion, timestamps
  - index (empresa_id, fecha); (referencia_tipo, referencia_id)
- asiento_lineas
  - id, asiento_id, cuenta_contable_id, tercero_cuenta_id (nullable), debe, haber, descripcion
  - generados por `ContabilizadorService`; `contabilidad:recontabilizar --force` los borra/rehace

## Cotizaciones
- cotizaciones
  - id, empresa_id, tercero_cuenta_id (remitente), tercero_destino_id (nullable)
  - estado (pedido/cotizada/consultada), origen, destino
  - items (json), flete_sugerido, flete_final, fecha_validez, observacion, creado_por_user_id
  - flujo: Pedido → Cotizar → Consulta; badge de pendientes en menú

## Finanzas
- gastos_operativos
  - id, empresa_id, fecha, categoria, moneda, cotizacion_ars, importe, forma_pago (efectivo/transferencia/cheque/tarjeta), banco_origen_id, cheque_id, fecha_pago, referencia, observacion, creado_por_user_id
  - distribución multi-categoría → gasto_operativo_categorias
- gasto_operativo_categorias
  - id, gasto_operativo_id, cuenta_contable_id, importe
- ingresos_operativos
  - id, empresa_id, fecha, cuenta_contable_id (nullable), categoria, medio, detalle (json), moneda, cotizacion_ars, importe, referencia, observacion, creado_por_user_id
- ingreso_operativo_categorias
  - id, ingreso_operativo_id, cuenta_contable_id, importe
- movimientos_bancarios
  - id, empresa_id, banco_id, fecha, tipo (ingreso/egreso/gasto_bancario), concepto, importe, moneda (ARS), referencia_tipo/referencia_id, contabilizado (bool), creado_por_user_id

## Proveedores / Cheques
- proveedor_comprobantes
  - id, empresa_id, tercero_cuenta_id, tipo, numero, estado (emitida), moneda, cotizacion_ars, subtotal/iva_total/tributos_total/total, fecha_emision, fecha_vencimiento, detalle (json), cuenta_contable_id, observacion, creado_por_user_id
- ordenes_pago
  - id, empresa_id, tercero_cuenta_id, numero_interno, estado (emitida), moneda, cotizacion_ars, total, fecha, medio, detalle (json), cheque_id (nullable), observacion, creado_por_user_id

## Reparto
- users.envia_ubicacion (bool, default false) — choferes que envían pings de geolocalización a `reparto_ubicaciones`
- reparto_ubicaciones
  - id, user_id, hoja_ruta_id (nullable), lat, lng, accuracy, created_at
- vehiculo_controles
  - id, vehiculo_id, tipo (RTO, matafuegos, etc.), fecha_vencimiento, observacion
  - index (vehiculo_id, fecha_vencimiento); alertas de vencimiento + badge en menú Vehículos

## Empresas
- (extensión) logo (string, nullable) — renderizado en navbar y encabezados de impresiones
