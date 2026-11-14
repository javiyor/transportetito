<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatNum } from '@/Utils/format.js';
import { computed } from 'vue';

const props = defineProps({
    preRecibos: Object,
    filters: Object,
    zonas: Array,
    localidades: Array,
    cobradores: Array,
    summaryByZona: Array,
    summaryByLocalidad: Array,
    summaryByMedio: Array,
});

const page = usePage();
const form = useForm({
    estado: props.filters?.estado || 'borrador',
    zona_id: props.filters?.zona_id || '',
    localidad: props.filters?.localidad || '',
    barrio: props.filters?.barrio || '',
    cobrador_user_id: props.filters?.cobrador_user_id || '',
});
const loteForm = useForm({ pre_recibo_ids: [] });
const confirmables = computed(() => (props.preRecibos.data || []).filter((preRecibo) => preRecibo.confirmable));
const todosSeleccionados = computed(() => confirmables.value.length > 0 && confirmables.value.every((preRecibo) => loteForm.pre_recibo_ids.includes(preRecibo.id)));

const togglePreRecibo = (id) => {
    const seleccionados = new Set(loteForm.pre_recibo_ids);
    seleccionados.has(id) ? seleccionados.delete(id) : seleccionados.add(id);
    loteForm.pre_recibo_ids = [...seleccionados];
};

const toggleTodos = (checked) => {
    loteForm.pre_recibo_ids = checked ? confirmables.value.map((preRecibo) => preRecibo.id) : [];
};

