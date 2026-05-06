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
