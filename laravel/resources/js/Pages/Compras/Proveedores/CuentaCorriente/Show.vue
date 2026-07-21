<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
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
        { medio: 'efectivo', importe: '', moneda: 'ARS', cheque_numero: '', cheque_banco: '', cheque_tipo: 'fisico', cheque_vencimiento: '', cheque_id: '' },
    ],
    observacion: '',
});

const totalPago = computed(() => {
    return form.items.reduce((sum, item) => sum + (parseFloat(item.importe) || 0), 0);
});

const comprobantesPorId = computed(() => {
    const map = {};
    (props.comprobantes || []).forEach(c => { map[c.id] = c; });
    return map;
});

const comprobantesPendientes = computed(() => {
    return (props.comprobantes || []).filter(c => parseFloat(c.saldo_pendiente) > 0);
});

const creditosDisponibles = computed(() => {
    return (props.comprobantes || []).filter(c => c.is_credit || parseFloat(c.saldo_pendiente) < 0);
});

const selectedComprobantesTotal = computed(() => {
    return (form.comprobante_ids || []).reduce((sum, id) => {
        const c = comprobantesPorId.value[id];
        return sum + (c ? parseFloat(c.saldo_pendiente) : 0);
    }, 0);
});

const agregarItem = () => {
    form.items.push({ medio: 'efectivo', importe: '', moneda: form.moneda, cheque_numero: '', cheque_banco: '', cheque_tipo: 'fisico', cheque_vencimiento: '', cheque_id: '' });
};

const quitarItem = (idx) => {
    if (form.items.length > 1) form.items.splice(idx, 1);
};

const esChequeTercero = (medio) => medio === 'cheque_tercero';
const esChequePropio = (medio) => medio === 'cheque_propio';

const submit = () => {
    if (!form.comprobante_ids?.length) {
        if (!confirm('No seleccionaste comprobantes a pagar. ¿Emitir orden de pago igual?')) return;
    } else if (selectedComprobantesTotal.value === 0) {
        if (!confirm('Los comprobantes seleccionados se cancelan entre si (total=0). ¿Confirmar compensación?')) return;
    }
    form.post(route('compras.proveedores.ctacte.ordenes-pago.store', props.cuenta.id), { preserveScroll: true });
};

const eliminarOrdenPago = (o) => {
    if (!confirm(`¿Eliminar orden de pago #${o.id}? Esta acción no se puede deshacer.`)) return;
    router.delete(route('compras.proveedores.ordenes-pago.destroy', o.id), { preserveScroll: true });
};

const ajusteForm = useForm({ tipo: 'ajuste_debito', fecha: new Date().toISOString().slice(0, 10), moneda: 'ARS', importe: '', observacion: '' });
const notaForm = useForm({ tipo: 'nota_debito_manual', fecha: new Date().toISOString().slice(0, 10), moneda: 'ARS', importe: '', motivo: '' });

const submitAjuste = () => ajusteForm.post(route('compras.proveedores.ctacte.ajustes.store', props.cuenta.id), { preserveScroll: true });
const submitNota = () => notaForm.post(route('compras.proveedores.ctacte.notas.store', props.cuenta.id), { preserveScroll: true });

