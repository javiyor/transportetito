<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    pedidos: Object,
    filtros: Object,
    estados: Array,
});

const form = useForm({
    desde: props.filtros.desde || '',
    hasta: props.filtros.hasta || '',
    estado: props.filtros.estado || '',
    remitente: props.filtros.remitente || '',
    destinatario: props.filtros.destinatario || '',
});

const applyFilters = () => {
    router.get(route('operacion.fletes.index'), {
        ...(form.desde && { desde: form.desde }),
        ...(form.hasta && { hasta: form.hasta }),
        ...(form.estado && { estado: form.estado }),
        ...(form.remitente && { remitente: form.remitente }),
        ...(form.destinatario && { destinatario: form.destinatario }),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const clearFilters = () => {
    form.reset();
    router.get(route('operacion.fletes.index'), {}, { preserveState: true, preserveScroll: true, replace: true });
};

const exportCsv = () => {
    const params = new URLSearchParams();
    if (props.filtros.desde) params.set('desde', props.filtros.desde);
    if (props.filtros.hasta) params.set('hasta', props.filtros.hasta);
    if (props.filtros.estado) params.set('estado', props.filtros.estado);
    if (props.filtros.remitente) params.set('remitente', props.filtros.remitente);
    if (props.filtros.destinatario) params.set('destinatario', props.filtros.destinatario);
    params.set('export', 'csv');
    window.open(route('operacion.fletes.index') + '?' + params.toString(), '_blank');
};
</script>

<template>
    <AppLayout title="Operacion / Fletes">
        <Head title="Operacion / Fletes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Consulta de Fletes</h2>
                <div class="flex items-center gap-2">
                    <button type="button" class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700" @click="exportCsv">Exportar CSV</button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Desde</label>
                        <input v-model="form.desde" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Hasta</label>
                        <input v-model="form.hasta" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estado</label>
                        <select v-model="form.estado" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todos</option>
                            <option v-for="e in estados" :key="e" :value="e">{{ e }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Remitente</label>
                        <input v-model="form.remitente" type="text" placeholder="Buscar..." class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Destinatario</label>
                        <input v-model="form.destinatario" type="text" placeholder="Buscar..." class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm text-gray-800 hover:bg-gray-200" @click="applyFilters">Aplicar</button>
                        <button type="button" class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-md text-sm text-gray-600 hover:bg-gray-50" @click="clearFilters">Limpiar</button>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remitente</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatario</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origen</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destino</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Palets</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Declarado</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Manifiesto</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Hoja Ruta</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comprobante</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in pedidos.data" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900 whitespace-nowrap">#{{ p.id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 max-w-48 truncate" :title="p.remitente">{{ p.remitente || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 max-w-48 truncate" :title="p.destinatario">{{ p.destinatario || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ p.origen || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ p.destino || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-center">{{ p.bultos }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-center">{{ p.palets }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-right">${{ Number(p.valor_declarado).toFixed(2) }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="estadoClass(p.estado)">{{ p.estado }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ p.fecha }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <Link v-if="p.manifiesto_id" :href="route('operacion.manifiestos.show', p.manifiesto_id)" class="text-indigo-600 hover:text-indigo-800">#{{ p.manifiesto_id }}</Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <Link v-if="p.hoja_ruta_id" :href="route('operacion.repartos.hojas.show', p.hoja_ruta_id)" class="text-indigo-600 hover:text-indigo-800">#{{ p.hoja_ruta_id }}</Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <Link v-if="p.comprobante_id" :href="route('operacion.comprobantes.show', p.comprobante_id)" class="text-indigo-600 hover:text-indigo-800 whitespace-nowrap">{{ p.comprobante_tipo }} {{ p.comprobante_numero || '#' + p.comprobante_id }}</Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="!pedidos.data?.length">
                                <td colspan="13" class="px-4 py-8 text-sm text-gray-400 text-center">No se encontraron fletes en el periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pedidos.total > pedidos.per_page" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Mostrando {{ pedidos.from }}–{{ pedidos.to }} de {{ pedidos.total }} resultados
                    </div>
                    <div class="flex gap-2">
                        <button v-if="pedidos.prev_page_url" type="button" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50" @click="router.get(pedidos.prev_page_url, {}, { preserveState: true, preserveScroll: true })">Anterior</button>
                        <button v-if="pedidos.next_page_url" type="button" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50" @click="router.get(pedidos.next_page_url, {}, { preserveState: true, preserveScroll: true })">Siguiente</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
export default {
    methods: {
        estadoClass(estado) {
            const map = {
                en_deposito: 'bg-yellow-100 text-yellow-800',
                en_viaje: 'bg-blue-100 text-blue-800',
                entregado: 'bg-green-100 text-green-800',
                no_entregado: 'bg-red-100 text-red-800',
                pendiente: 'bg-gray-100 text-gray-800',
            };
            return map[estado] || 'bg-gray-100 text-gray-800';
        },
    },
};
</script>
