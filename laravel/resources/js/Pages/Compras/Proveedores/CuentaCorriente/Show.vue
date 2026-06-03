<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    cuenta: Object,
    movimientos: Array,
    comprobantes: Array,
    ordenesPago: Array,
    saldoTotal: Number,
});

const form = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    moneda: 'ARS',
    importe: '',
    medio: 'transferencia',
    observacion: '',
    proveedor_comprobante_id: '',
});

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
                <Link :href="route('compras.proveedores.ctacte.index')"><SecondaryButton>Volver</SecondaryButton></Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div><div class="text-xs text-gray-500">Proveedor</div><div class="text-sm font-medium text-gray-900">{{ cuenta.tercero?.razon_social || '-' }}</div></div>
                <div><div class="text-xs text-gray-500">Saldo total</div><div class="text-sm font-medium text-gray-900">{{ saldoTotal }}</div></div>
                <div><div class="text-xs text-gray-500">CUIT</div><div class="text-sm font-medium text-gray-900">{{ cuenta.tercero?.cuit || '-' }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Registrar orden de pago</h3>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <TextInput v-model="form.fecha" type="date" class="block w-full" />
                    <select v-model="form.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                    <TextInput v-model="form.importe" type="number" min="0.01" step="0.01" class="block w-full" placeholder="Importe" />
                    <select v-model="form.medio" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="transferencia">Transferencia</option><option value="cheque">Cheque</option><option value="efectivo">Efectivo</option></select>
                    <select v-model="form.proveedor_comprobante_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">A cuenta</option><option v-for="c in comprobantes" :key="c.id" :value="c.id">#{{ c.id }} · {{ c.tipo }} · {{ c.moneda }} {{ c.total }}</option></select>
                    <TextInput v-model="form.observacion" type="text" class="block w-full" placeholder="Observacion" />
                </div>
                <div class="mt-4 flex justify-end"><PrimaryButton :disabled="form.processing" @click="submit">Guardar orden de pago</PrimaryButton></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Comprobantes proveedor</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numero</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pagado</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes" :key="c.id"><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(c.fecha_emision) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.tipo }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.numero || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ c.total }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ c.pagado_total }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ c.saldo_pendiente }}</td><td class="px-6 py-4 text-right text-sm"><Link class="text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link></td></tr></tbody></table></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Ordenes de pago</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="o in ordenesPago" :key="o.id"><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(o.fecha) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ o.medio || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ o.moneda }} {{ o.total }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ o.observacion || '-' }}</td></tr></tbody></table></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Movimientos</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="m in movimientos" :key="m.id"><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(m.fecha) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.tipo }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.moneda }} <span v-if="m.moneda !== 'ARS'" class="text-xs text-gray-500">({{ m.cotizacion_ars }})</span></td><td class="px-6 py-4 text-sm text-gray-700">{{ m.importe_signed }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.observacion || '-' }}</td></tr></tbody></table></div>
            </div>
        </div>
    </AppLayout>
</template>
