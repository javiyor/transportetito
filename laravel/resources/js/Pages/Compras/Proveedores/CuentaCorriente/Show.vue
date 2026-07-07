<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatNum } from '@/Utils/format.js';

const BANCOS = [
    'Banco Nacion', 'Banco Provincia', 'Banco Galicia', 'Banco BBVA',
    'Banco Macro', 'Banco Santander', 'Banco HSBC', 'Banco ICBC',
    'Banco Ciudad', 'Banco Supervielle', 'Banco Patagonia',
    'Banco Comafi', 'Banco Brubank', 'Banco Uala', 'Otro',
];

const props = defineProps({
    cuenta: Object,
    movimientos: Array,
    comprobantes: Array,
    ordenesPago: Array,
    chequesDisponibles: Array,
    saldoTotal: Number,
    bancos: Array,
});

const form = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    moneda: 'ARS',
    comprobante_ids: [],
    items: [
        { medio: 'efectivo', importe: '', moneda: 'ARS', cheque_numero: '', cheque_banco: '', cheque_vencimiento: '', cheque_id: '' },
    ],
    observacion: '',
});

const totalPago = computed(() => {
    return form.items.reduce((sum, item) => sum + (parseFloat(item.importe) || 0), 0);
});

const agregarItem = () => {
    form.items.push({ medio: 'efectivo', importe: '', moneda: form.moneda, cheque_numero: '', cheque_banco: '', cheque_vencimiento: '', cheque_id: '' });
};

const quitarItem = (idx) => {
    if (form.items.length > 1) form.items.splice(idx, 1);
};

const esChequeTercero = (medio) => medio === 'cheque_tercero';
const esChequePropio = (medio) => medio === 'cheque_propio';

const submit = () => form.post(route('compras.proveedores.ctacte.ordenes-pago.store', props.cuenta.id), { preserveScroll: true });

const formatFecha = (value) => value ? String(value).slice(0, 10) : '-';

