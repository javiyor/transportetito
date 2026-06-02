<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    preRecibos: Object,
    filters: Object,
    zonas: Array,
    localidades: Array,
    summaryByZona: Array,
    summaryByLocalidad: Array,
});

const form = useForm({
    estado: props.filters?.estado || 'borrador',
    zona_id: props.filters?.zona_id || '',
    localidad: props.filters?.localidad || '',
    barrio: props.filters?.barrio || '',
});

const applyFilters = () => {
    router.get(route('cobranzas.pre-recibos.index'), {
        estado: form.estado,
        zona_id: form.zona_id || null,
        localidad: form.localidad || null,
        barrio: form.barrio || null,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};
</script>

<template>
    <AppLayout title="Cobranzas / Pre-recibos">
        <Head title="Cobranzas / Pre-recibos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Pre-recibos</h2>
                <div class="flex items-center gap-3">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.export', { estado: form.estado, zona_id: form.zona_id || null, localidad: form.localidad || null, barrio: form.barrio || null })">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.index')">Ctas. ctes.</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.index')">Ver recibos</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">Estado</div>
                        <select v-model="form.estado" class="mt-1 block w-56 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="borrador">Borrador</option>
                            <option value="confirmado">Confirmado</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Zona</div>
                        <select v-model="form.zona_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todas</option>
                            <option v-for="z in zonas" :key="z.id" :value="z.id">{{ z.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Ciudad</div>
                        <select v-model="form.localidad" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todas</option>
                            <option v-for="loc in localidades" :key="loc" :value="loc">{{ loc }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Barrio / direccion</div>
                        <input v-model="form.barrio" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Buscar texto" />
                    </div>
                    <div>
                        <button type="button" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm text-gray-800 hover:bg-gray-200" @click="applyFilters">Aplicar</button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Resumen por zona</h3>
                    <div class="mt-4 space-y-2">
                        <div v-for="row in summaryByZona || []" :key="row.label" class="flex items-center justify-between rounded border border-gray-200 px-3 py-2 text-sm">
                            <div>{{ row.label }} <span class="text-gray-500">({{ row.cantidad }})</span></div>
                            <div class="font-medium text-gray-900">{{ row.total }}</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Resumen por ciudad</h3>
                    <div class="mt-4 space-y-2">
                        <div v-for="row in summaryByLocalidad || []" :key="row.label" class="flex items-center justify-between rounded border border-gray-200 px-3 py-2 text-sm">
                            <div>{{ row.label }} <span class="text-gray-500">({{ row.cantidad }})</span></div>
                            <div class="font-medium text-gray-900">{{ row.total }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <p class="text-sm text-gray-600">Pre-recibos generados desde Repartos para confirmar en deposito.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deposito</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in preRecibos.data" :key="p.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatFecha(p.fecha) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.hoja_ruta?.deposito?.nombre || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ p.cuenta?.tercero?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">CUIT {{ p.cuenta?.tercero?.cuit || '-' }} · Nro {{ p.cuenta?.numero_cliente || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ p.cuenta?.zona?.nombre || 'Sin zona' }} · {{ p.cuenta?.localidad || 'Sin ciudad' }} · {{ p.cuenta?.barrio || 'Sin barrio' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.estado }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.moneda }} {{ p.total }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.moneda === 'ARS' ? '-' : p.cotizacion_ars }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <Link class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.show', p.id)">Ver</Link>
                                </td>
                            </tr>

                            <tr v-if="!preRecibos.data.length">
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">No hay pre-recibos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="preRecibos.links?.length" class="p-6 border-t border-gray-200 flex flex-wrap gap-2">
                    <Link
                        v-for="link in preRecibos.links"
                        :key="link.label"
                        class="px-3 py-2 text-sm rounded border"
                        :class="link.active ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'"
                        :href="link.url || ''"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
