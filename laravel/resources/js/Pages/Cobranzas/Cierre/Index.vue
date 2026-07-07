<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatNum } from '@/Utils/format.js';

const props = defineProps({
    desde: String,
    hasta: String,
    ingresosPorMedio: Object,
    totalIngresos: Number,
    preIngresosPorMedio: Object,
    totalPreIngresos: Number,
    egresosPorMedio: Object,
    totalEgresos: Number,
    saldoNeto: Number,
    resumenContable: Array,
    resumenPorMes: Array,
    totalDebe: Number,
    totalHaber: Number,
    cantidadRecibos: Number,
    cantidadPreRecibos: Number,
    cantidadOrdenes: Number,
    cantidadAsientos: Number,
    hojas: Array,
    cantidadHojas: Number,
    totalGeneralHojas: Number,
});

const form = useForm({
    desde: props.desde || '',
    hasta: props.hasta || '',
});

const applyFilters = () => {
    router.get(route('cobranzas.cierre.index'), {
        desde: form.desde || null,
        hasta: form.hasta || null,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const sumBy = (obj) => Object.values(obj).reduce((a, b) => a + Number(b || 0), 0);
</script>

<template>
    <AppLayout title="Cobranzas / Cierre de caja">
        <Head title="Cobranzas / Cierre de caja" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cierre de caja</h2>
                <div class="flex items-center gap-3 flex-wrap">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.cierre.print', { desde: form.desde || null, hasta: form.hasta || null })" target="_blank">Imprimir</a>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">Desde</div>
                        <input v-model="form.desde" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Hasta</div>
                        <input v-model="form.hasta" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 rounded-md text-sm text-gray-800 hover:bg-gray-200" @click="applyFilters">Aplicar</button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Ingresos (Recibos)</h3>
                    <p class="text-xs text-gray-500 mb-3">{{ cantidadRecibos }} recibos confirmados</p>
                    <div v-if="Object.keys(ingresosPorMedio).length" class="space-y-2">
                        <div v-for="(total, medio) in ingresosPorMedio" :key="medio" class="flex items-center justify-between text-sm">
                            <span class="text-gray-700 capitalize">{{ medio }}</span>
                            <span class="font-mono font-medium text-gray-900">${{ formatNum(total) }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 flex items-center justify-between text-sm font-bold">
                            <span>Total ingresos</span>
                            <span class="font-mono text-green-700">${{ Number(totalIngresos).toFixed(2) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">Sin ingresos en el periodo.</p>

                    <h3 class="text-base font-semibold text-gray-900 mt-6 mb-3">Pre-ingresos (Pre-recibos)</h3>
                    <p class="text-xs text-gray-500 mb-3">{{ cantidadPreRecibos }} pre-recibos confirmados</p>
                    <div v-if="Object.keys(preIngresosPorMedio).length" class="space-y-2">
                        <div v-for="(total, medio) in preIngresosPorMedio" :key="medio" class="flex items-center justify-between text-sm">
                            <span class="text-gray-700 capitalize">{{ medio }}</span>
                            <span class="font-mono font-medium text-gray-900">${{ formatNum(total) }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 flex items-center justify-between text-sm font-bold">
                            <span>Total pre-ingresos</span>
                            <span class="font-mono text-green-700">${{ Number(totalPreIngresos).toFixed(2) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">Sin pre-ingresos en el periodo.</p>
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Egresos (Ordenes de pago)</h3>
                    <p class="text-xs text-gray-500 mb-3">{{ cantidadOrdenes }} ordenes confirmadas</p>
                    <div v-if="Object.keys(egresosPorMedio).length" class="space-y-2">
                        <div v-for="(total, medio) in egresosPorMedio" :key="medio" class="flex items-center justify-between text-sm">
                            <span class="text-gray-700 capitalize">{{ medio || 'Sin medio' }}</span>
                            <span class="font-mono font-medium text-gray-900">${{ formatNum(total) }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 flex items-center justify-between text-sm font-bold">
                            <span>Total egresos</span>
                            <span class="font-mono text-red-700">${{ Number(totalEgresos).toFixed(2) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">Sin egresos en el periodo.</p>

                    <div class="mt-6 bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between text-sm font-bold">
                            <span class="text-gray-900">Saldo neto</span>
                            <span class="font-mono text-lg" :class="saldoNeto >= 0 ? 'text-green-700' : 'text-red-700'">${{ Number(saldoNeto).toFixed(2) }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">Ingresos - Egresos</div>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Resumen contable</h3>
                    <p class="text-xs text-gray-500 mb-3">{{ cantidadAsientos }} asientos confirmados</p>
                    <div v-if="resumenContable?.length" class="space-y-1 max-h-64 overflow-y-auto">
                        <div v-for="r in resumenContable" :key="r.cuenta" class="flex items-center justify-between text-xs py-1 border-b border-gray-100 last:border-0">
                            <span class="text-gray-700 truncate mr-2">{{ r.cuenta }}</span>
                            <span class="font-mono font-medium whitespace-nowrap" :class="r.saldo >= 0 ? 'text-green-700' : 'text-red-700'">${{ Number(r.saldo).toFixed(2) }}</span>
                        </div>
                        <div class="border-t border-gray-300 pt-2 mt-2 flex items-center justify-between text-xs font-bold">
                            <span>Total Debe</span>
                            <span class="font-mono">${{ Number(totalDebe).toFixed(2) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span>Total Haber</span>
                            <span class="font-mono">${{ Number(totalHaber).toFixed(2) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">Sin asientos contables en el periodo.</p>
                </div>
            </div>

            <div v-if="hojas?.length" class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Control hojas de reparto</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ cantidadHojas }} hojas en el periodo</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chofer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehículo</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cobrado items</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pre-recibos</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="h in hojas" :key="h.id">
                                <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">{{ h.fecha }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ h.chofer || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ h.vehiculo || '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-center">{{ h.cantidad_items }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-right">${{ Number(h.total_items_cobrado).toFixed(2) }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-right">${{ Number(h.total_pre_recibos).toFixed(2) }}</td>
                                <td class="px-4 py-3 text-sm font-mono font-semibold text-right">${{ Number(h.total_general).toFixed(2) }}</td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="h.estado === 'cerrado' ? 'bg-green-100 text-green-800' : h.estado === 'en_viaje' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'">{{ h.estado }}</span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="6" class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">Total general hojas</td>
                                <td class="px-4 py-3 text-sm font-mono font-bold text-right text-gray-900">${{ Number(totalGeneralHojas).toFixed(2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div v-if="resumenPorMes?.length" class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Asientos por mes</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Debe</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Haber</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="r in resumenPorMes" :key="r.mes">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ r.mes }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ r.cantidad }}</td>
                                <td class="px-6 py-4 text-sm text-right font-mono">${{ Number(r.debe).toFixed(2) }}</td>
                                <td class="px-6 py-4 text-sm text-right font-mono">${{ Number(r.haber).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
