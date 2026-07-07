<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    ventas: Array,
    compras: Array,
    resumenVentas: Object,
    resumenCompras: Object,
    filtros: Object,
});

const page = usePage();
const roles = computed(() => page.props.tt?.roles || []);

const formatFecha = (v) => {
    if (!v) return '-';
    const d = new Date(String(v).slice(0, 10));
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
};
const formatNum = (n) => (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatMoneda = (m, n) => `${m} ${formatNum(n)}`;

const tipoLabel = (t) => {
    if (!t) return '-';
    const map = {
        '1': 'Factura A', '2': 'ND A', '3': 'NC A',
        '6': 'Factura B', '7': 'ND B', '8': 'NC B',
        '11': 'Factura C', '12': 'ND C', '13': 'NC C',
        '51': 'Factura M', '52': 'ND M', '53': 'NC M',
        'FA': 'Factura A', 'FB': 'Factura B', 'FC': 'Factura C',
        'FCA': 'Factura Crédito A', 'FCB': 'Factura Crédito B', 'FCC': 'Factura Crédito C',
        'NDA': 'ND A', 'NDB': 'ND B', 'NDC': 'ND C',
        'NCA': 'NC A', 'NCB': 'NC B', 'NCC': 'NC C',
        'factura_interna': 'Factura', 'guia_envio': 'Guía', 'nota_credito_interna': 'NC',
        'nota_debito_manual': 'ND', 'nota_credito_manual': 'NC manual',
    };
    return map[String(t).trim().toUpperCase()] || t;
};

const aplicarFiltro = (anio, mes) => {
    router.get(route('cobranzas.resumen-arca'), { anio, mes }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Resumen ARCA">
        <Head title="Resumen ARCA" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Resumen ARCA &mdash; Libro IVA</h2>
                <div class="flex items-center gap-2">
                    <a :href="route('cobranzas.resumen-arca.export', { tipo: 'ventas', anio: filtros.anio, mes: filtros.mes })"><SecondaryButton>Exportar Ventas CSV</SecondaryButton></a>
                    <a :href="route('cobranzas.resumen-arca.export', { tipo: 'compras', anio: filtros.anio, mes: filtros.mes })"><SecondaryButton>Exportar Compras CSV</SecondaryButton></a>
                    <Link :href="route('cobranzas.ctacte.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <!-- Filtros -->
            <div class="bg-white shadow sm:rounded-lg p-4 flex flex-wrap items-center gap-3">
                <div class="text-sm font-medium text-gray-700">Periodo:</div>
                <select class="block w-32 border-gray-300 rounded-md shadow-sm text-sm" :value="filtros.anio" @change="aplicarFiltro($event.target.value, filtros.mes)">
                    <option v-for="a in filtros.aniosDisponibles" :key="a" :value="a">{{ a }}</option>
                </select>
                <select class="block w-36 border-gray-300 rounded-md shadow-sm text-sm" :value="filtros.mes" @change="aplicarFiltro(filtros.anio, $event.target.value)">
                    <option value="">Todos los meses</option>
                    <option v-for="(m, i) in ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']" :key="i + 1" :value="i + 1">{{ m }}</option>
                </select>
                <SecondaryButton @click="aplicarFiltro('', '')">Limpiar filtros</SecondaryButton>
            </div>

            <!-- Dashboard resumen -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Ventas (con CAE)</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Comprobantes:</span><span class="font-medium">{{ resumenVentas.cantidad }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Subtotal:</span><span class="font-medium">$ {{ formatNum(resumenVentas.subtotal) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">IVA total:</span><span class="font-medium text-blue-700">$ {{ formatNum(resumenVentas.iva_total) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Tributos:</span><span class="font-medium">$ {{ formatNum(resumenVentas.tributos_total) }}</span></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2"><span class="text-gray-700 font-semibold">Total:</span><span class="font-semibold">$ {{ formatNum(resumenVentas.total) }}</span></div>
                    </div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Compras</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Comprobantes:</span><span class="font-medium">{{ resumenCompras.cantidad }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Subtotal:</span><span class="font-medium">$ {{ formatNum(resumenCompras.subtotal) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">IVA total:</span><span class="font-medium text-green-700">$ {{ formatNum(resumenCompras.iva_total) }}</span></div>
                        <div v-if="resumenCompras.tributos_total > 0" class="flex justify-between"><span class="text-gray-500">Tributos:</span><span class="font-medium">$ {{ formatNum(resumenCompras.tributos_total) }}</span></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2"><span class="text-gray-700 font-semibold">Total:</span><span class="font-semibold">$ {{ formatNum(resumenCompras.total) }}</span></div>
                    </div>
                    <div class="mt-4 border-t border-gray-200 pt-3 text-sm">
                        <div class="flex justify-between text-gray-700"><span>IVA a pagar (Ventas - Compras):</span><span class="font-bold" :class="(resumenVentas.iva_total - resumenCompras.iva_total) > 0 ? 'text-red-700' : 'text-green-700'">$ {{ formatNum(resumenVentas.iva_total - resumenCompras.iva_total) }}</span></div>
                    </div>
                </div>
            </div>

            <!-- Tabla Ventas -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Comprobantes de venta</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nro ARCA</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">IVA</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tributos</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">CAE</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="v in ventas" :key="v.id">
                                <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ formatFecha(v.fecha_emision) }}</td>
                                <td class="px-4 py-2 text-gray-900 font-medium">{{ tipoLabel(v.arca_tipo_cbte || v.tipo) }}</td>
                                <td class="px-4 py-2 text-gray-700 font-mono">{{ v.arca_punto_venta ? String(v.arca_punto_venta).padStart(4,'0') + '-' + String(v.arca_numero).padStart(8,'0') : (v.tipo === 'factura_interna' ? '#' + v.id : '-') }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">$ {{ formatNum(v.subtotal) }}</td>
                                <td class="px-4 py-2 text-right text-blue-700">$ {{ formatNum(v.iva_total) }}</td>
                                <td class="px-4 py-2 text-right text-orange-700">$ {{ formatNum(v.tributos_total) }}</td>
                                <td class="px-4 py-2 text-right text-gray-900 font-semibold">$ {{ formatNum(v.total) }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="text-xs font-mono" :class="v.arca_cae ? 'text-green-700' : 'text-gray-400'">{{ v.arca_cae || '-' }}</span>
                                </td>
                            </tr>
                            <tr v-if="!ventas.length">
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-400">Sin comprobantes de venta en este periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabla Compras -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Comprobantes de compra</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Numero</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">IVA</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Tributos</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in compras" :key="c.id">
                                <td class="px-4 py-2 text-gray-700 whitespace-nowrap">{{ formatFecha(c.fecha_emision) }}</td>
                                <td class="px-4 py-2 text-gray-900 font-medium">{{ tipoLabel(c.tipo) }}</td>
                                <td class="px-4 py-2 text-gray-700 font-mono">{{ c.numero || '-' }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">$ {{ formatNum(c.subtotal) }}</td>
                                <td class="px-4 py-2 text-right text-green-700">$ {{ formatNum(c.iva_total) }}</td>
                                <td class="px-4 py-2 text-right text-orange-700">$ {{ formatNum(c.tributos_total) }}</td>
                                <td class="px-4 py-2 text-right text-gray-900 font-semibold">$ {{ formatNum(c.total) }}</td>
                            </tr>
                            <tr v-if="!compras.length">
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">Sin comprobantes de compra en este periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
