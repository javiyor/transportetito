<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatNum } from '@/Utils/format.js';

const props = defineProps({
    cuenta: Object,
    movimientos: Array,
    comprobantes: Array,
    ordenesPago: Array,
    chequesDisponibles: Array,
    saldoTotal: Number,
});

const form = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    moneda: 'ARS',
    importe: '',
    medio: 'transferencia',
    observacion: '',
    proveedor_comprobante_id: '',
    cheque_id: '',
    cheque_numero: '',
    cheque_banco: '',
    cheque_vencimiento: '',
});

const esChequeTercero = computed(() => form.medio === 'cheque_tercero');
const esChequePropio = computed(() => form.medio === 'cheque_propio');

const submit = () => form.post(route('compras.proveedores.ctacte.ordenes-pago.store', props.cuenta.id), { preserveScroll: true });
const formatFecha = (value) => value ? String(value).slice(0, 10) : '-';
</script>

<template>
    <AppLayout :title="`Compras / Proveedor #${cuenta.id}`">
        <Head :title="`Compras / Proveedor #${cuenta.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cuenta corriente proveedor</h2>
                    <div class="mt-1 text-sm text-gray-600">{{ cuenta.tercero?.razon_social || '-' }} · CUIT {{ cuenta.tercero?.cuit || '-' }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <a v-if="form.proveedor_comprobante_id" :href="route('compras.proveedores.ordenes-pago.print', ordenesPago[0]?.id || 0)" class="hidden"></a>
                    <Link :href="route('compras.proveedores.ctacte.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div><div class="text-xs text-gray-500">Proveedor</div><div class="text-sm font-medium text-gray-900">{{ cuenta.tercero?.razon_social || '-' }}</div></div>
                <div><div class="text-xs text-gray-500">Saldo total</div><div class="text-sm font-medium text-gray-900">{{ formatNum(saldoTotal) }}</div></div>
                <div><div class="text-xs text-gray-500">CUIT</div><div class="text-sm font-medium text-gray-900">{{ cuenta.tercero?.cuit || '-' }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Registrar orden de pago</h3>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div><label class="block text-xs text-gray-500 mb-1">Fecha</label><TextInput v-model="form.fecha" type="date" class="block w-full" /></div>
                    <div><label class="block text-xs text-gray-500 mb-1">Moneda</label><select v-model="form.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select></div>
                    <div><label class="block text-xs text-gray-500 mb-1">Importe</label><TextInput v-model="form.importe" type="number" min="0.01" step="0.01" class="block w-full" placeholder="Importe" /></div>
                    <div><label class="block text-xs text-gray-500 mb-1">Medio</label><select v-model="form.medio" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="transferencia">Transferencia</option><option value="cheque_tercero">Cheque de tercero</option><option value="cheque_propio">Cheque propio</option><option value="efectivo">Efectivo</option></select></div>
                    <div><label class="block text-xs text-gray-500 mb-1">Comprobante</label><select v-model="form.proveedor_comprobante_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">A cuenta</option><option v-for="c in comprobantes" :key="c.id" :value="c.id">#{{ c.id }} · {{ c.tipo }} · {{ c.moneda }} {{ formatNum(c.total) }}</option></select></div>
                    <div><label class="block text-xs text-gray-500 mb-1">Observacion</label><TextInput v-model="form.observacion" type="text" class="block w-full" placeholder="Observacion" /></div>

                    <div v-if="esChequeTercero" class="sm:col-span-3 border-t border-gray-100 pt-3">
                        <label class="block text-xs text-gray-500 mb-1">Seleccionar cheque de tercero en cartera</label>
                        <select v-model="form.cheque_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Seleccionar...</option>
                            <option v-for="ch in chequesDisponibles" :key="ch.id" :value="ch.id">
                                #{{ ch.id }} · {{ ch.banco || 'S/B' }} · {{ ch.numero || 'S/N' }} · ${{ formatNum(ch.importe) }} · Vto {{ formatFecha(ch.fecha_vencimiento) }} {{ ch.librado_por ? '· ' + ch.librado_por : '' }}
                            </option>
                        </select>
                        <p v-if="!chequesDisponibles?.length" class="text-xs text-amber-600 mt-1">No hay cheques de terceros en cartera.</p>
                    </div>

                    <div v-if="esChequePropio" class="sm:col-span-3 border-t border-gray-100 pt-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div><label class="block text-xs text-gray-500 mb-1">Nro. cheque</label><TextInput v-model="form.cheque_numero" type="text" class="block w-full" placeholder="Nro cheque" /></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Banco</label><TextInput v-model="form.cheque_banco" type="text" class="block w-full" placeholder="Banco" /></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Vencimiento</label><TextInput v-model="form.cheque_vencimiento" type="date" class="block w-full" /></div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end"><PrimaryButton :disabled="form.processing" @click="submit">Guardar orden de pago</PrimaryButton></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Comprobantes proveedor</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numero</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pagado</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes" :key="c.id"><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(c.fecha_emision) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.tipo }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.numero || '-' }}</td><td class="px-6 py-4 text-sm text-right text-gray-900 font-semibold">{{ c.moneda }} {{ formatNum(c.total) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ formatNum(c.pagado_total) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ formatNum(c.saldo_pendiente) }}</td><td class="px-6 py-4 text-right text-sm"><Link class="text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link></td></tr></tbody></table></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Ordenes de pago</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cheque</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="o in ordenesPago" :key="o.id"><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(o.fecha) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ o.medio || '-' }}</td><td class="px-6 py-4 text-sm text-right text-gray-900 font-semibold">{{ o.moneda }} {{ formatNum(o.total) }}</td><td class="px-6 py-4 text-sm text-gray-600">
                        <template v-if="o.cheque"><span class="text-xs">{{ o.cheque.banco || '' }} {{ o.cheque.numero || '' }}</span></template>
                        <span v-else class="text-gray-400">—</span>
                    </td><td class="px-6 py-4 text-sm text-gray-700">{{ o.observacion || '-' }}</td><td class="px-6 py-4 text-right text-sm"><a class="text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.ordenes-pago.print', o.id)" target="_blank">Imprimir</a></td></tr></tbody></table></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Movimientos</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="m in movimientos" :key="m.id"><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(m.fecha) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.tipo }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.moneda }} <span v-if="m.moneda !== 'ARS'" class="text-xs text-gray-500">({{ m.cotizacion_ars }})</span></td><td class="px-6 py-4 text-sm text-right text-gray-900 font-semibold">{{ formatNum(m.importe_signed) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.observacion || '-' }}</td></tr></tbody></table></div>
            </div>
        </div>
    </AppLayout>
</template>
