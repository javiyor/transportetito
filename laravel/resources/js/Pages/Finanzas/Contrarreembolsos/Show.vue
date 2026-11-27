<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ comprobante: { type: Object, required: true } })
const form = useForm({ motivo: '' })
const formatNum = (value) => Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const confirmar = () => useForm({}).post(route('finanzas.contrarreembolsos.confirmar', props.comprobante.id))
const anular = () => {
    const motivo = window.prompt('Motivo de anulación del borrador:')
    if (!motivo) return
    form.motivo = motivo
    form.post(route('finanzas.contrarreembolsos.anular', props.comprobante.id))
}
</script>

<template>
    <AppLayout title="Liquidación de contrarreembolso">
        <template #header><div class="flex items-center justify-between"><h2 class="text-xl font-semibold text-gray-800">Liquidación #{{ comprobante.numero_interno }}</h2><div class="flex gap-3"><Link :href="route('finanzas.contrarreembolsos.index')" class="text-sm text-indigo-700">Listado</Link><a :href="route('finanzas.contrarreembolsos.print', comprobante.id)" target="_blank" class="text-sm text-indigo-700">Imprimir</a></div></div></template>
        <div class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 rounded-lg bg-white p-5 shadow-sm sm:grid-cols-3"><div><div class="text-xs uppercase text-gray-500">Fecha</div><div>{{ comprobante.fecha }}</div></div><div><div class="text-xs uppercase text-gray-500">Beneficiario</div><div>{{ comprobante.beneficiario_cuenta?.tercero?.razon_social || comprobante.beneficiario_cuenta?.nombre_cuenta }}</div><div class="text-xs text-gray-500">{{ comprobante.beneficiario_tipo }}</div></div><div><div class="text-xs uppercase text-gray-500">Estado</div><span class="rounded-full bg-gray-100 px-2 py-1 text-sm">{{ comprobante.estado }}</span></div></div>
            <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200"><h3 class="border-b px-4 py-3 font-semibold">Aplicaciones</h3><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Contrarreembolso</th><th class="px-4 py-3">Pedido</th><th class="px-4 py-3 text-right">Importe</th></tr></thead><tbody class="divide-y"><tr v-for="aplicacion in comprobante.aplicaciones" :key="aplicacion.id"><td class="px-4 py-3">#{{ aplicacion.contrarreembolso_id }}</td><td class="px-4 py-3">{{ aplicacion.contrarreembolso?.pedido?.remito_numero || aplicacion.contrarreembolso?.pedido_id }}</td><td class="px-4 py-3 text-right font-mono">{{ comprobante.moneda }} {{ formatNum(aplicacion.importe) }}</td></tr></tbody></table></section>
            <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200"><h3 class="border-b px-4 py-3 font-semibold">Medios entregados</h3><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Medio</th><th class="px-4 py-3">Detalle</th><th class="px-4 py-3 text-right">Importe</th></tr></thead><tbody class="divide-y"><tr v-for="item in comprobante.items" :key="item.id"><td class="px-4 py-3">{{ item.medio }}</td><td class="px-4 py-3">{{ item.cheque ? `${item.cheque.banco} · ${item.cheque.numero} · ${item.cheque.fecha_vencimiento}` : 'Efectivo' }}</td><td class="px-4 py-3 text-right font-mono">{{ comprobante.moneda }} {{ formatNum(item.importe) }}</td></tr></tbody><tfoot><tr class="font-semibold"><td colspan="2" class="px-4 py-3 text-right">Total</td><td class="px-4 py-3 text-right font-mono">{{ comprobante.moneda }} {{ formatNum(comprobante.total) }}</td></tr></tfoot></table></section>
            <div v-if="comprobante.observaciones" class="rounded-lg bg-white p-4 text-sm"><strong>Observaciones:</strong> {{ comprobante.observaciones }}</div>
            <div v-if="comprobante.estado === 'borrador'" class="flex justify-end gap-3"><button :disabled="form.processing" @click="anular" class="rounded-md border border-red-300 px-4 py-2 text-sm text-red-700">Anular borrador</button><button @click="confirmar" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Confirmar liquidación</button></div>
        </div>
    </AppLayout>
</template>
