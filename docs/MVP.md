# MVP - Facturacion (mes 1)

Objetivo: emitir Factura Electronica ARCA (WSFE) desde el sistema con PV 0002, y soportar operacion minima (manifiesto/pedidos) + cuenta corriente basica.

Incluye:
- Multiempresa (arranca Hurt S.A.) + deposito + PV asociado.
- Maestro de terceros por CUIT con consulta Padron autenticado.
- Manifiesto de ingreso (camion completo) + pedidos por destinatario (bultos/palets, valor declarado, CR, paga origen/destino).
- Facturas A/B/FCE + CAE + PDF.
- NC/ND (asociadas y no asociadas; no asociadas requieren permiso + tercero + motivo/cuenta).
- Parametros por empresa con vigencia: % seguro, % comision CR, topes Consumidor Final.
- Cuenta corriente cliente/proveedor basica + recibos internos + rendiciones cobrador provisionales con confirmacion/ajuste admin.

Post-MVP:
- Guias de reparto + POD + entrega parcial + CR en calle.
- Compras + OCR + Libro IVA Digital.
- Cheques, tercerizados completo, taller/flota.
