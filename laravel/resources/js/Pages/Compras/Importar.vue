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
    'nro. doc. emisor': 'proveedor_cuit', 'denominación emisor': 'proveedor_razon_social',
    'tipo de comprobante': 'tipo', 'número desde': 'numero',
    'punto de venta': 'pv', 'fecha de emisión': 'fecha_emision',
    'imp. total': 'total', 'moneda': 'moneda', 'tipo cambio': 'tipo_cambio',
    'cód. autorización': 'arca_cae', 'código de autorización': 'arca_cae',
    'proveedor_cuit': 'proveedor_cuit', 'proveedor_razon_social': 'proveedor_razon_social',
    'tipo': 'tipo', 'numero': 'numero', 'pv': 'pv',
    'fecha_emision': 'fecha_emision',     'total': 'total', 'moneda': 'moneda',
    'subtotal': 'subtotal', 'importe sujeto a impuesto': 'subtotal',
    'iva': 'iva_total', 'importe iva': 'iva_total', 'iva total': 'iva_total',
    'impuestos nacionales': 'tributos_total', 'tributos': 'tributos_total', 'impuestos': 'tributos_total',
};

const tipoArcaMap = {
    '1': 'FA', '2': 'FB', '3': 'FC', '4': 'FCA', '5': 'FCB', '6': 'FCC',
    'factura a': 'FA', 'factura b': 'FB', 'factura c': 'FC',
    'factura credito a': 'FCA', 'factura credito b': 'FCB', 'factura credito c': 'FCC',
    'nota de debito a': 'NDA', 'nota de debito b': 'NDB', 'nota de credito a': 'NCA', 'nota de credito b': 'NCB',
    'nota de débito a': 'NDA', 'nota de débito b': 'NDB', 'nota de crédito a': 'NCA', 'nota de crédito b': 'NCB',
};

const monedaArcaMap = {
    'pes': 'ARS', 'pesos': 'ARS', '$': 'ARS',
    'dol': 'USD', 'dolares': 'USD', 'usd': 'USD',
    'eur': 'EUR', 'euros': 'EUR',
    'brl': 'BRL', 'real': 'BRL', 'reales': 'BRL',
};

const parseCsv = () => {
    const lines = csvText.value.trim().split('\n').filter(Boolean);
    if (lines.length < 2) {
        alert('CSV debe tener encabezado + al menos 1 fila.');
        return;
    }
    const raw = lines[0].trim();
    const delim = raw.includes(';') ? ';' : ',';
    const cleanHeader = (h) => h.replace(/^"(.*)"$/, '$1').replace(/^'(.*)'$/, '$1').trim();
    const rawHeaders = raw.split(delim).map(cleanHeader);
    const mapped = rawHeaders.map((h) => {
        const key = h.toLowerCase().replace(/['"]/g, '').trim();
        return headerMap[key] || null;
    });
    const required = ['proveedor_cuit', 'proveedor_razon_social', 'fecha_emision', 'total'];
    const missing = required.filter((r) => !mapped.includes(r));
    if (missing.length) {
        alert('No se encontraron estas columnas: ' + missing.join(', ') + '. Detectadas: ' + rawHeaders.join(', '));
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

        return {
            proveedor_cuit: r.proveedor_cuit || '',
            proveedor_razon_social: r.proveedor_razon_social || '',
            tipo: tipo,
            numero: r.numero || '',
            pv: r.pv ? parseInt(r.pv, 10) : null,
            fecha_emision: r.fecha_emision || '',
            total: parseFloat(r.total) || 0,
            moneda: moneda,
            subtotal: r.subtotal ? parseFloat(r.subtotal) : null,
            iva_total: r.iva_total ? parseFloat(r.iva_total) : null,
            tributos_total: r.tributos_total ? parseFloat(r.tributos_total) : null,
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

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div v-if="importResult" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded">
                {{ importResult }}
            </div>

            <div v-if="Object.keys(csvForm.errors).length" class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded">
                <p class="font-semibold mb-1">Errores de validacion:</p>
                <ul class="list-disc list-inside text-sm">
                    <li v-for="(msg, field) in csvForm.errors" :key="field">{{ field }}: {{ msg }}</li>
                </ul>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
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
                                    <td class="px-2 py-1">{{ r.tipo }}</td>
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
