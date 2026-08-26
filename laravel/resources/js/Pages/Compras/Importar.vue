<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({});

const page = usePage();
const importResult = computed(() => page.props.tt?.flash?.importResult);

const csvText = ref('');
const csvPreview = ref([]);

const csvForm = useForm({
    rows: [],
});

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
    'proveedor_cuit': 'proveedor_cuit',
    'proveedor_razon_social': 'proveedor_razon_social',
    'tipo': 'tipo',
    'numero': 'numero',
    'pv': 'pv',
    'fecha_emision': 'fecha_emision',
    'subtotal': 'subtotal',
    'importe sujeto a impuesto': 'subtotal',
    'iva': 'iva_total',
    'importe iva': 'iva_total',
    'iva total': 'iva_total',
    'total iva': 'iva_total',
    'impuestos nacionales': 'tributos_total',
    'tributos': 'tributos_total',
    'impuestos': 'tributos_total',
    'imp neto gravado total': 'neto_total',
    'imp neto no gravado': 'neto_no_gravado',
    'imp op exentas': 'op_exentas',
    'importe op exentas': 'op_exentas',
    'otros tributos': 'tributos_total',
    // Detalle por alícuota ARCA (para sumar cuando no hay total)
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
    'factura a': 'FA', 'factura b': 'FB', 'factura c': 'FC', 'factura e': 'FE', 'factura m': 'FM',
    'factura credito a': 'FCA', 'factura credito b': 'FCB', 'factura credito c': 'FCC',
    'nota de debito a': 'NDA', 'nota de debito b': 'NDB', 'nota de debito c': 'NDC',
    'nota de debito e': 'NDE', 'nota de debito m': 'NDM',
    'nota de credito a': 'NCA', 'nota de credito b': 'NCB', 'nota de credito c': 'NCC',
    'nota de credito e': 'NCE', 'nota de credito m': 'NCM',
};

const tipoLabel = (t) => ({
    'FA': 'Factura A', 'FB': 'Factura B', 'FC': 'Factura C', 'FE': 'Factura E', 'FM': 'Factura M',
    'NDA': 'Nota de Débito A', 'NDB': 'Nota de Débito B', 'NDC': 'Nota de Débito C', 'NDE': 'Nota de Débito E', 'NDM': 'Nota de Débito M',
    'NCA': 'Nota de Crédito A', 'NCB': 'Nota de Crédito B', 'NCC': 'Nota de Crédito C', 'NCE': 'Nota de Crédito E', 'NCM': 'Nota de Crédito M',
    'FCA': 'Factura Crédito A', 'FCB': 'Factura Crédito B', 'FCC': 'Factura Crédito C',
}[t] || t);

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
    // Si tiene coma, es decimal argentino: 1.234,56 -> 1234.56
    if (s.includes(',')) {
        s = s.replace(/\./g, '').replace(',', '.');
    }
    const n = parseFloat(s);
    return isNaN(n) ? null : n;
};
const parseArDate = (v) => {
    if (!v) return '';
    const s = String(v).trim();
    // Ya viene YYYY-MM-DD
    if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
    // DD/MM/YYYY o DD-MM-YYYY o DD.MM.YYYY
    const m = s.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/);
    if (m) {
        const dd = m[1].padStart(2, '0');
        const mm = m[2].padStart(2, '0');
        const yyyy = m[3];
        return `${yyyy}-${mm}-${dd}`;
    }
    return s;
};

