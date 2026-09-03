<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    pasivos: Array,
    totalPendiente: Number,
});

const formatNum = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <AppLayout title="Pasivos pendientes">
        <Head title="Pasivos pendientes" />
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pasivos pendientes de pago</h2>
                <Link :href="route('finanzas.egresos.index')" class="text-sm text-indigo-600 hover:text-indigo-800">Volver a Egresos</Link>
            </div>
        </template>
        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div class="bg-white shadow sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><div class="text-xs text-gray-500">Cuentas con saldo</div><div class="text-sm font-medium text-gray-900">{{ pasivos.length }}</div></div>
                <div><div class="text-xs text-gray-500">Total pendiente</div><div class="text-sm font-medium text-red-700">$ {{ formatNum(totalPendiente) }}</div></div>
            </div>
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-[11px]">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase">Cuenta</th>
                                <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase">Debe</th>
                                <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase">Haber</th>
                                <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase">Saldo pendiente</th>
                                <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in pasivos" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-2 py-1 whitespace-nowrap"><span class="font-mono text-gray-900">{{ p.codigo }}</span> <span class="text-gray-700">{{ p.nombre }}</span></td>
                                <td class="px-2 py-1 text-right font-mono text-gray-700">{{ formatNum(p.debe) }}</td>
                                <td class="px-2 py-1 text-right font-mono text-green-700">{{ formatNum(p.haber) }}</td>
                                <td class="px-2 py-1 text-right font-mono font-semibold text-red-700">{{ formatNum(p.saldo) }}</td>
                                <td class="px-2 py-1 text-right whitespace-nowrap">
                                    <Link :href="route('finanzas.libro-mayor', { cuenta_contable_id: p.id })" class="text-indigo-600 hover:text-indigo-800">Ver mayor</Link>
                                </td>
                            </tr>
                            <tr v-if="!pasivos.length"><td colspan="5" class="px-2 py-4 text-center text-xs text-gray-500">Sin pasivos pendientes.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-200 text-xs text-gray-500">Saldo = Haber - Debe. Solo cuentas <b>pasivo</b> con saldo &gt; 0.01. Detalle de movimientos en Libro Mayor.</div>
            </div>
        </div>
    </AppLayout>
</template>
