<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({});

const page = usePage();
const importResult = computed(() => page.props.tt?.flash?.importResult);

const modo = ref('csv');

const csvForm = useForm({
    rows: [],
});

const arcaForm = useForm({
    punto_venta: '',
    tipo_comprobante: 'FA',
    numero_desde: '',
    numero_hasta: '',
});

const csvText = ref('');
const csvPreview = ref([]);

const arcaHeaderMap = {
    'fecha de emisión': 'fecha_emision', 'tipo de comprobante': 'tipo',
    'punto de venta': 'pv', 'número desde': 'numero',
    'cód. autorización': 'arca_cae', 'código de autorización': 'arca_cae',
    'nro. doc. receptor': 'cuit_cliente', 'denominación receptor': 'razon_social',
    'moneda': 'moneda', 'imp. total': 'total', 'tipo cambio': 'tipo_cambio',
    'importe neto gravado': 'subtotal', 'neto gravado': 'subtotal',
    'iva total': 'iva_total',
    'imp. tributos': 'tributos_total', 'tributos total': 'tributos_total',
};

const oldHeaderMap = {
    'tipo': 'tipo', 'pv': 'pv', 'numero': 'numero',
    'cuit_cliente': 'cuit_cliente', 'razon_social': 'razon_social',
    'fecha_emision': 'fecha_emision', 'total': 'total', 'moneda': 'moneda',
    'subtotal': 'subtotal', 'iva_total': 'iva_total', 'tributos_total': 'tributos_total',
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
    'nota de débito a': 'NDA', 'nota de débito b': 'NDB', 'nota de débito c': 'NDC',
    'nota de débito e': 'NDE', 'nota de débito m': 'NDM',
    'nota de crédito a': 'NCA', 'nota de crédito b': 'NCB', 'nota de crédito c': 'NCC',
    'nota de crédito e': 'NCE', 'nota de crédito m': 'NCM',
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
    const headerMap = { ...arcaHeaderMap, ...oldHeaderMap };
    const mapped = rawHeaders.map((h, i) => {
        const key = h.toLowerCase().replace(/['"]/g, '').trim();
        return headerMap[key] || null;
    });
    const required = ['tipo', 'pv', 'numero', 'cuit_cliente', 'razon_social', 'fecha_emision', 'total'];
    const missing = required.filter((r) => !mapped.includes(r));
    if (missing.length) {
        alert('No se encontraron estas columnas: ' + missing.join(', ') + '. Columnas detectadas: ' + rawHeaders.join(', '));
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
        let tipo = (r.tipo || '').trim();
        const tipoLower = tipo.toLowerCase();
        if (tipoArcaMap[tipo]) tipo = tipoArcaMap[tipo];
        else if (tipoArcaMap[tipoLower]) tipo = tipoArcaMap[tipoLower];

        let moneda = (r.moneda || 'ARS').trim().toLowerCase().replace(/[^a-z$]/g, '');
        moneda = monedaArcaMap[moneda] || (['ars','usd','eur','brl'].includes(moneda) ? moneda.toUpperCase() : 'ARS');

        return {
            tipo: tipo || 'FA',
            pv: parseInt(r.pv, 10),
            numero: parseInt(r.numero, 10),
            cuit_cliente: r.cuit_cliente || '',
            razon_social: r.razon_social || '',
            fecha_emision: r.fecha_emision || '',
            total: parseFloat(r.total) || 0,
            moneda: moneda,
            arca_cae: r.arca_cae || null,
            subtotal: r.subtotal ? parseFloat(r.subtotal) : 0,
            iva_total: r.iva_total ? parseFloat(r.iva_total) : 0,
            tributos_total: r.tributos_total ? parseFloat(r.tributos_total) : 0,
        };
    });
};

const submitCsv = () => {
    if (!csvForm.rows.length) return;
    csvForm.post(route('facturacion.importar.csv'), {
        preserveScroll: true,
        onSuccess: () => { csvText.value = ''; csvPreview.value = []; csvForm.rows = []; },
    });
};

const submitArca = () => {
    arcaForm.post(route('facturacion.importar.arca'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Facturacion / Importar facturas">
        <Head title="Facturacion / Importar facturas" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Importar facturas emitidas</h2>
            </div>
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

            <div class="flex gap-4 mb-4">
                <button class="px-4 py-2 text-sm rounded" :class="modo === 'csv' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-300'" @click="modo = 'csv'">CSV / Archivo</button>
                <button class="px-4 py-2 text-sm rounded" :class="modo === 'arca' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-300'" @click="modo = 'arca'">ARCA WSFE</button>
            </div>

            <!-- CSV mode -->
            <div v-if="modo === 'csv'" class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Importar desde CSV</h3>
                <p class="text-sm text-gray-500 mb-4">Pegue el CSV descargado de ARCA (formato separado por punto y coma) o el formato simple. Detecta columnas automaticamente.</p>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pegar CSV</label>
                    <textarea v-model="csvText" rows="8" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono" placeholder="Pegue aqui el CSV de ARCA o formato simple&#10;Ej: &quot;Fecha de Emisión&quot;;&quot;Tipo de Comprobante&quot;;&quot;Punto de Venta&quot;;&quot;Número Desde&quot;;&quot;Cód. Autorización&quot;;&quot;Nro. Doc. Receptor&quot;;&quot;Denominación Receptor&quot;;&quot;Moneda&quot;;&quot;Imp. Total&quot;"></textarea>
                </div>

                <SecondaryButton :disabled="!csvText.trim()" @click="parseCsv">Previsualizar</SecondaryButton>

                <div v-if="csvForm.rows.length" class="mt-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">{{ csvForm.rows.length }} fila(s) detectada(s)</p>
                    <div class="overflow-x-auto max-h-60 overflow-y-auto border border-gray-200 rounded">
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead class="bg-gray-50"><tr>
                                    <th class="px-2 py-1 text-left">Tipo</th><th class="px-2 py-1 text-left">PV</th><th class="px-2 py-1 text-left">Nro</th>
                                    <th class="px-2 py-1 text-left">CUIT</th><th class="px-2 py-1 text-left">Cliente</th><th class="px-2 py-1 text-left">Fecha</th>
                                    <th class="px-2 py-1 text-right">Subtotal</th><th class="px-2 py-1 text-right">IVA</th>
                                    <th class="px-2 py-1 text-right">Total</th><th class="px-2 py-1 text-left">Mon</th>
                                </tr></thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(r, i) in csvForm.rows" :key="i">
                                        <td class="px-2 py-1">{{ r.tipo }}</td><td class="px-2 py-1">{{ r.pv }}</td><td class="px-2 py-1">{{ r.numero }}</td>
                                        <td class="px-2 py-1 font-mono">{{ r.cuit_cliente }}</td><td class="px-2 py-1">{{ r.razon_social }}</td><td class="px-2 py-1">{{ r.fecha_emision }}</td>
                                        <td class="px-2 py-1 text-right">{{ r.subtotal || '-' }}</td><td class="px-2 py-1 text-right">{{ r.iva_total || '-' }}</td>
                                        <td class="px-2 py-1 text-right">{{ r.total }}</td><td class="px-2 py-1 font-bold">{{ r.moneda }}</td>
                                    </tr>
                                </tbody>
                            </table>
                    </div>
                    <div class="mt-4">
                        <PrimaryButton :disabled="csvForm.processing" @click="submitCsv">Importar {{ csvForm.rows.length }} factura(s)</PrimaryButton>
                    </div>
                </div>
            </div>

            <!-- ARCA mode -->
            <div v-if="modo === 'arca'" class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Importar desde ARCA (WSFE)</h3>
                <p class="text-sm text-gray-500 mb-4">Consultar comprobantes emitidos en AFIP/ARCA por rango de numeración.</p>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Punto de venta</label>
                        <input v-model="arcaForm.punto_venta" type="number" min="1" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo comprobante</label>
                        <select v-model="arcaForm.tipo_comprobante" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <optgroup label="Factura">
                                <option value="FA">Factura A</option>
                                <option value="FB">Factura B</option>
                                <option value="FC">Factura C</option>
                                <option value="FE">Factura E</option>
                                <option value="FM">Factura M</option>
                            </optgroup>
                            <optgroup label="Nota de Débito">
                                <option value="NDA">Nota de Débito A</option>
                                <option value="NDB">Nota de Débito B</option>
                                <option value="NDC">Nota de Débito C</option>
                                <option value="NDE">Nota de Débito E</option>
                                <option value="NDM">Nota de Débito M</option>
                            </optgroup>
                            <optgroup label="Nota de Crédito">
                                <option value="NCA">Nota de Crédito A</option>
                                <option value="NCB">Nota de Crédito B</option>
                                <option value="NCC">Nota de Crédito C</option>
                                <option value="NCE">Nota de Crédito E</option>
                                <option value="NCM">Nota de Crédito M</option>
                            </optgroup>
                            <optgroup label="Factura de Crédito">
                                <option value="FCA">Factura Crédito A</option>
                                <option value="FCB">Factura Crédito B</option>
                                <option value="FCC">Factura Crédito C</option>
                            </optgroup>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nro desde</label>
                        <input v-model="arcaForm.numero_desde" type="number" min="1" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nro hasta</label>
                        <input v-model="arcaForm.numero_hasta" type="number" min="1" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                </div>

                <div class="mt-4">
                    <PrimaryButton :disabled="arcaForm.processing || !arcaForm.punto_venta || !arcaForm.numero_desde || !arcaForm.numero_hasta" @click="submitArca">
                        {{ arcaForm.processing ? 'Importando...' : 'Importar desde ARCA' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
