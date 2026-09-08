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
    'total iva': 'iva_total',
    'iva total': 'iva_total',
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
const parseArNumber = (v) => {
    if (v == null || String(v).trim() === '') return null;
    let s = String(v).trim().replace(/\s/g, '').replace(/\$/g, '');
    if (s.includes(',')) {
        s = s.replace(/\./g, '').replace(',', '.');
    }
    const n = parseFloat(s);
    return isNaN(n) ? null : n;
};
const parseArDate = (v) => {
    if (!v) return '';
    const s = String(v).trim();
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
    const m = s.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/);
    if (m) {
        const dd = m[1].padStart(2, '0');
        const mm = m[2].padStart(2, '0');
        const yyyy = m[3];
        return `${yyyy}-${mm}-${dd}`;
    }
    return s;
};

const csvLine = `2026-07-07;1;11;7474;7474;86272815909030;80;30709024902;TRANSFENOR SRL;80;30719333350;1,00;$;;;;;;;;334663,99;1593638,05;;;1593638,05;0,00;0,00;291697,96;334663,99;2220000,00`;
const csvHeader = `"Fecha de Emisión";"Tipo de Comprobante";"Punto de Venta";"Número Desde";"Número Hasta";"Cód. Autorización";"Tipo Doc. Emisor";"Nro. Doc. Emisor";"Denominación Emisor";"Tipo Doc. Receptor";"Nro. Doc. Receptor";"Tipo Cambio";"Moneda";"Imp. Neto Gravado IVA 0%";"IVA 2,5%";"Imp. Neto Gravado IVA 2,5%";"IVA 5%";"Imp. Neto Gravado IVA 5%";"IVA 10,5%";"Imp. Neto Gravado IVA 10,5%";"IVA 21%";"Imp. Neto Gravado IVA 21%";"IVA 27%";"Imp. Neto Gravado IVA 27%";"Imp. Neto Gravado Total";"Imp. Neto No Gravado";"Imp. Op. Exentas";"Otros Tributos";"Total IVA";"Imp. Total"`;
const cleanHeader = (h) => h.replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim();
const rawHeaders = csvHeader.split(';').map(cleanHeader);
const mapped = rawHeaders.map((h) => {
    const key = normalizeKey(h);
    return headerMap[key] || null;
});
console.log('Mapped headers with legacy map:', mapped);

const vals = csvLine.split(';').map((v) => v.replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim());
const row = {};
mapped.forEach((field, i) => { if (field) row[field] = vals[i] || ''; });
console.log('Row parsed:', row);
console.log('proveedor_cuit:', row.proveedor_cuit, 'razon:', row.proveedor_razon_social, 'fecha:', row.fecha_emision, 'total:', row.total, 'moneda:', row.moneda, 'pv:', row.pv, 'numero:', row.numero, 'tipo:', row.tipo);

// Simulate full parse like frontend does for subtotal/iva
let r = {...row};
let total = parseArNumber(r.total) || 0;
let subtotal = parseArNumber(r.subtotal);
let iva_total = parseArNumber(r.iva_total);
let tributos_total = parseArNumber(r.tributos_total);
const netoTotal = parseArNumber(r.neto_total);
const netoNoGrav = parseArNumber(r.neto_no_gravado) || 0;
const opEx = parseArNumber(r.op_exentas) || 0;
const perNeto = ['neto_iva_0','neto_iva_2_5','neto_iva_5','neto_iva_10_5','neto_iva_21','neto_iva_27'].reduce((s,k) => s + (parseArNumber(r[k]) || 0), 0);
const perIva = ['iva_2_5','iva_5','iva_10_5','iva_21','iva_27'].reduce((s,k) => s + (parseArNumber(r[k]) || 0), 0);
console.log('perNeto', perNeto, 'perIva', perIva, 'netoTotal', netoTotal);
if (subtotal == null) {
    if (netoTotal != null) {
        subtotal = netoTotal + netoNoGrav + opEx;
    } else if (perNeto > 0 || netoNoGrav > 0 || opEx > 0) {
        subtotal = perNeto + netoNoGrav + opEx;
    }
}
if (iva_total == null && perIva > 0) {
    iva_total = perIva;
}
console.log('Final subtotal', subtotal, 'iva_total', iva_total, 'tributos_total', tributos_total, 'total', total);
console.log('Fecha parsed', parseArDate(r.fecha_emision));
