<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed } from 'vue';

const props = defineProps({
    hoja: Object,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const closeForm = useForm({ confirm: true });

const cerrar = () => {
    closeForm.post(route('operacion.repartos.hojas.cerrar', props.hoja.id));
};

const setEntrega = (itemId, estado) => {
    useForm({ estado_entrega: estado }).put(route('operacion.repartos.hojas.items.update', [props.hoja.id, itemId]), { preserveScroll: true });
};

const setOrden = (itemId, orden) => {
    useForm({ orden }).put(route('operacion.repartos.hojas.items.update', [props.hoja.id, itemId]), { preserveScroll: true });
};

const setObs = (itemId, observacion_operador) => {
    useForm({ observacion_operador }).put(route('operacion.repartos.hojas.items.update', [props.hoja.id, itemId]), { preserveScroll: true });
};

const stats = computed(() => {
    const items = props.hoja.items || [];
    const total = items.reduce((acc, it) => acc + Number(it.comprobante?.total || 0), 0);
    const entregados = items.filter((it) => it.estado_entrega === 'entregado').length;
    const noEntregados = items.filter((it) => it.estado_entrega === 'no_entregado').length;
    const pendientes = items.filter((it) => it.estado_entrega === 'pendiente').length;
    return { total: total.toFixed(2), entregados, noEntregados, pendientes, count: items.length };
});
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
                    <Link :href="route('operacion.repartos.hojas.print', hoja.id)" target="_blank" class="inline-flex">
                        <SecondaryButton>Imprimir</SecondaryButton>
                    </Link>
                    <PrimaryButton v-if="hoja.estado !== 'cerrada'" :disabled="closeForm.processing" @click.prevent="cerrar">Cerrar hoja</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded mb-6">
                {{ flashSuccess }}
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6 mb-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Items</div>
                        <div class="text-sm font-medium text-gray-900">{{ stats.count }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Pendientes</div>
                        <div class="text-sm font-medium text-gray-900">{{ stats.pendientes }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Entregados</div>
                        <div class="text-sm font-medium text-gray-900">{{ stats.entregados }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Total</div>
                        <div class="text-sm font-medium text-gray-900">{{ hoja.items?.[0]?.comprobante?.moneda || 'ARS' }} {{ stats.total }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orden</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Factura</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs</th>
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
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <TextInput
                                        :disabled="hoja.estado === 'cerrada'"
                                        :model-value="it.observacion_operador || ''"
                                        type="text"
                                        class="block w-full text-sm"
                                        placeholder="(opcional)"
                                        @change="setObs(it.id, $event.target.value)"
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <div class="flex justify-end gap-2">
                                        <TextInput
                                            v-if="hoja.estado !== 'cerrada'"
                                            :model-value="it.orden"
                                            type="number"
                                            min="1"
                                            class="w-20 text-xs"
                                            @change="setOrden(it.id, parseInt($event.target.value, 10))"
                                        />
                                        <SecondaryButton class="text-xs" v-if="hoja.estado !== 'cerrada'" @click.prevent="setEntrega(it.id, 'entregado')">Entregado</SecondaryButton>
                                        <SecondaryButton class="text-xs" v-if="hoja.estado !== 'cerrada'" @click.prevent="setEntrega(it.id, 'no_entregado')">No entregado</SecondaryButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!hoja.items?.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Sin items.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
