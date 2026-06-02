<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    cuentas: Array,
    cutoff: String,
});
</script>

<template>
    <AppLayout title="Cobranzas / Cuentas corrientes">
        <Head title="Cobranzas / Cuentas corrientes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Cuentas corrientes</h2>
                <div class="flex items-center gap-3">
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.pre-recibos.index')">Pre-recibos</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.index')">Recibos</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <p class="text-sm text-gray-600">Las cuentas con deuda vencida al {{ cutoff }} se resaltan.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zona</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ciudad</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barrio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencido +30</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in cuentas" :key="c.id" :class="c.resaltar ? 'bg-red-50' : ''">
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ c.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">CUIT {{ c.cuit || '-' }} · Nro {{ c.numero_cliente || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.zona || 'Sin zona' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.localidad || 'Sin ciudad' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.barrio || 'Sin barrio' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.saldo }}</td>
                                <td class="px-6 py-4 text-sm font-medium" :class="c.vencido_30 > 0 ? 'text-red-700' : 'text-gray-700'">{{ c.vencido_30 }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <Link class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.ctacte.show', c.id)">Consultar</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
