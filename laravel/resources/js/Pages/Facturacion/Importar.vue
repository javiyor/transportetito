<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    depositos: Array,
});

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

const parseCsv = () => {
    const lines = csvText.value.trim().split('\n').filter(Boolean);
    if (lines.length < 2) {
        alert('CSV debe tener encabezado + al menos 1 fila.');
        return;
    }
    const headers = lines[0].split(',').map((h) => h.trim().toLowerCase());
    const expected = ['tipo', 'pv', 'numero', 'cuit_cliente', 'razon_social', 'fecha_emision', 'total', 'moneda'];
    const missing = expected.filter((h) => !headers.includes(h));
    if (missing.length) {
        alert('Columnas esperadas: ' + expected.join(', ') + '. Faltan: ' + missing.join(', '));
        return;
    }
    const rows = lines.slice(1).map((line) => {
        const vals = line.split(',').map((v) => v.trim());
        const row = {};
        headers.forEach((h, i) => { row[h] = vals[i] || ''; });
        return row;
    });
    csvPreview.value = rows;
    csvForm.rows = rows.map((r) => ({
        tipo: r.tipo,
        pv: parseInt(r.pv, 10),
        numero: parseInt(r.numero, 10),
        cuit_cliente: r.cuit_cliente,
        razon_social: r.razon_social,
        fecha_emision: r.fecha_emision,
        total: parseFloat(r.total),
        moneda: r.moneda || 'ARS',
    }));
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

            <div class="flex gap-4 mb-4">
                <button class="px-4 py-2 text-sm rounded" :class="modo === 'csv' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-300'" @click="modo = 'csv'">CSV / Archivo</button>
                <button class="px-4 py-2 text-sm rounded" :class="modo === 'arca' ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-300'" @click="modo = 'arca'">ARCA WSFE</button>
            </div>

            <!-- CSV mode -->
            <div v-if="modo === 'csv'" class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-2">Importar desde CSV</h3>
                <p class="text-sm text-gray-500 mb-4">Columnas: tipo, pv, numero, cuit_cliente, razon_social, fecha_emision, total, moneda</p>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pegar CSV</label>
                    <textarea v-model="csvText" rows="8" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono" placeholder="tipo,pv,numero,cuit_cliente,razon_social,fecha_emision,total,moneda&#10;FA,1,5,20333999911,CLIENTE SA,2026-06-01,15000.00,ARS"></textarea>
                </div>

                <SecondaryButton :disabled="!csvText.trim()" @click="parseCsv">Previsualizar</SecondaryButton>

                <div v-if="csvPreview.length" class="mt-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">{{ csvPreview.length }} fila(s) detectada(s)</p>
                    <div class="overflow-x-auto max-h-60 overflow-y-auto border border-gray-200 rounded">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="bg-gray-50"><tr>
                                <th class="px-2 py-1 text-left">Tipo</th><th class="px-2 py-1 text-left">PV</th><th class="px-2 py-1 text-left">Nro</th>
                                <th class="px-2 py-1 text-left">CUIT</th><th class="px-2 py-1 text-left">Cliente</th><th class="px-2 py-1 text-left">Fecha</th>
                                <th class="px-2 py-1 text-right">Total</th><th class="px-2 py-1 text-left">Mon</th>
                            </tr></thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(r, i) in csvPreview" :key="i">
                                    <td class="px-2 py-1">{{ r.tipo }}</td><td class="px-2 py-1">{{ r.pv }}</td><td class="px-2 py-1">{{ r.numero }}</td>
                                    <td class="px-2 py-1 font-mono">{{ r.cuit_cliente }}</td><td class="px-2 py-1">{{ r.razon_social }}</td><td class="px-2 py-1">{{ r.fecha_emision }}</td>
                                    <td class="px-2 py-1 text-right">{{ r.total }}</td><td class="px-2 py-1">{{ r.moneda }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <PrimaryButton :disabled="csvForm.processing" @click="submitCsv">Importar {{ csvPreview.length }} factura(s)</PrimaryButton>
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
                            <option value="FA">Factura A</option>
                            <option value="FB">Factura B</option>
                            <option value="FC">Factura C</option>
                            <option value="FCA">Factura Crédito A</option>
                            <option value="FCB">Factura Crédito B</option>
                            <option value="FCC">Factura Crédito C</option>
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
