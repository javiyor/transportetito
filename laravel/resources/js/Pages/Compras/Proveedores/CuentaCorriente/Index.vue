<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    cuentas: Array,
});
</script>

<template>
    <AppLayout title="Compras / Proveedores / Cuenta corriente">
        <Head title="Compras / Proveedores / Cuenta corriente" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compras / Proveedores / Cuenta corriente</h2>
                <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.index')">Comprobantes proveedor</Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><p class="text-sm text-gray-600">Saldos de proveedores listos para futuras ordenes de pago.</p></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CUIT</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in cuentas" :key="c.id"><td class="px-6 py-4 text-sm text-gray-700">{{ c.razon_social || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.cuit || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.saldo }}</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