const formatFecha = (value) => {
    if (!value) return '-';
    const d = new Date(String(value).slice(0, 10));
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
};

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
                            <table v-if="comprobantesPendientes.length" class="w-full text-xs">
                                <thead><tr class="text-gray-500"><th class="text-left pr-2 py-0.5">Tipo</th><th class="text-left pr-2 py-0.5">Numero</th><th class="text-right pr-2 py-0.5">Total</th><th class="text-right py-0.5">Saldo</th><th class="w-4 py-0.5"></th></tr></thead>
                                <tbody><tr v-for="c in comprobantesPendientes" :key="c.id" class="hover:bg-gray-50"><td class="pr-2 py-0.5 text-gray-700">{{ c.tipo }}</td><td class="pr-2 py-0.5 text-gray-700 font-mono">{{ c.numero || '-' }}</td><td class="pr-2 py-0.5 text-right text-gray-700">{{ c.moneda }} {{ formatNum(c.total) }}</td><td class="pr-2 py-0.5 text-right font-semibold text-gray-900">{{ c.moneda }} {{ formatNum(c.saldo_pendiente) }}</td><td class="py-0.5"><input type="checkbox" :value="c.id" v-model="form.comprobante_ids" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 size-3.5" /></td></tr></tbody>
                            </table>
                            <div v-if="!comprobantesPendientes.length" class="text-xs text-gray-400 py-1">Sin comprobantes pendientes</div>
                            <div v-if="creditosDisponibles.length" class="mt-1 text-xs text-green-600 border-t border-gray-100 pt-1">
                                Creditos disponibles: 
                                <span v-for="(cr, idx) in creditosDisponibles" :key="cr.id">
                                    {{ idx > 0 ? ', ' : '' }}{{ cr.moneda }} {{ formatNum(Math.abs(cr.saldo_pendiente)) }}{{ cr.is_credit ? ' (Pago a cuenta)' : '' }}
                                </span>
                            </div>
                        <div v-if="form.comprobante_ids.length" class="mt-1 text-xs font-semibold text-gray-700 border-t border-gray-100 pt-1">
                            Total seleccionado: {{ formatNum(selectedComprobantesTotal) }}
                            <span v-if="selectedComprobantesTotal === 0" class="text-amber-600">(compensación)</span>
                        </div>
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

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-3">
                    <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wider">Ajuste</h3>
                    <div class="mt-2 space-y-2">
                        <select v-model="ajusteForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option value="ajuste_debito">Ajuste debito</option><option value="ajuste_credito">Ajuste credito</option></select>
                        <div class="grid grid-cols-3 gap-2">
                            <TextInput v-model="ajusteForm.fecha" type="date" class="block w-full text-xs" />
                            <select v-model="ajusteForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                            <TextInput v-model="ajusteForm.importe" type="number" min="0.01" step="0.01" class="block w-full text-xs" placeholder="Importe" />
                        </div>
                        <InputError :message="ajusteForm.errors.fecha" />
                        <InputError :message="ajusteForm.errors.moneda" />
                        <InputError :message="ajusteForm.errors.importe" />
                        <TextInput v-model="ajusteForm.observacion" type="text" class="block w-full text-xs" placeholder="Observacion" />
                        <InputError :message="ajusteForm.errors.observacion" />
                        <PrimaryButton class="!text-xs !px-3 !py-1.5" :disabled="ajusteForm.processing" @click="submitAjuste">Guardar</PrimaryButton>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg p-3">
                    <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wider">Nota debito / credito</h3>
                    <div class="mt-2 space-y-2">
                        <select v-model="notaForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option value="nota_debito_manual">Nota debito</option><option value="nota_credito_manual">Nota credito</option></select>
                        <div class="grid grid-cols-3 gap-2">
                            <TextInput v-model="notaForm.fecha" type="date" class="block w-full text-xs" />
                            <select v-model="notaForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                            <TextInput v-model="notaForm.importe" type="number" min="0.01" step="0.01" class="block w-full text-xs" placeholder="Importe" />
                        </div>
                        <InputError :message="notaForm.errors.fecha" />
                        <InputError :message="notaForm.errors.moneda" />
                        <InputError :message="notaForm.errors.importe" />
                        <TextInput v-model="notaForm.motivo" type="text" class="block w-full text-xs" placeholder="Motivo" />
                        <InputError :message="notaForm.errors.motivo" />
                        <PrimaryButton class="!text-xs !px-3 !py-1.5" :disabled="notaForm.processing" @click="submitNota">Generar</PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Comprobantes proveedor</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numero</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pagado</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes" :key="c.id" :class="{'bg-green-50': c.is_credit}"><td class="px-4 py-2 text-sm text-gray-700">{{ formatFecha(c.fecha_emision) }}</td><td class="px-4 py-2 text-sm" :class="c.is_credit ? 'text-green-600 font-medium' : 'text-gray-700'">{{ c.is_credit ? 'Pago a cuenta' : c.tipo }}</td><td class="px-4 py-2 text-sm text-gray-700 font-mono">{{ c.numero || '-' }}</td><td class="px-4 py-2 text-sm text-right font-semibold" :class="c.is_credit ? 'text-green-600' : 'text-gray-900'">{{ c.moneda }} {{ formatNum(c.total) }}</td><td class="px-4 py-2 text-sm text-right text-gray-700">{{ c.moneda }} {{ formatNum(c.pagado_total) }}</td><td class="px-4 py-2 text-sm text-right font-semibold" :class="c.is_credit ? 'text-green-600' : 'text-gray-700'">{{ c.moneda }} {{ formatNum(c.saldo_pendiente) }}</td><td class="px-4 py-2 text-right text-sm"><Link v-if="!c.is_credit" class="text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link><span v-else class="text-xs text-gray-400 italic">Pago a cuenta</span></td></tr></tbody></table></div>
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
