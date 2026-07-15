<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    asientos: Object,
    cuentasContables: Array,
    filtros: Object,
    totales: Object,
});

const expanded = ref(new Set());

const toggle = (id) => {
    const s = new Set(expanded.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expanded.value = s;
};

const applyFilters = () => {
    router.get(route('finanzas.libro-diario'), {
        fecha_desde: document.getElementById('fecha_desde')?.value || '',
        fecha_hasta: document.getElementById('fecha_hasta')?.value || '',
        cuenta_contable_id: document.getElementById('cuenta_contable_id')?.value || '',
    }, { preserveState: true, replace: true });
};

const refLabel = (tipo) => ({
    comprobante: 'Venta',
    proveedor_comprobante: 'Compra',
    recibo: 'Recibo',
    orden_pago: 'OP',
}[tipo] || tipo);

const fmtFecha = (f) => {
    if (!f) return '';
    const parts = f.split(' ')[0].split('-');
    if (parts.length !== 3) return f;
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
};

const fmtDesc = (d) => {
    if (!d) return '';
    return d.replace(/factura_interna/g, 'factura');
};
</script>

<template>
    <AppLayout title="Libro Diario">
        <Head title="Libro Diario" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Libro Diario</h2>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha desde</label>
                        <input id="fecha_desde" type="date" :value="filtros.fecha_desde" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha hasta</label>
                        <input id="fecha_hasta" type="date" :value="filtros.fecha_hasta" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Cuenta contable</label>
                        <select id="cuenta_contable_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todas</option>
                            <option v-for="c in cuentasContables" :key="c.id" :value="c.id" :selected="filtros.cuenta_contable_id == c.id">{{ c.codigo_completo || c.codigo }} - {{ c.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <button @click="applyFilters" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">Filtrar</button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Asientos</div>
                    <div class="text-lg font-bold text-gray-900">{{ asientos.total }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Total Debe</div>
                    <div class="text-lg font-bold text-green-700">$ {{ totales.debe.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Total Haber</div>
                    <div class="text-lg font-bold text-red-700">$ {{ totales.haber.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripcion</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debe</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Haber</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="asiento in asientos.data" :key="asiento.id">
                                <tr class="hover:bg-gray-50 cursor-pointer" @click="toggle(asiento.id)">
                                    <td class="px-4 py-2 text-sm whitespace-nowrap">{{ fmtFecha(asiento.fecha) }}</td>
                                    <td class="px-4 py-2 text-sm">{{ fmtDesc(asiento.descripcion) }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <span class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ refLabel(asiento.referencia_tipo) }} #{{ asiento.referencia_id }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-mono text-green-700">
                                        $ {{ asiento.lineas.reduce((s, l) => s + parseFloat(l.debe), 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-mono text-red-700">
                                        $ {{ asiento.lineas.reduce((s, l) => s + parseFloat(l.haber), 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-center text-gray-400">{{ expanded.has(asiento.id) ? '▲' : '▼' }}</td>
                                </tr>
                                <tr v-if="expanded.has(asiento.id)">
                                    <td colspan="6" class="px-8 py-2 bg-gray-50">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="text-xs text-gray-500 uppercase">
                                                    <th class="text-left px-2 py-1">Cuenta</th>
                                                    <th class="text-left px-2 py-1">Tercero</th>
                                                    <th class="text-right px-2 py-1">Debe</th>
                                                    <th class="text-right px-2 py-1">Haber</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="linea in asiento.lineas" :key="linea.id" class="border-t border-gray-100">
                                                    <td class="px-2 py-1 text-xs">{{ linea.cuenta_contable?.codigo_completo }} - {{ linea.cuenta_contable?.nombre }}</td>
                                                    <td class="px-2 py-1 text-xs text-gray-500">{{ linea.tercero_cuenta?.tercero?.razon_social || '-' }}</td>
                                                    <td class="px-2 py-1 text-right text-xs font-mono text-green-700">{{ parseFloat(linea.debe) > 0 ? '$ ' + parseFloat(linea.debe).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '' }}</td>
                                                    <td class="px-2 py-1 text-right text-xs font-mono text-red-700">{{ parseFloat(linea.haber) > 0 ? '$ ' + parseFloat(linea.haber).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!asientos.data?.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Sin asientos en este periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-gray-200" v-if="asientos.total > asientos.per_page">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Pág. {{ asientos.current_page }} de {{ asientos.last_page }} ({{ asientos.total }} asientos)</span>
                        <div class="flex gap-2">
                            <Link v-if="asientos.prev_page_url" :href="asientos.prev_page_url" class="px-3 py-1 bg-white border rounded-md hover:bg-gray-50">Anterior</Link>
                            <Link v-if="asientos.next_page_url" :href="asientos.next_page_url" class="px-3 py-1 bg-white border rounded-md hover:bg-gray-50">Siguiente</Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
