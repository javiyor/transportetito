<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    manifiestos: Object,
});

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};

const monedaSymbol = (moneda) => {
    if (moneda === 'USD') return 'USD';
    if (moneda === 'EUR') return 'EUR';
    return '$';
};
</script>

<template>
    <AppLayout title="Facturacion / Pendientes">
        <Head title="Facturacion / Pendientes" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturacion / Manifiestos pendientes</h2>
                <Link :href="route('operacion.comprobantes.index')">
                    <button type="button" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Comprobantes emitidos</button>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div v-if="!manifiestos?.data?.length" class="px-6 py-10 text-center text-sm text-gray-500">
                    No hay manifiestos con pedidos pendientes de facturar.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transporte</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chofer</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Pendientes</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="m in manifiestos.data" :key="m.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">#{{ m.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ formatFecha(m.fecha) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ m.empresa?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ m.transporte || '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ m.chofer || '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ m.pendientes_count }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <Link :href="route('facturacion.manifiestos.show', m.id)" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                        Facturar
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="manifiestos?.last_page > 1" class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between gap-4">
                        <Link v-if="manifiestos.prev_page_url" :href="manifiestos.prev_page_url" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Anterior</Link>
                        <span class="text-sm text-gray-500">Pagina {{ manifiestos.current_page }} de {{ manifiestos.last_page }}</span>
                        <Link v-if="manifiestos.next_page_url" :href="manifiestos.next_page_url" class="text-sm text-indigo-600 hover:text-indigo-800">Siguiente &rarr;</Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
