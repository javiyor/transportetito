<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    cuentas: Array,
    cuentaSeleccionada: Object,
    movimientos: Object,
    saldo: Object,
    filtros: Object,
});

const applyFilters = () => {
    router.get(route('finanzas.libro-mayor'), {
        cuenta_contable_id: document.getElementById('cuenta_id')?.value || '',
        fecha_desde: document.getElementById('fecha_desde')?.value || '',
        fecha_hasta: document.getElementById('fecha_hasta')?.value || '',
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <AppLayout title="Libro Mayor">
        <Head title="Libro Mayor" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Libro Mayor</h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Cuenta contable</label>
                        <select id="cuenta_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Seleccionar...</option>
                            <option v-for="c in cuentas" :key="c.id" :value="c.id" :selected="filtros.cuenta_contable_id == c.id">{{ c.codigo_completo || c.codigo }} - {{ c.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha desde</label>
                        <input id="fecha_desde" type="date" :value="filtros.fecha_desde" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha hasta</label>
                        <input id="fecha_hasta" type="date" :value="filtros.fecha_hasta" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <button @click="applyFilters" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">Consultar</button>
                    </div>
                </div>
            </div>

            <div v-if="cuentaSeleccionada" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-4 sm:col-span-2">
                    <div class="text-xs text-gray-500">Cuenta</div>
                    <div class="text-lg font-bold text-gray-900">{{ cuentaSeleccionada.codigo_completo }} - {{ cuentaSeleccionada.nombre }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Saldo</div>
                    <div class="text-lg font-bold" :class="saldo.saldo >= 0 ? 'text-green-700' : 'text-red-700'">
                        $ {{ Math.abs(saldo.saldo).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}
                        <span class="text-xs font-normal text-gray-500">({{ saldo.naturaleza }})</span>
                    </div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Movimientos</div>
                    <div class="text-lg font-bold text-gray-900">{{ movimientos.total }}</div>
                </div>
            </div>

            <div v-if="movimientos" class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asiento</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripcion</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tercero</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debe</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Haber</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="mov in movimientos.data" :key="mov.id" class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm whitespace-nowrap">{{ mov.asiento?.fecha }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">#{{ mov.asiento_id }}</td>
                                <td class="px-4 py-2 text-sm">{{ mov.descripcion || mov.asiento?.descripcion }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ mov.tercero_cuenta?.tercero?.razon_social || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-right font-mono text-green-700">{{ parseFloat(mov.debe) > 0 ? '$ ' + parseFloat(mov.debe).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '' }}</td>
                                <td class="px-4 py-2 text-sm text-right font-mono text-red-700">{{ parseFloat(mov.haber) > 0 ? '$ ' + parseFloat(mov.haber).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '' }}</td>
                            </tr>
                            <tr v-if="!movimientos.data?.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Sin movimientos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-gray-200" v-if="movimientos.total > movimientos.per_page">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Pág. {{ movimientos.current_page }} de {{ movimientos.last_page }} ({{ movimientos.total }} movs.)</span>
                        <div class="flex gap-2">
                            <Link v-if="movimientos.prev_page_url" :href="movimientos.prev_page_url" class="px-3 py-1 bg-white border rounded-md hover:bg-gray-50">Anterior</Link>
                            <Link v-if="movimientos.next_page_url" :href="movimientos.next_page_url" class="px-3 py-1 bg-white border rounded-md hover:bg-gray-50">Siguiente</Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!movimientos" class="bg-white shadow sm:rounded-lg p-10 text-center text-sm text-gray-500">
                Seleccione una cuenta contable para ver sus movimientos.
            </div>
        </div>
    </AppLayout>
</template>
