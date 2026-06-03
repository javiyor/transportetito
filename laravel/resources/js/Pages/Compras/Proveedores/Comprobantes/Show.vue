<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    comprobante: Object,
    ordenesPago: Array,
    pagado: Number,
    saldo: Number,
});

const formatFecha = (value) => value ? String(value).slice(0, 10) : '-';
</script>

<template>
    <AppLayout :title="`Comprobante proveedor #${comprobante.id}`">
        <Head :title="`Comprobante proveedor #${comprobante.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Comprobante proveedor #{{ comprobante.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">{{ comprobante.cuenta?.tercero?.razon_social || '-' }}</div>
                </div>
                <Link :href="route('compras.proveedores.comprobantes.index')"><SecondaryButton>Volver</SecondaryButton></Link>
            </div>
        </template>

        <div class="max-w-5xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div><div class="text-xs text-gray-500">Tipo / Numero</div><div class="text-sm font-medium text-gray-900">{{ comprobante.tipo }} · {{ comprobante.numero || '-' }}</div></div>
                <div><div class="text-xs text-gray-500">Fecha / Vto</div><div class="text-sm font-medium text-gray-900">{{ formatFecha(comprobante.fecha_emision) }} · {{ formatFecha(comprobante.fecha_vencimiento) }}</div></div>
                <div><div class="text-xs text-gray-500">Total</div><div class="text-sm font-medium text-gray-900">{{ comprobante.moneda }} {{ comprobante.total }}</div></div>
                <div><div class="text-xs text-gray-500">Pagado</div><div class="text-sm font-medium text-gray-900">{{ comprobante.moneda }} {{ pagado }}</div></div>
                <div><div class="text-xs text-gray-500">Saldo</div><div class="text-sm font-medium text-gray-900">{{ comprobante.moneda }} {{ saldo }}</div></div>
                <div><div class="text-xs text-gray-500">Obs.</div><div class="text-sm font-medium text-gray-900">{{ comprobante.observacion || '-' }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Ordenes de pago aplicadas</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="o in ordenesPago" :key="o.id"><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(o.fecha) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ o.medio || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ o.moneda }} {{ o.total }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ o.observacion || '-' }}</td></tr></tbody></table></div>
            </div>
        </div>
    </AppLayout>
</template>
