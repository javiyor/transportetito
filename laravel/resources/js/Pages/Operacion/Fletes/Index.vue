<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
const selectedItems = ref(new Map());
const selectedCount = computed(() => selectedItems.value.size);
const allVisibleSelected = computed(() => props.pedidos.data.length > 0
    && props.pedidos.data.every((pedido) => selectedItems.value.has(pedido.id)));

const toggleSelection = (pedido, checked) => {
    const next = new Map(selectedItems.value);
    if (checked) next.set(pedido.id, pedido);
    else next.delete(pedido.id);
    selectedItems.value = next;
};

const toggleVisibleSelection = (event) => {
    const next = new Map(selectedItems.value);
    for (const pedido of props.pedidos.data) {
        if (event.target.checked) next.set(pedido.id, pedido);
        else next.delete(pedido.id);
    }
    selectedItems.value = next;
};

const applyFilters = () => {
    selectedItems.value = new Map();
    router.get(route('operacion.fletes.index'), {
        ...(form.desde && { desde: form.desde }),
        ...(form.hasta && { hasta: form.hasta }),
        ...(form.estado && { estado: form.estado }),
        ...(form.remitente && { remitente: form.remitente }),
        ...(form.destinatario && { destinatario: form.destinatario }),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const clearFilters = () => {
    selectedItems.value = new Map();
    form.reset();
    router.get(route('operacion.fletes.index'), {}, { preserveState: true, preserveScroll: true, replace: true });
};

const goToPage = (url) => {
    if (!url) return;
    try {
        const u = new URL(url, window.location.origin);
        const page = u.searchParams.get('page');
        router.get(route('operacion.fletes.index'), {
            ...(form.desde && { desde: form.desde }),
            ...(form.hasta && { hasta: form.hasta }),
            ...(form.estado && { estado: form.estado }),
            ...(form.remitente && { remitente: form.remitente }),
            ...(form.destinatario && { destinatario: form.destinatario }),
            ...(page && { page }),
        }, { preserveState: true, preserveScroll: true });
    } catch {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
};

const exportExcel = () => {
    const params = new URLSearchParams();
    if (props.filtros.desde) params.set('desde', props.filtros.desde);
    if (props.filtros.hasta) params.set('hasta', props.filtros.hasta);
    if (props.filtros.estado) params.set('estado', props.filtros.estado);
    if (props.filtros.remitente) params.set('remitente', props.filtros.remitente);
    if (props.filtros.destinatario) params.set('destinatario', props.filtros.destinatario);
    params.set('export', 'excel');
    if (selectedItems.value.size) {
        params.set('ids', [...selectedItems.value.keys()].join(','));
    }
    window.open(route('operacion.fletes.index') + '?' + params.toString(), '_blank');
};

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[char]));

const printSelected = () => {
    const rows = [...selectedItems.value.values()];
    if (!rows.length) return;

    const printWindow = window.open('', '_blank');
    if (!printWindow) return;

    const columns = ['Factura ARCA', 'Empresa', 'Remitente', 'Destinatario', 'Destino', 'Bultos', 'Palets', 'Facturado', 'Estado', 'Fecha', 'Manifiesto', 'Hoja', 'Comprobante'];
    const tableRows = rows.map((pedido) => {
        const values = [
            pedido.comprobante_arca_numero,
            pedido.empresa,
            pedido.remitente,
            pedido.destinatario,
            pedido.destino,
            pedido.bultos,
            pedido.palets,
            pedido.valor_facturado == null ? '' : `$${formatImporte(pedido.valor_facturado)}`,
            estadoLabel(pedido.estado),
            pedido.fecha,
            pedido.manifiesto_id ? `#${pedido.manifiesto_id}` : '',
            pedido.hoja_ruta_id ? `#${pedido.hoja_ruta_id}` : '',
            `${pedido.comprobante_tipo || ''} ${pedido.comprobante_numero || ''}`.trim(),
        ];
        return `<tr>${values.map((value) => `<td>${escapeHtml(value)}</td>`).join('')}</tr>`;
    }).join('');

    printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Fletes seleccionados</title><style>body{font-family:Arial,sans-serif;margin:18px}h1{font-size:18px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:5px;text-align:left;font-size:10px}th{background:#eee}@media print{body{margin:0}}</style></head><body><h1>Fletes seleccionados</h1><table><thead><tr>${columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('')}</tr></thead><tbody>${tableRows}</tbody></table></body></html>`);
    printWindow.document.close();
    printWindow.focus();
    window.setTimeout(() => printWindow.print(), 250);
};

const estadoLabel = (estado) => ({
    en_deposito: 'En depósito',
    en_viaje: 'En viaje',
    en_reparto: 'En reparto',
    facturado: 'Facturado',
    entregado: 'Entregado',
    entregado_con_diferencia: 'Entregado con diferencia',
    no_entregado: 'No entregado',
    pendiente: 'Pendiente',
}[estado] || estado || '-');
const formatImporte = (value) => Number(value || 0).toLocaleString('es-AR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
</script>

<template>
    <AppLayout title="Operacion / Fletes">
        <Head title="Operacion / Fletes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Consulta de Fletes</h2>
                <div class="flex items-center gap-2">
                    <button type="button" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-50" :disabled="!selectedCount" @click="printSelected">Imprimir seleccionados ({{ selectedCount }})</button>
                    <button type="button" class="inline-flex items-center px-3 py-1.5 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700" @click="exportExcel">Excel{{ selectedCount ? ` (${selectedCount})` : '' }}</button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-2 sm:px-6 lg:px-8 space-y-1">
            <div class="bg-white shadow sm:rounded-lg p-2">
                <div class="grid grid-cols-2 sm:grid-cols-6 gap-1.5">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700">Desde</label>
                        <input v-model="form.desde" type="date" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700">Hasta</label>
                        <input v-model="form.hasta" type="date" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700">Estado</label>
                        <select v-model="form.estado" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1">
                            <option value="">Todos</option>
                            <option v-for="e in estados" :key="e" :value="e">{{ estadoLabel(e) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700">Remitente</label>
                        <input v-model="form.remitente" type="text" placeholder="Buscar..." class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1" />
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700">Destinatario</label>
                        <input v-model="form.destinatario" type="text" placeholder="Buscar..." class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-[11px] py-1" />
                    </div>
                    <div class="flex gap-1 items-end">
                        <button type="button" class="inline-flex items-center px-3 py-1 bg-gray-100 border border-gray-200 rounded-md text-xs text-gray-800 hover:bg-gray-200" @click="applyFilters">Aplicar</button>
                        <button type="button" class="inline-flex items-center px-3 py-1 bg-white border border-gray-200 rounded-md text-xs text-gray-600 hover:bg-gray-50" @click="clearFilters">Limpiar</button>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-[10px] leading-none">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-1 py-1 text-center"><input type="checkbox" class="size-3.5 rounded border-gray-300 text-indigo-600" :checked="allVisibleSelected" aria-label="Seleccionar fletes visibles" @change="toggleVisibleSelection" /></th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Factura ARCA</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Remitente</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Destinatario</th>
                                <th class="px-1 py-1 text-center text-[10px] font-medium text-gray-500 uppercase tracking-wider">Bult</th>
                                <th class="px-1 py-1 text-center text-[10px] font-medium text-gray-500 uppercase tracking-wider">Pal</th>
                                <th class="px-1 py-1 text-right text-[10px] font-medium text-gray-500 uppercase tracking-wider">Facturado</th>
                                <th class="px-1 py-1 text-center text-[10px] font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-1 py-1 text-center text-[10px] font-medium text-gray-500 uppercase tracking-wider">Manif</th>
                                <th class="px-1 py-1 text-center text-[10px] font-medium text-gray-500 uppercase tracking-wider">Hoja</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Comprob</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in pedidos.data" :key="p.id" class="hover:bg-gray-50 leading-none">
                                <td class="px-1 py-0.5 text-center"><input type="checkbox" class="size-3.5 rounded border-gray-300 text-indigo-600" :checked="selectedItems.has(p.id)" :aria-label="`Seleccionar flete ${p.comprobante_arca_numero || p.id}`" @change="toggleSelection(p, $event.target.checked)" /></td>
                                <td class="px-1 py-0.5 text-[10px] font-mono text-gray-900 whitespace-nowrap">{{ p.comprobante_arca_numero || '—' }}</td>
                                <td class="px-1 py-0.5 text-[10px] text-gray-700 max-w-[90px] truncate" :title="p.empresa">{{ p.empresa || '—' }}</td>
                                <td class="px-1 py-0.5 text-[10px] text-gray-700 max-w-[110px] truncate" :title="p.remitente">{{ p.remitente || '—' }}</td>
                                <td class="px-1 py-0.5 text-[10px] text-gray-700 max-w-[110px] truncate" :title="p.destinatario">{{ p.destinatario || '—' }}</td>
                                <td class="px-1 py-0.5 text-[10px] text-gray-700 text-center">{{ p.bultos }}</td>
                                <td class="px-1 py-0.5 text-[10px] text-gray-700 text-center">{{ p.palets }}</td>
                                <td class="px-1 py-0.5 text-[10px] font-mono text-right">{{ p.valor_facturado == null ? '' : `$${formatImporte(p.valor_facturado)}` }}</td>
                                <td class="px-1 py-0.5 text-[10px] text-center">
                                    <span class="inline-flex items-center px-1 py-0 rounded-full text-[9px] font-medium" :class="estadoClass(p.estado)">{{ estadoLabel(p.estado) }}</span>
                                </td>
                                <td class="px-1 py-0.5 text-[10px] text-gray-600 whitespace-nowrap">{{ p.fecha }}</td>
                                <td class="px-1 py-0.5 text-[10px] text-center">
                                    <Link v-if="p.manifiesto_id" :href="route('operacion.manifiestos.show', p.manifiesto_id)" class="text-indigo-600 hover:text-indigo-800">#{{ p.manifiesto_id }}</Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-1 py-0.5 text-[10px] text-center">
                                    <Link v-if="p.hoja_ruta_id" :href="route('operacion.repartos.hojas.show', p.hoja_ruta_id)" class="text-indigo-600 hover:text-indigo-800">#{{ p.hoja_ruta_id }}</Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-1 py-0.5 text-[10px]">
                                    <Link v-if="p.comprobante_id" :href="route('operacion.comprobantes.show', p.comprobante_id)" class="text-indigo-600 hover:text-indigo-800 whitespace-nowrap">{{ p.comprobante_tipo }} {{ p.comprobante_numero || '#' + p.comprobante_id }}</Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="!pedidos.data?.length">
                                <td colspan="13" class="px-1 py-4 text-[10px] text-gray-400 text-center">No se encontraron fletes en el periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pedidos.total > pedidos.per_page" class="px-2 py-1 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-[11px] text-gray-600">
                        Mostrando {{ pedidos.from }}–{{ pedidos.to }} de {{ pedidos.total }} (pág. {{ pedidos.current_page }} de {{ pedidos.last_page }})
                    </div>
                    <div class="flex gap-1">
                        <button :disabled="!pedidos.prev_page_url" type="button" class="inline-flex items-center px-2 py-1 bg-white border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed" @click="goToPage(pedidos.prev_page_url)">Anterior</button>
                        <button :disabled="!pedidos.next_page_url" type="button" class="inline-flex items-center px-2 py-1 bg-white border border-gray-300 rounded-md text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed" @click="goToPage(pedidos.next_page_url)">Siguiente</button>
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
                en_reparto: 'bg-indigo-100 text-indigo-800',
                entregado: 'bg-green-100 text-green-800',
                entregado_con_diferencia: 'bg-blue-100 text-blue-800',
                no_entregado: 'bg-red-100 text-red-800',
                pendiente: 'bg-gray-100 text-gray-800',
            };
            return map[estado] || 'bg-gray-100 text-gray-800';
        },
    },
};
</script>
