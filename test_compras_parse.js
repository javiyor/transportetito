const headerMap = {
    'nro doc emisor': 'proveedor_cuit',
    'denominacion emisor': 'proveedor_razon_social',
    'tipo de comprobante': 'tipo',
    'tipo comprobante': 'tipo',
    'numero desde': 'numero',
    'nro desde': 'numero',
    'punto de venta': 'pv',
    'punto venta': 'pv',
    'fecha de emision': 'fecha_emision',
    'fecha emision': 'fecha_emision',
    'fecha': 'fecha_emision',
    'imp total': 'total',
    'importe total': 'total',
    'total': 'total',
    'moneda': 'moneda',
    'tipo cambio': 'tipo_cambio',
    'cod autorizacion': 'arca_cae',
    'codigo de autorizacion': 'arca_cae',
    'imp neto gravado total': 'neto_total',
    'imp neto no gravado': 'neto_no_gravado',
    'imp op exentas': 'op_exentas',
    'otros tributos': 'tributos_total',
    'imp neto gravado iva 0': 'neto_iva_0',
    'iva 2 5': 'iva_2_5',
    'imp neto gravado iva 2 5': 'neto_iva_2_5',
    'iva 5': 'iva_5',
    'imp neto gravado iva 5': 'neto_iva_5',
    'iva 10 5': 'iva_10_5',
    'imp neto gravado iva 10 5': 'neto_iva_10_5',
    'iva 21': 'iva_21',
    'imp neto gravado iva 21': 'neto_iva_21',
    'iva 27': 'iva_27',
    'imp neto gravado iva 27': 'neto_iva_27',
};
const normalizeKey = (s) => String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ').trim();
const csvHeader = `"Fecha de Emisión";"Tipo de Comprobante";"Punto de Venta";"Número Desde";"Número Hasta";"Cód. Autorización";"Tipo Doc. Emisor";"Nro. Doc. Emisor";"Denominación Emisor";"Tipo Doc. Receptor";"Nro. Doc. Receptor";"Tipo Cambio";"Moneda";"Imp. Neto Gravado IVA 0%";"IVA 2,5%";"Imp. Neto Gravado IVA 2,5%";"IVA 5%";"Imp. Neto Gravado IVA 5%";"IVA 10,5%";"Imp. Neto Gravado IVA 10,5%";"IVA 21%";"Imp. Neto Gravado IVA 21%";"IVA 27%";"Imp. Neto Gravado IVA 27%";"Imp. Neto Gravado Total";"Imp. Neto No Gravado";"Imp. Op. Exentas";"Otros Tributos";"Total IVA";"Imp. Total"`;
const cleanHeader = (h) => h.replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim();
const rawHeaders = csvHeader.split(';').map(cleanHeader);
console.log('Raw headers:', rawHeaders);
const mapped = rawHeaders.map((h) => {
    const key = normalizeKey(h);
    return headerMap[key] || null;
});
console.log('Mapped:', mapped);
console.log('Required check:', ['proveedor_cuit', 'proveedor_razon_social', 'fecha_emision', 'total'].filter(r => !mapped.includes(r)));
