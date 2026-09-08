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
const tipoArcaMap = {
    '1': 'FA', '2': 'NDA', '3': 'NCA',
    '6': 'FB', '7': 'NDB', '8': 'NCB',
    '11': 'FC', '12': 'NDC', '13': 'NCC',
    '15': 'FE', '16': 'NDE', '17': 'NCE',
    '51': 'FM', '52': 'NDM', '53': 'NCM',
    '63': 'LB', // Liquidacion B? Not in original, but test
};
const monedaArcaMap = {
    'pes': 'ARS', 'pesos': 'ARS', '$': 'ARS',
    'dol': 'USD', 'dolares': 'USD', 'usd': 'USD',
    'eur': 'EUR', 'euros': 'EUR',
    'brl': 'BRL', 'real': 'BRL', 'reales': 'BRL',
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

const csvText = `"Fecha de Emisión";"Tipo de Comprobante";"Punto de Venta";"Número Desde";"Número Hasta";"Cód. Autorización";"Tipo Doc. Emisor";"Nro. Doc. Emisor";"Denominación Emisor";"Tipo Doc. Receptor";"Nro. Doc. Receptor";"Tipo Cambio";"Moneda";"Imp. Neto Gravado IVA 0%";"IVA 2,5%";"Imp. Neto Gravado IVA 2,5%";"IVA 5%";"Imp. Neto Gravado IVA 5%";"IVA 10,5%";"Imp. Neto Gravado IVA 10,5%";"IVA 21%";"Imp. Neto Gravado IVA 21%";"IVA 27%";"Imp. Neto Gravado IVA 27%";"Imp. Neto Gravado Total";"Imp. Neto No Gravado";"Imp. Op. Exentas";"Otros Tributos";"Total IVA";"Imp. Total"
2026-07-07;1;11;7474;7474;86272815909030;80;30709024902;TRANSFENOR SRL;80;30719333350;1,00;$;;;;;;;;334663,99;1593638,05;;;1593638,05;0,00;0,00;291697,96;334663,99;2220000,00
2026-07-24;11;2;790;790;86305200700821;80;20266943106;FERNANDEZ MARTIN MAXIMILIANO;80;30719333350;1,00;$;0,00;0,00;0,00;0,00;0,00;0,00;0,00;0,00;0,00;0,00;0,00;0,00;0,00;0,00;0,00;0,00;320000,00
2026-07-27;1;2;8581;8581;86305638947954;80;30716825252;AD GROUP S.A.S.;80;30719333350;1,00;$;;;;;;;;165901,05;790005,00;;;790005,00;0,00;0,00;0,00;165901,05;955906,05
2026-07-31;63;1139;5362;5362;86317002718870;80;30571421352;BANCO CREDICOOP COOPERATIVO LTDO;80;30719333350;1,00;$;;;;;;;;525,00;2500,00;;;2500,00;0,00;0,00;0,00;525,00;3025,00`;

const lines = csvText.trim().split('\n').filter(Boolean);
const raw = lines[0].trim();
let delim = ',';
if (raw.includes('\t')) delim = '\t';
else if (raw.includes(';')) delim = ';';
else if (raw.includes(',')) delim = ',';
console.log('Delim:', delim);

const cleanHeader = (h) => h.replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim();
const rawHeaders = raw.split(delim).map(cleanHeader);
const mapped = rawHeaders.map((h) => {
    const key = normalizeKey(h);
    return headerMap[key] || null;
});
console.log('Mapped:', mapped);

const rows = lines.slice(1).map((line) => {
    const vals = line.split(delim).map((v) => v.replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim());
    const row = {};
    mapped.forEach((field, i) => { if (field) row[field] = vals[i] || ''; });
    return row;
});
console.log('Rows raw:', rows);

const tipoArcaMapFull = tipoArcaMap;
const monedaArcaMapFull = monedaArcaMap;

let csvFormRows = rows.map((r) => {
    let tipo = (r.tipo || 'FA').trim();
    const tipoLower = tipo.toLowerCase();
    if (tipoArcaMapFull[tipo]) tipo = tipoArcaMapFull[tipo];
    else if (tipoArcaMapFull[tipoLower]) tipo = tipoArcaMapFull[tipoLower];

    let moneda = (r.moneda || 'ARS').trim().toLowerCase().replace(/[^a-z$]/g, '');
    moneda = monedaArcaMapFull[moneda] || (['ars','usd','eur','brl'].includes(moneda) ? moneda.toUpperCase() : 'ARS');

    const total = parseArNumber(r.total) || 0;
    let subtotal = parseArNumber(r.subtotal);
    let iva_total = parseArNumber(r.iva_total);
    let tributos_total = parseArNumber(r.tributos_total);

    const netoTotal = parseArNumber(r.neto_total);
    const netoNoGrav = parseArNumber(r.neto_no_gravado) || 0;
    const opEx = parseArNumber(r.op_exentas) || 0;
    const perNeto = ['neto_iva_0','neto_iva_2_5','neto_iva_5','neto_iva_10_5','neto_iva_21','neto_iva_27'].reduce((s,k) => s + (parseArNumber(r[k]) || 0), 0);
    const perIva = ['iva_2_5','iva_5','iva_10_5','iva_21','iva_27'].reduce((s,k) => s + (parseArNumber(r[k]) || 0), 0);

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

    const fechaNorm = parseArDate(r.fecha_emision);

    return {
        proveedor_cuit: r.proveedor_cuit || '',
        proveedor_razon_social: r.proveedor_razon_social || '',
        tipo: tipo,
        numero: r.numero || '',
        pv: r.pv ? parseInt(String(r.pv).replace(/\D/g,''), 10) || null : null,
        fecha_emision: fechaNorm || r.fecha_emision || '',
        total: total,
        moneda: moneda,
        subtotal: subtotal,
        iva_total: iva_total,
        tributos_total: tributos_total,
    };
});

console.log('CsvForm rows:');
csvFormRows.forEach((r,i) => {
    console.log(`Row ${i}:`, JSON.stringify(r));
    // Simulate backend validation
    const errors = [];
    if (!r.proveedor_cuit) errors.push('proveedor_cuit missing');
    if (!r.proveedor_razon_social) errors.push('proveedor_razon_social missing');
    if (!r.fecha_emision) errors.push('fecha_emision missing');
    if (!r.total) errors.push('total missing or 0');
    if (errors.length) console.log('  Validation errors:', errors);
});

console.log('All rows would pass backend validation?', csvFormRows.every(r => r.proveedor_cuit && r.proveedor_razon_social && r.fecha_emision && r.total));
