<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    cuenta: Object,
    movimientos: Array,
    comprobantes: Array,
    saldos: Object,
});

const ajusteForm = useForm({ tipo: 'ajuste_debito', fecha: new Date().toISOString().slice(0, 10), moneda: 'ARS', importe: '', observacion: '' });
const notaForm = useForm({ tipo: 'nota_debito_manual', fecha: new Date().toISOString().slice(0, 10), moneda: 'ARS', importe: '', motivo: '' });
const reciboForm = useForm({ fecha: new Date().toISOString().slice(0, 10), moneda: 'ARS', importe: '', medio: 'efectivo', detalle: '', comprobante_id: '' });

const submitAjuste = () => ajusteForm.post(route('cobranzas.ctacte.ajustes.store', props.cuenta.id), { preserveScroll: true });
const submitNota = () => notaForm.post(route('cobranzas.ctacte.notas.store', props.cuenta.id), { preserveScroll: true });
const submitRecibo = () => reciboForm.post(route('cobranzas.ctacte.recibos.store', props.cuenta.id), { preserveScroll: true });

const formatFecha = (value) => value ? String(value).slice(0, 10) : '-';
</script>

<template>
    <AppLayout :title="`Cuenta corriente #${cuenta.id}`">
        <Head :title="`Cuenta corriente #${cuenta.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cuenta corriente</h2>
                    <div class="mt-1 text-sm text-gray-600">{{ cuenta.tercero?.razon_social || '-' }} · CUIT {{ cuenta.tercero?.cuit || '-' }}</div>
                </div>
                <Link :href="route('cobranzas.ctacte.index')"><SecondaryButton>Volver</SecondaryButton></Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div><div class="text-xs text-gray-500">Zona</div><div class="text-sm font-medium text-gray-900">{{ cuenta.zona?.nombre || 'Sin zona' }}</div></div>
                <div><div class="text-xs text-gray-500">Ciudad</div><div class="text-sm font-medium text-gray-900">{{ cuenta.localidad || 'Sin ciudad' }}</div></div>
                <div><div class="text-xs text-gray-500">Saldo total</div><div class="text-sm font-medium text-gray-900">{{ saldos.saldo_total }}</div></div>
                <div><div class="text-xs text-gray-500">Vencido +30</div><div class="text-sm font-medium" :class="saldos.vencido_30 > 0 ? 'text-red-700' : 'text-gray-900'">{{ saldos.vencido_30 }}</div></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Generar ajuste</h3>
                    <div class="mt-4 space-y-3">
                        <select v-model="ajusteForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="ajuste_debito">Ajuste debito</option><option value="ajuste_credito">Ajuste credito</option></select>
                        <TextInput v-model="ajusteForm.fecha" type="date" class="block w-full" />
                        <select v-model="ajusteForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                        <TextInput v-model="ajusteForm.importe" type="number" min="0.01" step="0.01" class="block w-full" placeholder="Importe" />
                        <TextInput v-model="ajusteForm.observacion" type="text" class="block w-full" placeholder="Observacion" />
                        <PrimaryButton :disabled="ajusteForm.processing" @click="submitAjuste">Guardar ajuste</PrimaryButton>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Nota de debito / credito</h3>
                    <div class="mt-4 space-y-3">
                        <select v-model="notaForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="nota_debito_manual">Nota de debito</option><option value="nota_credito_manual">Nota de credito</option></select>
                        <TextInput v-model="notaForm.fecha" type="date" class="block w-full" />
                        <select v-model="notaForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                        <TextInput v-model="notaForm.importe" type="number" min="0.01" step="0.01" class="block w-full" placeholder="Importe" />
                        <TextInput v-model="notaForm.motivo" type="text" class="block w-full" placeholder="Motivo" />
                        <PrimaryButton :disabled="notaForm.processing" @click="submitNota">Generar nota</PrimaryButton>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Emitir recibo</h3>
                    <div class="mt-4 space-y-3">
                        <TextInput v-model="reciboForm.fecha" type="date" class="block w-full" />
                        <select v-model="reciboForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                        <TextInput v-model="reciboForm.importe" type="number" min="0.01" step="0.01" class="block w-full" placeholder="Importe" />
                        <select v-model="reciboForm.medio" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="cheque_propio">Cheque propio</option><option value="cheque_tercero">Cheque tercero</option></select>
                        <select v-model="reciboForm.comprobante_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">A cuenta</option><option v-for="c in comprobantes" :key="c.id" :value="c.id">#{{ c.id }} · {{ c.tipo }} · {{ c.moneda }} {{ c.total }}</option></select>
                        <TextInput v-model="reciboForm.detalle" type="text" class="block w-full" placeholder="Detalle" />
                        <PrimaryButton :disabled="reciboForm.processing" @click="submitRecibo">Emitir recibo</PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Comprobantes</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes" :key="c.id"><td class="px-6 py-4 text-sm font-mono text-gray-900">#{{ c.id }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.tipo }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(c.fecha_emision) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ c.total }}</td><td class="px-6 py-4 text-right text-sm"><Link class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', c.id)">Ver</Link></td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Movimientos</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ref.</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200"><tr v-for="m in movimientos" :key="m.id"><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(m.fecha) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.tipo }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.moneda }} <span v-if="m.moneda !== 'ARS'" class="text-xs text-gray-500">({{ m.cotizacion_ars }})</span></td><td class="px-6 py-4 text-sm text-gray-700">{{ m.importe_signed }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.observacion || '-' }}</td><td class="px-6 py-4 text-right text-sm"><Link v-if="m.referencia_tipo === 'comprobante' && m.referencia_id" class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', m.referencia_id)">Comprobante</Link><Link v-else-if="m.referencia_tipo === 'recibo' && m.referencia_id" class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.show', m.referencia_id)">Recibo</Link><span v-else>-</span></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