const chequesFiltrados = computed(() => {
    const usadoIds = form.items
        .filter(i => i.medio === 'cheque_tercero' && i.cheque_id)
        .map(i => Number(i.cheque_id));
    return (props.chequesDisponibles || []).filter(ch => !usadoIds.includes(ch.id));
});
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
                    <Link :href="route('compras.proveedores.ctacte.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow sm:rounded-lg p-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm">
                <div><div class="text-xs text-gray-500">Proveedor</div><div class="font-medium text-gray-900">{{ cuenta.tercero?.razon_social || '-' }}</div></div>
                <div><div class="text-xs text-gray-500">Saldo total</div><div class="font-medium text-gray-900">{{ formatNum(saldoTotal) }}</div></div>
                <div><div class="text-xs text-gray-500">CUIT</div><div class="font-medium text-gray-900">{{ cuenta.tercero?.cuit || '-' }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Registrar orden de pago</h3>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div><label class="block text-xs text-gray-500 mb-1">Fecha</label><TextInput v-model="form.fecha" type="date" class="block w-full text-sm" /></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Moneda</label><select v-model="form.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select></div>
                        <div><label class="block text-xs text-gray-500 mb-1">Observacion</label><TextInput v-model="form.observacion" type="text" class="block w-full text-sm" placeholder="Observacion" /></div>
                    </div>

                    <fieldset class="border border-gray-200 rounded-md p-1 max-h-40 overflow-y-auto">
                        <legend class="text-xs text-gray-500 px-1">Comprobantes a pagar (opcional)</legend>
                        <table v-if="comprobantes.length" class="w-full text-xs">
                            <thead><tr class="text-gray-500"><th class="text-left pr-2 py-0.5">Tipo</th><th class="text-left pr-2 py-0.5">Numero</th><th class="text-right pr-2 py-0.5">Total</th><th class="text-right py-0.5">Saldo</th><th class="w-4 py-0.5"></th></tr></thead>
                            <tbody><tr v-for="c in comprobantes" :key="c.id" class="hover:bg-gray-50"><td class="pr-2 py-0.5 text-gray-700">{{ c.tipo }}</td><td class="pr-2 py-0.5 text-gray-700 font-mono">{{ c.numero || '-' }}</td><td class="pr-2 py-0.5 text-right text-gray-700">{{ c.moneda }} {{ formatNum(c.total) }}</td><td class="pr-2 py-0.5 text-right text-gray-900 font-semibold">{{ c.moneda }} {{ formatNum(c.saldo_pendiente) }}</td><td class="py-0.5"><input type="checkbox" :value="c.id" v-model="form.comprobante_ids" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 size-3.5" /></td></tr></tbody>
                        </table>
                        <div v-if="!comprobantes.length" class="text-xs text-gray-400 py-1">Sin comprobantes pendientes</div>
                    </fieldset>

                    <div class="border border-gray-200 rounded-md p-3 space-y-2">
                        <div class="text-sm font-medium text-gray-700">Medios de pago</div>
                        <div v-for="(item, idx) in form.items" :key="idx" class="border border-gray-100 rounded p-2 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <select v-model="item.medio" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="transferencia">Transferencia</option>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="cheque_tercero">Cheque de tercero</option>
                                    <option value="cheque_propio">Cheque propio</option>
                                </select>
                                <TextInput v-model="item.importe" type="number" min="0.01" step="0.01" class="block w-28 text-sm" placeholder="Importe" />
                                <button type="button" class="text-red-500 text-lg leading-none font-bold" @click="quitarItem(idx)" :disabled="form.items.length <= 1">&times;</button>
                            </div>
                            <div v-if="esChequeTercero(item.medio)" class="grid grid-cols-1 gap-2">
                                <select v-model="item.cheque_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="">Seleccionar cheque en cartera...</option>
                                    <option v-for="ch in chequesFiltrados" :key="ch.id" :value="ch.id">
                                        #{{ ch.id }} · {{ ch.banco || 'S/B' }} · {{ ch.numero || 'S/N' }} · ${{ formatNum(ch.importe) }} · Vto {{ formatFecha(ch.fecha_vencimiento) }}
                                    </option>
                                </select>
                                <p v-if="!chequesFiltrados.length" class="text-xs text-amber-600">No hay cheques de terceros disponibles en cartera.</p>
                            </div>
                            <div v-if="esChequePropio(item.medio)" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div><label class="block text-xs text-gray-500">Nro. cheque</label><TextInput v-model="item.cheque_numero" type="text" class="block w-full text-sm" placeholder="Nro cheque" /></div>
                                <div><label class="block text-xs text-gray-500">Banco</label><select v-model="item.cheque_banco" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Seleccionar banco</option><option v-for="b in (bancos || BANCOS)" :key="b.id || b" :value="b.nombre || b">{{ b.nombre || b }}</option></select></div>
                                <div><label class="block text-xs text-gray-500">Vencimiento</label><TextInput v-model="item.cheque_vencimiento" type="date" class="block w-full text-sm" /></div>
                            </div>
                        </div>
                        <SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="agregarItem">+ Agregar medio</SecondaryButton>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-900">Total: {{ formatNum(totalPago) }}</div>
                        <PrimaryButton :disabled="form.processing" @click="submit">Guardar orden de pago</PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Comprobantes proveedor</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numero</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pagado</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes" :key="c.id"><td class="px-4 py-2 text-sm text-gray-700">{{ formatFecha(c.fecha_emision) }}</td><td class="px-4 py-2 text-sm text-gray-700">{{ c.tipo }}</td><td class="px-4 py-2 text-sm text-gray-700">{{ c.numero || '-' }}</td><td class="px-4 py-2 text-sm text-right text-gray-900 font-semibold">{{ c.moneda }} {{ formatNum(c.total) }}</td><td class="px-4 py-2 text-sm text-gray-700 text-right">{{ c.moneda }} {{ formatNum(c.pagado_total) }}</td><td class="px-4 py-2 text-sm text-gray-700 text-right">{{ c.moneda }} {{ formatNum(c.saldo_pendiente) }}</td><td class="px-4 py-2 text-right text-sm"><Link class="text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link></td></tr></tbody></table></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Ordenes de pago</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cheque</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="o in ordenesPago" :key="o.id"><td class="px-4 py-2 text-sm text-gray-700">{{ formatFecha(o.fecha) }}</td><td class="px-4 py-2 text-sm text-gray-700">{{ o.medio || '-' }}</td><td class="px-4 py-2 text-sm text-right text-gray-900 font-semibold">{{ o.moneda }} {{ formatNum(o.total) }}</td><td class="px-4 py-2 text-sm text-gray-600"><template v-if="o.cheque"><span class="text-xs">{{ o.cheque.banco || '' }} {{ o.cheque.numero || '' }}</span></template><span v-else class="text-gray-400">—</span></td><td class="px-4 py-2 text-sm text-gray-700">{{ o.observacion || '-' }}</td><td class="px-4 py-2 text-right text-sm"><a class="text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.ordenes-pago.print', o.id)" target="_blank">Imprimir</a></td></tr></tbody></table></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Movimientos</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comprobante</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="m in movimientos" :key="m.id"><td class="px-4 py-2 text-sm text-gray-700">{{ formatFecha(m.fecha) }}</td><td class="px-4 py-2 text-sm text-gray-700">{{ m.tipo }}</td><td class="px-4 py-2 text-sm text-gray-700"><template v-if="m.comprobante_numero && m.comprobante_tipo">{{ m.comprobante_tipo }} {{ m.comprobante_numero }}</template><span v-else class="text-gray-400">-</span></td><td class="px-4 py-2 text-sm text-gray-700">{{ m.moneda }} <span v-if="m.moneda !== 'ARS'" class="text-xs text-gray-500">({{ m.cotizacion_ars }})</span></td><td class="px-4 py-2 text-sm text-right text-gray-900 font-semibold">{{ formatNum(m.importe_signed) }}</td><td class="px-4 py-2 text-sm text-gray-700">{{ m.observacion || '-' }}</td></tr></tbody></table></div>
            </div>
        </div>
    </AppLayout>
</template>