const applyFilters = () => {
    router.get(route('cobranzas.pre-recibos.index'), {
        estado: form.estado,
        zona_id: form.zona_id || null,
        localidad: form.localidad || null,
        barrio: form.barrio || null,
        cobrador_user_id: form.cobrador_user_id || null,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const confirmarSeleccionados = () => {
    if (!loteForm.pre_recibo_ids.length || loteForm.processing) return;
    if (!window.confirm(`¿Confirmar ${loteForm.pre_recibo_ids.length} pre-recibo(s) listos y generar sus recibos definitivos?`)) return;

    loteForm.post(route('cobranzas.pre-recibos.confirmar-masivo'), {
        preserveScroll: true,
        onSuccess: () => loteForm.reset('pre_recibo_ids'),
    });
};

const medioLabel = (medio) => ({
    ef: 'Efectivo',
    efectivo: 'Efectivo',
    cheque: 'Cheque',
    cheque_tercero: 'Cheque',
    transf: 'Transferencia',
    transferencia: 'Transferencia',
    cuenta_corriente: 'Cuenta corriente',
})[medio] || medio || 'Sin especificar';

const formatFecha = (value) => {
    if (!value) return '-';
    const d = new Date(String(value).slice(0, 10));
    const dd = String(d.getDate()).padStart(2, '0'); const mm = String(d.getMonth() + 1).padStart(2, '0'); const yyyy = d.getFullYear(); return `${dd}-${mm}-${yyyy}`;
};
</script>

<template>
    <AppLayout title="Cobranzas / Pre-recibos">
        <Head title="Cobranzas / Pre-recibos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Pre-recibos</h2>
                <div class="flex items-center gap-3">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.export', { estado: form.estado, zona_id: form.zona_id || null, localidad: form.localidad || null, barrio: form.barrio || null, cobrador_user_id: form.cobrador_user_id || null })">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.index')">Ctas. ctes.</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.index')">Ver recibos</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8">
            <div v-if="page.props.tt?.flash?.success" class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ page.props.tt.flash.success }}</div>
            <div v-if="page.props.tt?.flash?.error" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ page.props.tt.flash.error }}</div>

            <div class="bg-white shadow sm:rounded-lg p-4 mb-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-4">
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
                        <div class="text-sm font-medium text-gray-900">Cobrador</div>
                        <select v-model="form.cobrador_user_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todos</option>
                            <option v-for="cobrador in cobradores" :key="cobrador.id" :value="cobrador.id">{{ cobrador.name }}</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm text-gray-800 hover:bg-gray-200" @click="applyFilters">Aplicar</button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-6">
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
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <h3 class="text-base font-semibold text-gray-900">Resumen de cobros por tipo</h3>
                    <div class="mt-4 space-y-2">
                        <div v-for="row in summaryByMedio || []" :key="row.medio" class="flex items-center justify-between rounded border border-gray-200 px-3 py-2 text-sm">
                            <div>{{ medioLabel(row.medio) }} <span class="text-gray-500">({{ row.cantidad }})</span></div>
                            <div class="font-medium text-gray-900">{{ row.moneda }} {{ formatNum(row.total) }}</div>
                        </div>
                        <div v-if="!summaryByMedio?.length" class="text-sm text-gray-500">Sin cobros registrados.</div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-gray-600">Pre-recibos generados desde Repartos para confirmar en depósito.</p>
                        <PrimaryButton v-if="form.estado === 'borrador'" :disabled="loteForm.processing || !loteForm.pre_recibo_ids.length" @click="confirmarSeleccionados">
                            {{ loteForm.processing ? 'Confirmando...' : `Confirmar seleccionados (${loteForm.pre_recibo_ids.length})` }}
                        </PrimaryButton>
                    </div>
                </div>

                <div class="space-y-4 p-4 sm:hidden">
                    <div v-for="p in preRecibos.data" :key="p.id" class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ p.cuenta?.tercero?.razon_social || '-' }}</div>
                                <div class="text-xs text-gray-500">{{ formatFecha(p.fecha) }} · {{ p.estado }}</div>
                                <div v-if="p.confirmable" class="text-[11px] text-green-700">Listo para confirmar</div>
                                <div v-else-if="p.estado === 'borrador'" class="text-[11px] text-red-700">{{ (p.errores_confirmacion || []).join(' ') }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-if="form.estado === 'borrador' && p.confirmable" type="checkbox" :checked="loteForm.pre_recibo_ids.includes(p.id)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @change="togglePreRecibo(p.id)" />
                                <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.show', p.id)">Ver</Link>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Deposito</div>
                                <div class="font-medium text-gray-900">{{ p.hoja_ruta?.deposito?.nombre || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Zona / Ciudad / Barrio</div>
                                <div class="font-medium text-gray-900">{{ p.cuenta?.zona?.nombre || 'Sin zona' }} · {{ p.cuenta?.localidad || 'Sin ciudad' }} · {{ p.cuenta?.barrio || 'Sin barrio' }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Total</div>
                                    <div class="font-medium text-gray-900">{{ p.moneda }} {{ p.total }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Cotizacion</div>
                                    <div class="font-medium text-gray-900">{{ p.moneda === 'ARS' ? '-' : p.cotizacion_ars }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!preRecibos.data.length" class="rounded-lg border border-gray-200 bg-white px-6 py-4 text-center text-sm text-gray-500">No hay pre-recibos.</div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-[1200px] w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input v-if="form.estado === 'borrador'" type="checkbox" :checked="todosSeleccionados" :disabled="!confirmables.length" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @change="toggleTodos($event.target.checked)" />
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deposito</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                                <th class="sticky right-0 bg-gray-50 px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in preRecibos.data" :key="p.id">
                                <td class="px-3 py-4 text-sm">
                                    <input v-if="form.estado === 'borrador' && p.confirmable" type="checkbox" :checked="loteForm.pre_recibo_ids.includes(p.id)" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @change="togglePreRecibo(p.id)" />
                                    <span v-else-if="form.estado === 'borrador'" class="text-[10px] text-red-700" :title="(p.errores_confirmacion || []).join(' ')">Revisar</span>
                                </td>
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
                                <td class="sticky right-0 bg-white px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <Link class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.show', p.id)">Ver</Link>
                                </td>
                            </tr>

                            <tr v-if="!preRecibos.data.length">
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">No hay pre-recibos.</td>
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