const parseCsv = () => {
    const lines = csvText.value.trim().split('\n').filter(Boolean);
    if (lines.length < 2) {
        alert('CSV debe tener encabezado + al menos 1 fila.');
        return;
    }
    const raw = lines[0].trim();
    // Detectar delimitador: tab > ; > ,
    let delim = ',';
    if (raw.includes('\t')) delim = '\t';
    else if (raw.includes(';')) delim = ';';
    else if (raw.includes(',')) delim = ',';

    const cleanHeader = (h) => h.replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim();
    const rawHeaders = raw.split(delim).map(cleanHeader);
    const mapped = rawHeaders.map((h) => {
        const key = normalizeKey(h);
        // Fallback para headers que no están en mapa pero contienen neto/iva
        if (headerMap[key]) return headerMap[key];
        if (key.startsWith('imp neto gravado')) {
            // Mapear genérico a neto_detail si no está explícito
            if (headerMap[key]) return headerMap[key];
            // Intentar encontrar coincidencia parcial
            for (const k of Object.keys(headerMap)) {
                if (k.startsWith('imp neto gravado') && key.includes(k.replace('imp neto gravado', '').trim())) {
                    return headerMap[k];
                }
            }
            return null;
        }
        if (key.startsWith('iva ')) {
            if (headerMap[key]) return headerMap[key];
            return null;
        }
        return null;
    });
    const required = ['proveedor_cuit', 'proveedor_razon_social', 'fecha_emision', 'total'];
    const missing = required.filter((r) => !mapped.includes(r));
    if (missing.length) {
        alert('No se encontraron estas columnas: ' + missing.join(', ') + '. Detectadas: ' + rawHeaders.join(', ') + ' (delim=' + (delim === '\t' ? 'TAB' : delim) + ')');
        return;
    }
    const rows = lines.slice(1).map((line) => {
        const vals = line.split(delim).map((v) => v.replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim());
        const row = {};
        mapped.forEach((field, i) => { if (field) row[field] = vals[i] || ''; });
        return row;
    });
    csvPreview.value = rows;
    csvForm.rows = rows.map((r) => {
        let tipo = (r.tipo || 'FA').trim();
        const tipoLower = tipo.toLowerCase();
        if (tipoArcaMap[tipo]) tipo = tipoArcaMap[tipo];
        else if (tipoArcaMap[tipoLower]) tipo = tipoArcaMap[tipoLower];

        let moneda = (r.moneda || 'ARS').trim().toLowerCase().replace(/[^a-z$]/g, '');
        moneda = monedaArcaMap[moneda] || (['ars','usd','eur','brl'].includes(moneda) ? moneda.toUpperCase() : 'ARS');

        // Parsear montos con formato argentino
        const total = parseArNumber(r.total) || 0;
        let subtotal = parseArNumber(r.subtotal);
        let iva_total = parseArNumber(r.iva_total);
        let tributos_total = parseArNumber(r.tributos_total);

        // Si viene formato ARCA detallado, calcular subtotal/iva desde desglose
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
        // Si Otros Tributos viene pero tributos_total ya es ese valor, ok
        if (tributos_total == null && r.tributos_total != null) {
            tributos_total = parseArNumber(r.tributos_total);
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
};

const submitCsv = () => {
    if (!csvForm.rows.length) return;
    csvForm.post(route('compras.importar.csv'), {
        preserveScroll: true,
        onSuccess: () => { csvText.value = ''; csvPreview.value = []; csvForm.rows = []; },
    });
};
</script>

<template>
    <AppLayout title="Compras / Importar">
        <Head title="Compras / Importar" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Importar comprobantes de proveedores</h2>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div v-if="importResult" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded">
                {{ importResult }}
            </div>

            <div v-if="Object.keys(csvForm.errors).length" class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded">
                <p class="font-semibold mb-1">Errores de validacion:</p>
                <ul class="list-disc list-inside text-sm">
                    <li v-for="(msg, field) in csvForm.errors" :key="field">{{ field }}: {{ msg }}</li>
                </ul>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Importar desde CSV</h3>
                <p class="text-sm text-gray-500 mb-4">Pegue el CSV. Detecta columnas automaticamente. Requiere: proveedor_cuit/CUIT, proveedor_razon_social/denominacion, fecha_emision, total.</p>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pegar CSV</label>
                    <textarea v-model="csvText" rows="8" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono" placeholder="Pegue aqui el CSV&#10;Ej: proveedor_cuit,proveedor_razon_social,tipo,numero,fecha_emision,total,moneda&#10;20333999911,PROVEEDOR SA,FA,00001,2026-06-01,50000.00,ARS"></textarea>
                </div>

                <SecondaryButton :disabled="!csvText.trim()" @click="parseCsv">Previsualizar</SecondaryButton>

                <div v-if="csvForm.rows.length" class="mt-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">{{ csvForm.rows.length }} fila(s) detectada(s)</p>
                    <div class="overflow-x-auto max-h-60 overflow-y-auto border border-gray-200 rounded">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-2 py-1 text-left">CUIT</th>
                                <th class="px-2 py-1 text-left">Proveedor</th>
                                <th class="px-2 py-1 text-left">Tipo</th>
                                <th class="px-2 py-1 text-left">Nro</th>
                                <th class="px-2 py-1 text-left">Fecha</th>
                                <th class="px-2 py-1 text-right">Subtotal</th>
                                <th class="px-2 py-1 text-right">IVA</th>
                                <th class="px-2 py-1 text-right">Tributos</th>
                                <th class="px-2 py-1 text-right">Total</th>
                                <th class="px-2 py-1 text-left">Mon</th>
                            </tr></thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(r, i) in csvForm.rows" :key="i">
                                    <td class="px-2 py-1 font-mono">{{ r.proveedor_cuit }}</td>
                                    <td class="px-2 py-1">{{ r.proveedor_razon_social }}</td>
                                    <td class="px-2 py-1">{{ tipoLabel(r.tipo) }}</td>
                                    <td class="px-2 py-1">{{ r.numero }}</td>
                                    <td class="px-2 py-1">{{ r.fecha_emision }}</td>
                                    <td class="px-2 py-1 text-right">{{ r.subtotal ?? '-' }}</td>
                                    <td class="px-2 py-1 text-right">{{ r.iva_total ?? '-' }}</td>
                                    <td class="px-2 py-1 text-right">{{ r.tributos_total ?? '-' }}</td>
                                    <td class="px-2 py-1 text-right">{{ r.total }}</td>
                                    <td class="px-2 py-1 font-bold">{{ r.moneda }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <PrimaryButton :disabled="csvForm.processing" @click="submitCsv">Importar {{ csvForm.rows.length }} comprobante(s)</PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
