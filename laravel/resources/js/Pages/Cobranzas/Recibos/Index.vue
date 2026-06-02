<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    recibos: Object,
});

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};
</script>

<template>
    <AppLayout title="Cobranzas / Recibos">
        <Head title="Cobranzas / Recibos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Recibos</h2>
                <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.index')">Ver pre-recibos</Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <p class="text-sm text-gray-600">Recibos confirmados con moneda y cotizacion usada.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="r in recibos.data" :key="r.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatFecha(r.fecha) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ r.cuenta?.tercero?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">CUIT {{ r.cuenta?.tercero?.cuit || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ r.estado }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ r.moneda }} {{ r.total }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ r.moneda === 'ARS' ? '-' : r.cotizacion_ars }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <Link class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.show', r.id)">Ver</Link>
                                </td>
                            </tr>
                            <tr v-if="!recibos.data.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No hay recibos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
