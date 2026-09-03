<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    recibos: Object,
    filters: Object,
    zonas: Array,
    localidades: Array,
    summaryByZona: Array,
    summaryByLocalidad: Array,
});

const form = useForm({
    zona_id: props.filters?.zona_id || '',
    localidad: props.filters?.localidad || '',
    barrio: props.filters?.barrio || '',
});

const applyFilters = () => {
    router.get(route('cobranzas.recibos.index'), {
        zona_id: form.zona_id || null,
        localidad: form.localidad || null,
        barrio: form.barrio || null,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const formatFecha = (value) => {
    if (!value) return '-';
    const d = new Date(String(value).slice(0, 10));
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
};

const goToPage = (url) => {
    if (!url) return;
    try {
        const u = new URL(url, window.location.origin);
        const page = u.searchParams.get('page');
        router.get(route('cobranzas.recibos.index'), {
            page: page || 1,
            zona_id: form.zona_id || null,
            localidad: form.localidad || null,
            barrio: form.barrio || null,
        }, { preserveState: true, preserveScroll: true });
    } catch {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

const translateLabel = (label) => {
    if (!label) return '';
    return label.replace(/&laquo;\s*Previous/g, '« Anterior').replace(/Next\s*&raquo;/g, 'Siguiente »').replace(/Previous/g, 'Anterior').replace(/Next/g, 'Siguiente').replace(/&laquo;/g, '«').replace(/&raquo;/g, '»');
};
</script>

<template>
    <AppLayout title="Cobranzas / Recibos">
        <Head title="Cobranzas / Recibos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Recibos</h2>
                <div class="flex items-center gap-3">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.export', { zona_id: form.zona_id || null, localidad: form.localidad || null, barrio: form.barrio || null })">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.index')">Ctas. ctes.</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.index')">Ver pre-recibos</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
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
                    <div class="flex items-end">
                        <button type="button" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm text-gray-800 hover:bg-gray-200" @click="applyFilters">Aplicar</button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <h3 class="text-base font-semibold text-gray-900">Resumen por zona</h3>
                    <div class="mt-4 space-y-2">
                        <div v-for="row in summaryByZona || []" :key="row.label" class="flex items-center justify-between rounded border border-gray-200 px-3 py-2 text-sm">
                            <div>{{ row.label }} <span class="text-gray-500">({{ row.cantidad }})</span></div>
                            <div class="font-medium text-gray-900">{{ row.total }}</div>
                        </div>
                    </div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
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
                    <p class="text-sm text-gray-600">Recibos confirmados con moneda y cotizacion usada.</p>
                </div>

                <div class="space-y-1 p-2 sm:hidden">
                    <div v-for="r in recibos.data" :key="r.id" class="rounded-lg border border-gray-200 bg-white p-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-xs font-semibold text-gray-900">#{{ r.numero_interno || r.id }} · {{ r.cuenta?.tercero?.razon_social || '-' }}</div>
                                <div class="text-[11px] text-gray-500">{{ formatFecha(r.fecha) }} · <span :class="r.estado === 'anulada' ? 'text-red-700 font-medium' : 'text-green-700 font-medium'">{{ r.estado }}</span></div>
                            </div>
                            <Link class="text-xs text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.show', r.id)">Ver</Link>
                        </div>
                        <div class="mt-1 grid grid-cols-1 gap-1 text-xs">
                            <div>
                                <div class="text-[10px] uppercase tracking-wider text-gray-500">Zona / Ciudad / Barrio</div>
                                <div class="font-medium text-gray-900 text-[11px]">{{ r.cuenta?.zona?.nombre || 'Sin zona' }} · {{ r.cuenta?.localidad || 'Sin ciudad' }} · {{ r.cuenta?.barrio || 'Sin barrio' }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <div class="text-[10px] uppercase tracking-wider text-gray-500">Total</div>
                                    <div class="font-medium text-gray-900 text-[11px]">{{ r.moneda }} {{ r.total }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase tracking-wider text-gray-500">Cotizacion</div>
                                    <div class="font-medium text-gray-900 text-[11px]">{{ r.moneda === 'ARS' ? '-' : r.cotizacion_ars }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!recibos.data.length" class="rounded-lg border border-gray-200 bg-white px-6 py-4 text-center text-xs text-gray-500">No hay recibos.</div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full w-full divide-y divide-gray-200 text-[11px]">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Número</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-2 py-1.5 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                                <th class="sticky right-0 bg-gray-50 px-2 py-1.5 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="r in recibos.data" :key="r.id" class="hover:bg-gray-50">
                                <td class="px-2 py-1 whitespace-nowrap text-[11px] font-mono text-gray-900">#{{ r.numero_interno || r.id }}</td>
                                <td class="px-2 py-1 whitespace-nowrap text-[11px] text-gray-900">{{ formatFecha(r.fecha) }}</td>
                                <td class="px-2 py-1 text-[11px] text-gray-700">
                                    <div class="font-medium text-gray-900 truncate max-w-[180px]">{{ r.cuenta?.tercero?.razon_social || '-' }}</div>
                                    <div class="text-[10px] text-gray-500">CUIT {{ r.cuenta?.tercero?.cuit || '-' }} · {{ r.cuenta?.zona?.nombre || 'Sin zona' }} · {{ r.cuenta?.localidad || 'Sin ciudad' }}</div>
                                </td>
                                <td class="px-2 py-1 whitespace-nowrap text-[11px]" :class="r.estado === 'anulada' ? 'text-red-700 font-medium' : 'text-green-700 font-medium'">{{ r.estado }}</td>
                                <td class="px-2 py-1 whitespace-nowrap text-[11px] text-gray-700 font-mono">{{ r.moneda }} {{ r.total }}</td>
                                <td class="px-2 py-1 whitespace-nowrap text-[11px] text-gray-700">{{ r.moneda === 'ARS' ? '-' : r.cotizacion_ars }}</td>
                                <td class="sticky right-0 bg-white px-2 py-1 whitespace-nowrap text-right text-[11px]">
                                    <Link class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.show', r.id)">Ver</Link>
                                </td>
                            </tr>
                            <tr v-if="!recibos.data.length">
                                <td colspan="7" class="px-6 py-4 text-center text-xs text-gray-500">No hay recibos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="text-xs text-gray-500">Mostrando {{ recibos.from ?? recibos.data.length }}-{{ recibos.to ?? recibos.data.length }} de {{ recibos.total ?? recibos.data.length }} (pág. {{ recibos.current_page ?? 1 }} de {{ recibos.last_page ?? 1 }})</div>
                    <div class="flex flex-wrap gap-1">
                        <template v-if="recibos.links?.length">
                            <button v-for="link in recibos.links" :key="link.label" :disabled="!link.url" @click="goToPage(link.url)" v-html="translateLabel(link.label)" :class="['px-3 py-1 text-xs rounded border', link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 hover:bg-gray-50', !link.url ? 'opacity-50 cursor-not-allowed' : '']" />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
