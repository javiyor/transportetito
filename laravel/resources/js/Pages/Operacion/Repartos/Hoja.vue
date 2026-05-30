<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    hoja: Object,
});

const closeForm = useForm({ confirm: true });

const cerrar = () => {
    closeForm.post(route('operacion.repartos.hojas.cerrar', props.hoja.id));
};

const setEntrega = (itemId, estado) => {
    useForm({ estado_entrega: estado }).put(route('operacion.repartos.hojas.items.update', [props.hoja.id, itemId]), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :title="`Operacion / Hoja #${hoja.id}`">
        <Head :title="`Operacion / Hoja #${hoja.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Hoja #{{ hoja.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">{{ hoja.fecha }} · {{ hoja.deposito?.nombre || '-' }} · Estado: {{ hoja.estado }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton :href="route('operacion.repartos.facturas')" as="a">Volver</SecondaryButton>
                    <PrimaryButton v-if="hoja.estado !== 'cerrada'" :disabled="closeForm.processing" @click.prevent="cerrar">Cerrar hoja</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orden</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Factura</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="it in hoja.items" :key="it.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ it.orden }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ it.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">CUIT {{ it.entrega_cuenta?.tercero?.cuit || '-' }} · Nro {{ it.entrega_cuenta?.numero_cliente || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ it.direccion || it.entrega_cuenta?.direccion || '' }} {{ it.localidad || it.entrega_cuenta?.localidad ? '· ' + (it.localidad || it.entrega_cuenta?.localidad) : '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.comprobante?.moneda }} {{ it.comprobante?.total }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.estado_entrega }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <div class="flex justify-end gap-2">
                                        <SecondaryButton class="text-xs" v-if="hoja.estado !== 'cerrada'" @click.prevent="setEntrega(it.id, 'entregado')">Entregado</SecondaryButton>
                                        <SecondaryButton class="text-xs" v-if="hoja.estado !== 'cerrada'" @click.prevent="setEntrega(it.id, 'no_entregado')">No entregado</SecondaryButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!hoja.items?.length">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Sin items.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
