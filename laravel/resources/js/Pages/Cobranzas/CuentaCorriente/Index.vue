<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    cuentas: Array,
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
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.index')">Pre-recibos</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.index')">Recibos</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">Filtro</div>
                        <select v-model="form.filtro" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="todos">Todos</option>
                            <option value="vencido">Vencidos +30</option>
                            <option value="con_saldo">Con saldo</option>
                            <option value="sin_saldo">Sin saldo</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Desde</div>
                        <input v-model="form.desde" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Hasta</div>
                        <input v-model="form.hasta" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Zona</div>
                        <select v-model="form.zona_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todas</option>
                            <option v-for="z in zonas || []" :key="z.id" :value="z.id">{{ z.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Ciudad</div>
                        <select v-model="form.localidad" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todas</option>
                            <option v-for="l in localidades || []" :key="l" :value="l">{{ l }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Barrio</div>
                        <select v-model="form.barrio" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todos</option>
                            <option v-for="b in barrios || []" :key="b" :value="b">{{ b }}</option>
                        </select>
                    </div>
                    <div v-if="cobradores?.length">
                        <div class="text-sm font-medium text-gray-900">Cobrador</div>
                        <select v-model="form.cobrador_user_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todos</option>
                            <option v-for="c in cobradores" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm text-gray-800 hover:bg-gray-200" @click="applyFilters">Aplicar</button>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm text-gray-600">Las cuentas con deuda vencida al {{ cutoff }} se resaltan.</p>
                        <p class="text-sm font-medium text-gray-900">{{ cuentas.length }} cuentas &mdash; Total: ${{ sumBy(cuentas, 'saldo').toFixed(2) }}</p>
                    </div>
                </div>
                <div class="space-y-4 p-4 sm:hidden">
                    <div v-for="c in cuentas" :key="c.id" class="rounded-lg border p-4" :class="c.resaltar ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white'">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ c.razon_social || '-' }}</div>
                                <div class="text-xs text-gray-500">CUIT {{ c.cuit || '-' }} · Nro {{ c.numero_cliente || '-' }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.show', c.id)">Consultar</Link>
                                <button v-if="c.docs_count" class="text-sm text-indigo-600 hover:text-indigo-800" @click="toggleExpand(c.id)">{{ expandedRows[c.id] ? 'Ocultar' : 'Docs (' + c.docs_count + ')' }}</button>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Zona / Ciudad / Barrio</div>
                                <div class="font-medium text-gray-900">{{ c.zona || 'Sin zona' }} · {{ c.localidad || 'Sin ciudad' }} · {{ c.barrio || 'Sin barrio' }}</div>
                            </div>
                            <div v-if="c.cobrador" class="text-xs text-gray-500">Cobrador: <span class="font-medium text-gray-900">{{ c.cobrador }}</span></div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Saldo total</div>
                                    <div class="font-medium text-gray-900">${{ c.saldo.toFixed ? c.saldo.toFixed(2) : c.saldo }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Vencido +30</div>
                                    <div class="font-medium" :class="c.vencido_30 > 0 ? 'text-red-700' : 'text-gray-900'">${{ c.vencido_30.toFixed ? c.vencido_30.toFixed(2) : c.vencido_30 }}</div>
                                </div>
                            </div>
                            <div v-if="expandedRows[c.id] && c.docs_pendientes?.length" class="border-t border-gray-100 pt-2">
                                <div class="text-xs uppercase tracking-wider text-gray-500 mb-1">Documentos pendientes</div>
                                <div v-for="d in c.docs_pendientes" :key="d.id" class="flex items-center justify-between text-xs py-1">
                                    <span>{{ d.tipo }} {{ d.fecha_emision }}</span>
                                    <span class="font-mono font-medium">${{ Number(d.total).toFixed(2) }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs font-bold border-t border-gray-200 pt-1 mt-1">
                                    <span>Total pendiente</span>
                                    <span>${{ Number(c.docs_total).toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-[1400px] w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zona</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ciudad</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barrio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cobrador</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencido +30</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Docs. pend.</th>
                                <th class="sticky right-0 bg-gray-50 px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="c in cuentas" :key="c.id">
                                <tr :class="c.resaltar ? 'bg-red-50' : ''">
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <div class="font-medium text-gray-900">{{ c.razon_social || '-' }}</div>
                                        <div class="text-xs text-gray-500">CUIT {{ c.cuit || '-' }} · Nro {{ c.numero_cliente || '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ c.zona || 'Sin zona' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ c.localidad || 'Sin ciudad' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ c.barrio || 'Sin barrio' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ c.cobrador || '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 font-mono">${{ c.saldo.toFixed ? c.saldo.toFixed(2) : c.saldo }}</td>
                                    <td class="px-6 py-4 text-sm font-mono font-medium" :class="c.vencido_30 > 0 ? 'text-red-700' : 'text-gray-700'">
                                        <span v-if="c.vencido_30 > 0" class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">${{ c.vencido_30.toFixed ? c.vencido_30.toFixed(2) : c.vencido_30 }}</span>
                                        <span v-else>$0.00</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <button v-if="c.docs_count" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium" @click="toggleExpand(c.id)">{{ expandedRows[c.id] ? 'Ocultar' : c.docs_count + ' doc(s)' }}</button>
                                        <span v-else class="text-xs text-gray-400">0</span>
                                    </td>
                                    <td class="sticky right-0 bg-white px-6 py-4 text-right text-sm">
                                        <Link class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.show', c.id)">Consultar</Link>
                                    </td>
                                </tr>
                                <tr v-if="expandedRows[c.id] && c.docs_pendientes?.length" :class="c.resaltar ? 'bg-red-50/50' : 'bg-gray-50'">
                                    <td colspan="9" class="px-6 py-3">
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
                                                    <td class="py-1 text-right font-mono text-gray-900">${{ Number(d.total).toFixed(2) }}</td>
                                                </tr>
                                                <tr class="border-t border-gray-300 font-bold">
                                                    <td colspan="3" class="py-1 pr-4 text-right text-gray-700">Total pendiente</td>
                                                    <td class="py-1 text-right font-mono text-gray-900">${{ Number(c.docs_total).toFixed(2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!cuentas.length">
                                <td colspan="9" class="px-6 py-10 text-center text-sm text-gray-500">Sin cuentas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
