<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    hoja: Object,
});

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};

const totalImporte = () => {
    return (props.hoja.items || []).reduce((acc, it) => acc + Number(it.comprobante?.total || 0), 0).toFixed(2);
};
</script>

<template>
    <div class="bg-white text-gray-900">
        <Head :title="`Hoja de ruta #${hoja.id}`" />

        <div class="max-w-5xl mx-auto p-8">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <div class="text-xs uppercase tracking-wider text-gray-500">Hoja de ruta</div>
                    <h1 class="mt-1 text-2xl font-semibold">#{{ hoja.id }}</h1>
                    <div class="mt-2 text-sm text-gray-700">
                        {{ formatFecha(hoja.fecha) }} · {{ hoja.deposito?.nombre || '-' }} · Estado: {{ hoja.estado }}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-medium">{{ hoja.empresa?.razon_social || '' }}</div>
                    <div class="text-xs text-gray-600">CUIT {{ hoja.empresa?.cuit || '-' }}</div>
                    <div class="mt-2 text-xs text-gray-600">Total: {{ hoja.items?.[0]?.comprobante?.moneda || 'ARS' }} {{ totalImporte() }}</div>
                </div>
            </div>

            <div class="mt-6 border-t border-gray-200" />

            <table class="mt-6 w-full text-sm">
                <thead>
                    <tr class="text-xs uppercase tracking-wider text-gray-500">
                        <th class="py-2 text-left w-16">Orden</th>
                        <th class="py-2 text-left">Entrega</th>
                        <th class="py-2 text-left">Direccion</th>
                        <th class="py-2 text-left w-28">Factura</th>
                        <th class="py-2 text-left w-28">Total</th>
                        <th class="py-2 text-left w-28">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="it in hoja.items" :key="it.id" class="border-t border-gray-100">
                        <td class="py-2 font-mono">{{ it.orden }}</td>
                        <td class="py-2">
                            <div class="font-medium">{{ it.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                            <div class="text-xs text-gray-600">CUIT {{ it.entrega_cuenta?.tercero?.cuit || '-' }} · Nro {{ it.entrega_cuenta?.numero_cliente || '-' }}</div>
                        </td>
                        <td class="py-2">
                            <div>{{ it.direccion || '' }}</div>
                            <div class="text-xs text-gray-600">{{ it.localidad || '' }} {{ it.cp ? '· ' + it.cp : '' }} {{ it.telefono ? '· ' + it.telefono : '' }}</div>
                        </td>
                        <td class="py-2 font-mono">#{{ it.comprobante_id }}</td>
                        <td class="py-2">{{ it.comprobante?.moneda }} {{ it.comprobante?.total }}</td>
                        <td class="py-2">{{ it.estado_entrega }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style>
@media print {
    @page {
        size: A4;
        margin: 12mm;
    }
}
</style>
