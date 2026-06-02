<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    depositos: Array,
    filters: Object,
    facturas: Array,
});

const form = useForm({
    deposito_id: props.filters?.deposito_id || '',
    fecha: props.filters?.fecha || '',
    tipo: props.filters?.tipo || 'todos',
    comprobante_ids: [],
});

const applyFilters = () => {
    router.get(
        route('operacion.repartos.facturas'),
        { deposito_id: form.deposito_id || null, fecha: form.fecha || null, tipo: form.tipo || 'todos' },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const tipoLabel = (tipo) => {
    if (tipo === 'guia_envio') return 'Guia';
    if (tipo === 'factura_interna') return 'Factura';
    return tipo || '-';
};

const toggleAll = (checked) => {
    form.comprobante_ids = checked ? props.facturas.map((f) => f.id) : [];
};

const createHoja = () => {
    form.post(route('operacion.repartos.hojas.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Operacion / Repartos">
        <Head title="Operacion / Repartos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Repartos</h2>
                <Link :href="route('operacion.manifiestos.index')">
                    <SecondaryButton>Volver</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">Deposito</div>
                        <select v-model="form.deposito_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todos</option>
                            <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Fecha</div>
                        <input v-model="form.fecha" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Tipo</div>
                        <select v-model="form.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="todos">Todos</option>
                            <option value="factura_interna">Facturas</option>
                            <option value="guia_envio">Guias</option>
                        </select>
                    </div>
                    <div class="flex items-end justify-end">
                        <SecondaryButton type="button" @click="applyFilters">Aplicar</SecondaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Comprobantes emitidos (listas)</h3>
                        <p class="mt-1 text-sm text-gray-600">Selecciona facturas o guias para armar hoja de ruta.</p>
                    </div>
                    <PrimaryButton :disabled="form.processing || !form.comprobante_ids.length" @click.prevent="createHoja">
                        Crear hoja
                    </PrimaryButton>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" :checked="form.comprobante_ids.length === facturas.length && facturas.length" @change="toggleAll($event.target.checked)" />
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="f in facturas" :key="f.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Checkbox v-model:checked="form.comprobante_ids" :value="f.id" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ f.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ tipoLabel(f.tipo) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ f.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">CUIT {{ f.entrega_cuenta?.tercero?.cuit || '-' }} · Nro {{ f.entrega_cuenta?.numero_cliente || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ f.entrega_cuenta?.direccion || '' }} {{ f.entrega_cuenta?.localidad ? '· ' + f.entrega_cuenta.localidad : '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ f.moneda }} {{ f.total }}</td>
                            </tr>
                            <tr v-if="!facturas.length">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No hay comprobantes para los filtros seleccionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
