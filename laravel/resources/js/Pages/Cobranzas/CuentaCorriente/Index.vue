<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { formatNum } from '@/Utils/format.js';

const props = defineProps({
    cuentas: Array,
    totales: Object,
    cutoff: String,
    filters: Object,
    zonas: Array,
    localidades: Array,
    barrios: Array,
    cobradores: Array,
    reportMeta: Object,
});

const form = useForm({
    filtro: props.filters?.filtro || 'todos',
    desde: props.filters?.desde || '',
    hasta: props.filters?.hasta || '',
    zona_id: props.filters?.zona_id || '',
    localidad: props.filters?.localidad || '',
    barrio: props.filters?.barrio || '',
    cobrador_user_id: props.filters?.cobrador_user_id || '',
});

const applyFilters = () => {
    router.get(route('cobranzas.ctacte.index'), {
        filtro: form.filtro || 'todos',
        desde: form.desde || null,
        hasta: form.hasta || null,
        zona_id: form.zona_id || null,
        localidad: form.localidad || null,
        barrio: form.barrio || null,
        cobrador_user_id: form.cobrador_user_id || null,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const expandedRows = ref({});
const selectedIds = ref(new Set());

const toggleSelect = (id) => {
    const s = new Set(selectedIds.value);
    if (s.has(id)) {
        s.delete(id);
    } else {
        s.add(id);
    }
    selectedIds.value = s;
};

const selectAll = () => {
    if (selectedIds.value.size === props.cuentas.length) {
        selectedIds.value = new Set();
    } else {
        selectedIds.value = new Set(props.cuentas.map((c) => c.id));
    }
};

const printSelected = () => {
    const ids = Array.from(selectedIds.value);
    if (!ids.length) return;
    window.open(route('cobranzas.ctacte.print-selected', { ids: ids.join(',') }), '_blank');
};

const toggleExpand = (id) => {
    expandedRows.value[id] = !expandedRows.value[id];
};

const sumBy = (arr, key) => arr.reduce((a, c) => a + Number(c[key] || 0), 0);
</script>

<template>
    <AppLayout title="Cobranzas / Cuentas corrientes">
        <Head title="Cobranzas / Cuentas corrientes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Cuentas corrientes</h2>
                <div class="flex items-center gap-3 flex-wrap">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.export', { filtro: form.filtro || 'todos', desde: form.desde || null, hasta: form.hasta || null, zona_id: form.zona_id || null, localidad: form.localidad || null, barrio: form.barrio || null, cobrador_user_id: form.cobrador_user_id || null })">Exportar CSV</a>
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.listado-print', { zona_id: form.zona_id || null, localidad: form.localidad || null, barrio: form.barrio || null, cobrador_user_id: form.cobrador_user_id || null })" target="_blank">Imprimir listado</a>
                    <button type="button" class="text-sm text-indigo-600 hover:text-indigo-800" :disabled="!selectedIds.size" @click="printSelected">Imprimir seleccionados ({{ selectedIds.size }})</button>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.index')">Pre-recibos</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.index')">Recibos</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-2 sm:px-6 lg:px-8 space-y-1">
            <div class="bg-white shadow sm:rounded-lg p-2">
                <div class="grid grid-cols-2 sm:grid-cols-8 gap-1.5">
                    <div>
                        <div class="text-[11px] font-medium text-gray-700">Filtro</div>
                        <select v-model="form.filtro" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1">
                            <option value="todos">Todos</option>
                            <option value="vencido">Vencidos +30</option>
                            <option value="con_saldo">Con saldo</option>
                            <option value="sin_saldo">Sin saldo</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-gray-700">Desde</div>
                        <input v-model="form.desde" type="date" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1" />
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-gray-700">Hasta</div>
                        <input v-model="form.hasta" type="date" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1" />
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-gray-700">Zona</div>
                        <select v-model="form.zona_id" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1">
                            <option value="">Todas</option>
                            <option v-for="z in zonas || []" :key="z.id" :value="z.id">{{ z.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-gray-700">Ciudad</div>
                        <select v-model="form.localidad" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1">
                            <option value="">Todas</option>
                            <option v-for="l in localidades || []" :key="l" :value="l">{{ l }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-gray-700">Barrio</div>
                        <select v-model="form.barrio" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1">
                            <option value="">Todos</option>
                            <option v-for="b in barrios || []" :key="b" :value="b">{{ b }}</option>
                        </select>
                    </div>
                    <div v-if="cobradores?.length">
                        <div class="text-[11px] font-medium text-gray-700">Cobrador</div>
                        <select v-model="form.cobrador_user_id" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1">
                            <option value="">Todos</option>
                            <option v-for="c in cobradores" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="inline-flex items-center px-3 py-1 bg-gray-100 border border-gray-200 rounded-md text-xs text-gray-800 hover:bg-gray-200" @click="applyFilters">Aplicar</button>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-2 border-b border-gray-200">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-[11px] text-gray-600">Vencida al {{ cutoff }} se resalta.</p>
                        <p class="text-[11px] font-medium text-gray-900">
                            {{ cuentas.length }} ctas &mdash; Total: ${{ formatNum(totales?.general ?? sumBy(cuentas, 'saldo')) }}
                            <span v-if="totales && Math.abs(totales.docs - totales.general) > 0.01" class="ml-2 text-[10px] font-normal" :class="totales.docs < totales.general ? 'text-amber-600' : 'text-gray-500'">
                                (Pend: ${{ formatNum(totales.docs) }})
                            </span>
                        </p>
                    </div>
                </div>
                <div class="space-y-1 p-1 sm:hidden">
                    <div v-for="c in cuentas" :key="c.id" class="rounded-lg border p-1.5" :class="c.resaltar ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white'">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-1">
                                <input type="checkbox" :checked="selectedIds.has(c.id)" @change="toggleSelect(c.id)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 size-3.5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-gray-900 truncate">{{ c.razon_social || '-' }}</div>
                                <div class="text-[10px] text-gray-500 truncate">CUIT {{ c.cuit || '-' }} · {{ c.localidad || 'Sin ciudad' }} · {{ c.cobrador || '-' }}</div>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <Link class="text-[11px] text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.show', c.id)">Ver</Link>
                                <button v-if="c.docs_count" class="text-[11px] text-indigo-600 hover:text-indigo-800" @click="toggleExpand(c.id)">{{ expandedRows[c.id] ? '−' : '+' + c.docs_count }}</button>
                            </div>
                        </div>
                        <div class="mt-1 grid grid-cols-2 gap-1 text-[11px] leading-none">
                            <div>
                                <div class="text-[9px] uppercase tracking-wider text-gray-500">Saldo</div>
                                <div class="font-mono font-medium text-gray-900">${{ formatNum(c.saldo) }}</div>
                            </div>
                            <div>
                                <div class="text-[9px] uppercase tracking-wider text-gray-500">Vencido +30</div>
                                <div class="font-mono font-medium" :class="c.vencido_30 > 0 ? 'text-red-700' : 'text-gray-900'">${{ formatNum(c.vencido_30) }}</div>
                            </div>
                        </div>
                        <div v-if="expandedRows[c.id] && c.docs_pendientes?.length" class="border-t border-gray-100 pt-1 mt-1">
                            <div class="text-[10px] uppercase tracking-wider text-gray-500 mb-0.5">Pendientes</div>
                            <div v-for="d in c.docs_pendientes" :key="d.id" class="flex items-center justify-between text-[11px] py-0.5 leading-none">
                                <span class="truncate">{{ d.tipo }} {{ d.fecha_emision }}</span>
                                <span class="font-mono font-medium">${{ formatNum(d.total) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-bold border-t border-gray-200 pt-0.5 mt-0.5">
                                <span>Total</span>
                                <span>${{ formatNum(c.docs_total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full w-full divide-y divide-gray-200 text-[10px] leading-none">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" :checked="selectedIds.size === cuentas.length && cuentas.length > 0" :indeterminate="selectedIds.size > 0 && selectedIds.size < cuentas.length" @change="selectAll" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 size-3.5" />
                                </th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Ciudad</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Cobrador</th>
                                <th class="px-1 py-1 text-right text-[10px] font-medium text-gray-500 uppercase tracking-wider">Saldo</th>
                                <th class="px-1 py-1 text-right text-[10px] font-medium text-gray-500 uppercase tracking-wider">Vencido +30</th>
                                <th class="px-1 py-1 text-center text-[10px] font-medium text-gray-500 uppercase tracking-wider">Docs</th>
                                <th class="sticky right-0 bg-gray-50 px-1 py-1 text-right text-[10px] font-medium text-gray-500 uppercase tracking-wider">Acc</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="c in cuentas" :key="c.id">
                                <tr :class="c.resaltar ? 'bg-red-50' : 'hover:bg-gray-50'" class="leading-none">
                                    <td class="px-1 py-1 text-gray-700">
                                        <input type="checkbox" :checked="selectedIds.has(c.id)" @change="toggleSelect(c.id)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 size-3.5" />
                                    </td>
                                    <td class="px-1 py-1 text-[10px] text-gray-700">
                                        <div class="font-medium text-gray-900 truncate max-w-[160px] leading-none">{{ c.razon_social || '-' }}</div>
                                        <div class="text-[9px] text-gray-500 leading-none">CUIT {{ c.cuit || '-' }} · {{ c.numero_cliente || '-' }}</div>
                                    </td>
                                    <td class="px-1 py-1 text-[10px] text-gray-700 max-w-[90px] truncate">{{ c.localidad || 'Sin ciudad' }}</td>
                                    <td class="px-1 py-1 text-[10px] text-gray-700 max-w-[80px] truncate">{{ c.cobrador || '-' }}</td>
                                    <td class="px-1 py-1 text-[10px] text-gray-700 font-mono text-right">${{ formatNum(c.saldo) }}</td>
                                    <td class="px-1 py-1 text-[10px] font-mono font-medium text-right" :class="c.vencido_30 > 0 ? 'text-red-700' : 'text-gray-700'">
                                        <span v-if="c.vencido_30 > 0" class="inline-flex items-center rounded-full bg-red-100 px-1 py-0 text-[10px] font-medium text-red-800">${{ formatNum(c.vencido_30) }}</span>
                                        <span v-else>${{ formatNum(0) }}</span>
                                    </td>
                                    <td class="px-1 py-1 text-[10px] text-gray-700 text-center">
                                        <button v-if="c.docs_count" class="text-indigo-600 hover:text-indigo-800 text-[11px] font-medium" @click="toggleExpand(c.id)">{{ expandedRows[c.id] ? '−' : '+' + c.docs_count }}</button>
                                        <span v-else class="text-[10px] text-gray-400">0</span>
                                    </td>
                                    <td class="sticky right-0 bg-white px-1 py-1 text-right text-[10px]">
                                        <Link class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.show', c.id)">Ver</Link>
                                    </td>
                                </tr>
                                <tr v-if="expandedRows[c.id] && c.docs_pendientes?.length" :class="c.resaltar ? 'bg-red-50/50' : 'bg-gray-50'">
                                    <td colspan="8" class="px-6 py-3">
                                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Documentos pendientes</div>
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400 uppercase tracking-wider">
                                                    <th class="text-left py-1 pr-4">Tipo</th>
                                                    <th class="text-left py-1 pr-4">Fecha</th>
                                                    <th class="text-left py-1 pr-4">CAE</th>
                                                    <th class="text-right py-1">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="d in c.docs_pendientes" :key="d.id" class="border-t border-gray-200">
                                                    <td class="py-1 pr-4 font-medium text-gray-900">{{ d.tipo }}</td>
                                                    <td class="py-1 pr-4 text-gray-600">{{ d.fecha_emision }}</td>
                                                    <td class="py-1 pr-4 text-gray-600 font-mono">{{ d.arca_cae || '-' }}</td>
                                                    <td class="py-1 text-right font-mono text-gray-900">${{ formatNum(d.total) }}</td>
                                                </tr>
                                                <tr class="border-t border-gray-300 font-bold">
                                                    <td colspan="3" class="py-1 pr-4 text-right text-gray-700">Total pendiente</td>
                                                    <td class="py-1 text-right font-mono text-gray-900">${{ formatNum(c.docs_total) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!cuentas.length">
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">Sin cuentas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
