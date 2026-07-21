<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatNum } from '@/Utils/format.js';

const props = defineProps({
    filters: Object,
    comprobantes: Object,
});

const form = useForm({
    tipo: props.filters?.tipo || 'todos',
    estado: props.filters?.estado || 'todos',
    compartidos: props.filters?.compartidos || '1',
});

const applyFilters = () => {
    router.get(route('operacion.comprobantes.index'), {
        tipo: form.tipo || 'todos',
        estado: form.estado || 'todos',
        compartidos: form.compartidos || '1',
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const arcaTipoLabel = (arca_tipo_cbte) => {
    const map = {
        '01': 'Factura A', '02': 'Nota debito A', '03': 'Nota credito A',
        '06': 'Factura B', '07': 'Nota debito B', '08': 'Nota credito B',
        '11': 'Factura C', '12': 'Nota debito C', '13': 'Nota credito C',
        '15': 'Factura E', '16': 'Nota debito E', '17': 'Nota credito E',
        '51': 'Factura M', '52': 'Nota debito M', '53': 'Nota credito M',
    };
    return map[String(arca_tipo_cbte)] || null;
};
const tipoLabel = (c) => {
    if (c.arca_tipo_cbte) {
        const arc = arcaTipoLabel(c.arca_tipo_cbte);
        if (arc) return arc;
    }
    const map = {
        guia_envio: 'Guia no fiscal',
        factura_interna: 'Factura',
        nota_credito_interna: 'Nota de credito',
        nota_debito_manual: 'Nota de debito',
        nota_credito_manual: 'Nota de credito manual',
    };
    return map[c.tipo] || c.tipo || '-';
};
</script>

<template>
    <AppLayout title="Operacion / Comprobantes">
        <Head title="Operacion / Comprobantes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Comprobantes</h2>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.terceros.index', { tipo: 'cliente' })"><SecondaryButton>Nuevo cliente</SecondaryButton></Link>
                    <Link :href="route('operacion.manifiestos.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
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
                    <div class="flex items-end">
                        <button @click="form.compartidos = form.compartidos === '1' ? '0' : '1'; applyFilters()"
                            class="text-xs px-3 py-2 rounded border font-medium transition-colors"
                            :class="form.compartidos === '1' ? 'bg-indigo-100 text-indigo-700 border-indigo-300' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
                            {{ form.compartidos === '1' ? 'Compartidos' : 'Solo esta empresa' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Emitidos</h3>
                </div>
                <div class="space-y-4 p-4 sm:hidden">
                    <div v-for="c in comprobantes.data" :key="c.id" class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">#{{ c.id }}</div>
                                <div class="text-xs text-gray-500">{{ tipoLabel(c) }} · {{ c.estado }} · {{ c.arca_punto_venta ? String(parseInt(c.arca_punto_venta)) + '-' + String(c.arca_numero).padStart(8,'0') : (c.numero_interno ? '#' + c.numero_interno : '') }}</div>
                            </div>
                            <Link :href="route('operacion.comprobantes.show', c.id)" class="text-sm text-indigo-600 hover:text-indigo-800">Ver</Link>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Facturar</div>
                                <div class="font-medium text-gray-900">{{ c.facturar_cuenta?.tercero?.razon_social || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Entrega</div>
                                <div class="font-medium text-gray-900">{{ c.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Subtotal</div>
                                    <div class="font-medium text-gray-900">{{ formatNum(c.subtotal) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">IVA</div>
                                    <div class="font-medium text-blue-700">{{ formatNum(c.iva_total) }}</div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Total</div>
                                    <div class="font-medium text-gray-900">{{ c.moneda }} {{ formatNum(c.total) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Cotizacion</div>
                                    <div class="font-medium text-gray-900">{{ c.moneda === 'ARS' ? '-' : (c.detalle_facturacion?.calculo?.cotizacion?.tasa_ars || c.detalle_facturacion?.cotizacion?.tasa_ars || '-') }}</div>
                                </div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Saldo acreditable</div>
                                <div class="font-medium text-gray-900">
                                    <span v-if="c.credit_summary?.saldo_acreditable !== null">{{ c.moneda }} {{ formatNum(c.credit_summary?.saldo_acreditable) }}</span>
                                    <span v-else>-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!comprobantes.data.length" class="rounded-lg border border-gray-200 bg-white px-6 py-10 text-center text-sm text-gray-500">Sin comprobantes.</div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nro</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facturar</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">IVA</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo acred.</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in comprobantes.data" :key="c.id">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">#{{ c.id }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ tipoLabel(c) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 font-mono">{{ c.arca_punto_venta ? String(parseInt(c.arca_punto_venta)) + '-' + String(c.arca_numero).padStart(8,'0') : (c.numero_interno ? '#' + c.numero_interno : '-') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.facturar_cuenta?.tercero?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.entrega_cuenta?.tercero?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.estado }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 text-right">{{ formatNum(c.subtotal) }}</td>
                                <td class="px-6 py-4 text-sm text-blue-700 text-right">{{ formatNum(c.iva_total) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 font-semibold text-right">{{ c.moneda }} {{ formatNum(c.total) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 text-right">
                                    <span v-if="c.credit_summary?.saldo_acreditable !== null">{{ c.moneda }} {{ formatNum(c.credit_summary?.saldo_acreditable) }}</span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <Link :href="route('operacion.comprobantes.show', c.id)" class="text-indigo-600 hover:text-indigo-800">Ver</Link>
                                </td>
                            </tr>
                            <tr v-if="!comprobantes.data.length">
                                <td colspan="11" class="px-6 py-10 text-center text-sm text-gray-500">Sin comprobantes.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
