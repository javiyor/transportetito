<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    recibo: Object,
});

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};
</script>

<template>
    <AppLayout :title="`Cobranzas / Recibo #${recibo.id}`">
        <Head :title="`Cobranzas / Recibo #${recibo.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Recibo #{{ recibo.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">{{ formatFecha(recibo.fecha) }} · Estado: {{ recibo.estado }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2 justify-end">
                    <a :href="route('cobranzas.recibos.print', recibo.id)" target="_blank"><SecondaryButton>Imprimir / PDF</SecondaryButton></a>
                    <Link :href="route('cobranzas.recibos.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Cuenta</div>
                        <div class="text-sm font-medium text-gray-900">{{ recibo.cuenta?.tercero?.razon_social || '-' }}</div>
                        <div class="text-xs text-gray-500">CUIT {{ recibo.cuenta?.tercero?.cuit || '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Total</div>
                        <div class="text-sm font-medium text-gray-900">{{ recibo.moneda }} {{ recibo.total }}</div>
                        <div v-if="recibo.moneda !== 'ARS'" class="text-xs text-gray-500">Cotizacion {{ recibo.cotizacion_ars }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Pre-recibo origen</div>
                        <div class="text-sm font-medium text-gray-900">{{ recibo.pre_recibo_id ? ('#' + recibo.pre_recibo_id) : '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Items</h3></div>
                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="it in (recibo.items || [])" :key="it.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ it.medio }}</div>
                                <div class="text-xs text-gray-500">{{ it.moneda }} {{ it.importe }}</div>
                            </div>
                            <div class="text-xs text-gray-500">{{ it.moneda === 'ARS' ? '-' : it.cotizacion_ars }}</div>
                        </div>
                        <pre class="mt-3 text-xs bg-gray-50 border border-gray-200 rounded p-2 overflow-auto whitespace-pre-wrap">{{ it.detalle ? JSON.stringify(it.detalle, null, 2) : '' }}</pre>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="it in (recibo.items || [])" :key="it.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.medio }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.moneda }} {{ it.importe }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.moneda === 'ARS' ? '-' : it.cotizacion_ars }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700"><pre class="text-xs bg-gray-50 border border-gray-200 rounded p-2 overflow-auto">{{ it.detalle ? JSON.stringify(it.detalle, null, 2) : '' }}</pre></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Aplicaciones</h3></div>
                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="ap in (recibo.aplicaciones || [])" :key="ap.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ ap.modo }}</div>
                                <div class="text-xs text-gray-500">{{ ap.comprobante_id ? ('#' + ap.comprobante_id) : '-' }}</div>
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ ap.moneda }} {{ ap.importe }}</div>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">Cotizacion: {{ ap.moneda === 'ARS' ? '-' : ap.cotizacion_ars }}</div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comprobante</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="ap in (recibo.aplicaciones || [])" :key="ap.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.modo }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.comprobante_id ? ('#' + ap.comprobante_id) : '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.moneda }} {{ ap.importe }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.moneda === 'ARS' ? '-' : ap.cotizacion_ars }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
