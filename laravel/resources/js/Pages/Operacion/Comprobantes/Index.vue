<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    filters: Object,
    comprobantes: Object,
});

const form = useForm({
    tipo: props.filters?.tipo || 'todos',
    estado: props.filters?.estado || 'todos',
});

const applyFilters = () => {
    router.get(route('operacion.comprobantes.index'), {
        tipo: form.tipo || 'todos',
        estado: form.estado || 'todos',
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const tipoLabel = (tipo) => {
    if (tipo === 'guia_envio') return 'Guia no fiscal';
    if (tipo === 'factura_interna') return 'Factura';
    if (tipo === 'nota_credito_interna') return 'Nota de credito';
    return tipo || '-';
};
</script>

<template>
    <AppLayout title="Operacion / Comprobantes">
        <Head title="Operacion / Comprobantes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Comprobantes</h2>
                <Link :href="route('operacion.manifiestos.index')"><SecondaryButton>Volver</SecondaryButton></Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">Tipo</div>
                        <select v-model="form.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" @change="applyFilters">
                            <option value="todos">Todos</option>
                            <option value="factura_interna">Facturas</option>
                            <option value="guia_envio">Guias</option>
                            <option value="nota_credito_interna">Notas de credito</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Estado</div>
                        <select v-model="form.estado" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" @change="applyFilters">
                            <option value="todos">Todos</option>
                            <option value="emitida">Emitidas</option>
                            <option value="anulada">Anuladas</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Emitidos</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facturar</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo acreditable</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in comprobantes.data" :key="c.id">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">#{{ c.id }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ tipoLabel(c.tipo) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.facturar_cuenta?.tercero?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.entrega_cuenta?.tercero?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.estado }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ c.total }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda === 'ARS' ? '-' : (c.detalle_facturacion?.calculo?.cotizacion?.tasa_ars || c.detalle_facturacion?.cotizacion?.tasa_ars || '-') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <span v-if="c.credit_summary?.saldo_acreditable !== null">{{ c.moneda }} {{ c.credit_summary?.saldo_acreditable }}</span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <Link :href="route('operacion.comprobantes.show', c.id)" class="text-indigo-600 hover:text-indigo-800">Ver</Link>
                                </td>
                            </tr>
                            <tr v-if="!comprobantes.data.length">
                                <td colspan="9" class="px-6 py-10 text-center text-sm text-gray-500">Sin comprobantes.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
