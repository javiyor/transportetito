<script setup>
import { computed, ref, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({ registros: { type: Array, required: true }, cheques: { type: Array, required: true }, fecha: { type: String, required: true } })
const form = useForm({ fecha: props.fecha, moneda: 'ARS', remitente_cuenta_id: '', destinatario_cuenta_id: '', observaciones: '', contrarreembolsos: [], items: [], cheque_ids: [], efectivo: '', confirmar: false })
const confirming = ref(false)
const searchRemitente = ref('')
const searchDestinatario = ref('')
const showRemitenteDropdown = ref(false)
const showDestinatarioDropdown = ref(false)
const formatNum = (value) => Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const crearOpcionesCuenta = (tipo) => {
    const idKey = `${tipo}_cuenta_id`
    const result = new Map()
    for (const row of props.registros) {
        if (row[idKey]) result.set(row[idKey], { id: row[idKey], nombre: row[tipo], cuit: row[`${tipo}_cuit`] })
    }
    return [...result.values()]
}
const remitentes = computed(() => crearOpcionesCuenta('remitente'))
const destinatarios = computed(() => crearOpcionesCuenta('destinatario'))
const filtrarCuentas = (cuentas, texto) => {
    const query = texto.trim().toLocaleLowerCase()
    return query ? cuentas.filter((cuenta) => `${cuenta.nombre} ${cuenta.cuit}`.toLocaleLowerCase().includes(query)) : cuentas
}
const remitentesFiltrados = computed(() => filtrarCuentas(remitentes.value, searchRemitente.value))
const destinatariosFiltrados = computed(() => filtrarCuentas(destinatarios.value, searchDestinatario.value))
const seleccionarRemitente = (cuenta) => {
    form.remitente_cuenta_id = cuenta.id
    searchRemitente.value = cuenta.nombre
    showRemitenteDropdown.value = false
}
const seleccionarDestinatario = (cuenta) => {
    form.destinatario_cuenta_id = cuenta.id
    searchDestinatario.value = cuenta.nombre
    showDestinatarioDropdown.value = false
}
watch(() => [form.remitente_cuenta_id, form.destinatario_cuenta_id, form.moneda], () => {
    form.contrarreembolsos = []
})
const disponibles = computed(() => {
    if (!form.remitente_cuenta_id || !form.destinatario_cuenta_id) return []
    return props.registros.filter((row) => Number(row.remitente_cuenta_id) === Number(form.remitente_cuenta_id)
        && Number(row.destinatario_cuenta_id) === Number(form.destinatario_cuenta_id)
        && row.moneda === form.moneda)
})
const selectedCheques = computed(() => props.cheques.filter((cheque) => form.cheque_ids.includes(cheque.id) && cheque.moneda === form.moneda))
const totalAplicar = computed(() => form.contrarreembolsos.reduce((sum, row) => sum + Number(row.importe || 0), 0))
const totalMedios = computed(() => Number(form.efectivo || 0) + selectedCheques.value.reduce((sum, cheque) => sum + Number(cheque.importe), 0))
const toggled = (row, event) => {
    const rows = [...form.contrarreembolsos]
    const index = rows.findIndex((item) => item.id === row.id)
    if (event.target.checked && index < 0) rows.push({ id: row.id, importe: row.disponible })
    if (!event.target.checked && index >= 0) rows.splice(index, 1)
    form.contrarreembolsos = rows
}
const importePara = (id) => form.contrarreembolsos.find((row) => row.id === id)?.importe ?? ''
const cambiarImporte = (id, value) => { const row = form.contrarreembolsos.find((item) => item.id === id); if (row) row.importe = value }
const enviar = (confirmar) => {
    form.confirmar = confirmar
    form.items = []
    if (Number(form.efectivo) > 0) form.items.push({ medio: 'efectivo', importe: Number(form.efectivo) })
    for (const cheque of selectedCheques.value) form.items.push({ medio: 'cheque_tercero', cheque_id: cheque.id })
    confirming.value = confirmar
    form.post(route('finanzas.contrarreembolsos.store'), { onFinish: () => { confirming.value = false } })
}
</script>

<template>
    <AppLayout title="Nueva liquidación de contrarreembolso">
        <template #header><div class="flex items-center justify-between"><h2 class="text-xl font-semibold text-gray-800">Nueva liquidación de contrarreembolso</h2><Link :href="route('finanzas.contrarreembolsos.index')" class="text-sm text-indigo-700">Volver al listado</Link></div></template>
        <div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
            <div v-if="Object.keys(form.errors).length" class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ Object.values(form.errors)[0] }}</div>
            <section class="grid gap-4 rounded-lg bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm">Fecha<input v-model="form.fecha" type="date" class="mt-1 block w-full rounded-md border-gray-300" /></label>
                <label class="text-sm">Moneda<select v-model="form.moneda" class="mt-1 block w-full rounded-md border-gray-300"><option v-for="moneda in ['ARS','USD','EUR','BRL']" :key="moneda">{{ moneda }}</option></select></label>
                <div class="relative text-sm">
                    <label for="remitente-search">Remitente</label>
                    <input id="remitente-search" v-model="searchRemitente" @input="form.remitente_cuenta_id = ''; showRemitenteDropdown = true" @focus="showRemitenteDropdown = true" @blur="setTimeout(() => showRemitenteDropdown = false, 200)" type="text" class="mt-1 block w-full rounded-md border-gray-300" placeholder="Buscar nombre o CUIT..." autocomplete="off" />
                    <input v-model="form.remitente_cuenta_id" type="hidden" />
                    <ul v-if="showRemitenteDropdown && remitentesFiltrados.length" class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg">
                        <li v-for="cuenta in remitentesFiltrados" :key="cuenta.id" @mousedown.prevent="seleccionarRemitente(cuenta)" class="cursor-pointer border-b px-3 py-2 hover:bg-indigo-50"><div class="font-medium">{{ cuenta.nombre }}</div><div class="text-xs text-gray-500">{{ cuenta.cuit || 'Sin CUIT' }}</div></li>
                    </ul>
                    <InputError :message="form.errors.remitente_cuenta_id" />
                </div>
                <div class="relative text-sm">
                    <label for="destinatario-search">Destinatario</label>
                    <input id="destinatario-search" v-model="searchDestinatario" @input="form.destinatario_cuenta_id = ''; showDestinatarioDropdown = true" @focus="showDestinatarioDropdown = true" @blur="setTimeout(() => showDestinatarioDropdown = false, 200)" type="text" class="mt-1 block w-full rounded-md border-gray-300" placeholder="Buscar nombre o CUIT..." autocomplete="off" />
                    <input v-model="form.destinatario_cuenta_id" type="hidden" />
                    <ul v-if="showDestinatarioDropdown && destinatariosFiltrados.length" class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg">
                        <li v-for="cuenta in destinatariosFiltrados" :key="cuenta.id" @mousedown.prevent="seleccionarDestinatario(cuenta)" class="cursor-pointer border-b px-3 py-2 hover:bg-indigo-50"><div class="font-medium">{{ cuenta.nombre }}</div><div class="text-xs text-gray-500">{{ cuenta.cuit || 'Sin CUIT' }}</div></li>
                    </ul>
                    <InputError :message="form.errors.destinatario_cuenta_id" />
                </div>
                <label class="text-sm sm:col-span-2 lg:col-span-4">Observaciones<input v-model="form.observaciones" class="mt-1 block w-full rounded-md border-gray-300" maxlength="2000" /></label>
                <p class="text-xs text-gray-500 sm:col-span-2 lg:col-span-4">Los descuentos están deshabilitados hasta definir la regla comercial y contable.</p>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b px-4 py-3 font-semibold">Contrarreembolsos cobrados disponibles</div>
                <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-3 py-3">Aplicar</th><th class="px-3 py-3">Factura / Remito</th><th class="px-3 py-3">Remitente</th><th class="px-3 py-3">Destinatario</th><th class="px-3 py-3 text-right">Disponible</th><th class="px-3 py-3">Importe a liquidar</th></tr></thead><tbody class="divide-y">
                    <tr v-for="row in disponibles" :key="row.id"><td class="px-3 py-2"><input type="checkbox" :checked="form.contrarreembolsos.some((item) => item.id === row.id)" @change="toggled(row, $event)" /></td><td class="px-3 py-2">{{ row.comprobante }}<div class="text-xs text-gray-500">Remito {{ row.remito || '—' }}</div></td><td class="px-3 py-2">{{ row.remitente }}</td><td class="px-3 py-2">{{ row.destinatario }}</td><td class="px-3 py-2 text-right font-mono">{{ row.moneda }} {{ formatNum(row.disponible) }}</td><td class="px-3 py-2"><input type="number" min="0.01" step="0.01" :max="row.disponible" :disabled="!form.contrarreembolsos.some((item) => item.id === row.id)" :value="importePara(row.id)" @input="cambiarImporte(row.id, $event.target.value)" class="w-36 rounded-md border-gray-300 text-right" /></td></tr>
                    <tr v-if="!disponibles.length"><td colspan="6" class="px-3 py-8 text-center text-gray-500">Seleccioná remitente, destinatario y moneda para ver los contrarreembolsos disponibles.</td></tr>
                </tbody></table></div>
            </section>

            <section class="grid gap-5 rounded-lg bg-white p-5 shadow-sm lg:grid-cols-2">
                <div><h3 class="mb-3 font-semibold">Valores entregados</h3><label class="block text-sm">Efectivo ({{ form.moneda }})<input v-model="form.efectivo" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 text-right" /></label><div class="mt-4 text-sm font-semibold">Cheques de terceros en cartera</div><div class="mt-2 max-h-52 space-y-2 overflow-auto">
                    <label v-for="cheque in cheques.filter((item) => item.moneda === form.moneda)" :key="cheque.id" class="flex items-center gap-2 text-sm"><input v-model="form.cheque_ids" type="checkbox" :value="cheque.id" /><span>{{ cheque.banco }} · {{ cheque.numero }} · {{ cheque.fecha_vencimiento }} · {{ form.moneda }} {{ formatNum(cheque.importe) }}</span></label>
                    <p v-if="!cheques.some((item) => item.moneda === form.moneda)" class="text-sm text-gray-500">No hay cheques disponibles en esta moneda.</p>
                </div></div>
                <div class="space-y-3 rounded-md bg-gray-50 p-4 text-sm"><div class="flex justify-between"><span>Total a liquidar</span><strong>{{ form.moneda }} {{ formatNum(totalAplicar) }}</strong></div><div class="flex justify-between"><span>Total en efectivo/cheques</span><strong>{{ form.moneda }} {{ formatNum(totalMedios) }}</strong></div><div :class="Math.abs(totalAplicar-totalMedios) <= 0.01 ? 'text-green-700' : 'text-red-700'" class="font-semibold">Diferencia: {{ form.moneda }} {{ formatNum(totalAplicar-totalMedios) }}</div><p class="text-xs text-gray-500">Los cheques seleccionados se endosarán al confirmar; los importes deben coincidir exactamente con las aplicaciones.</p></div>
            </section>
            <div class="flex flex-wrap justify-end gap-3"><button type="button" :disabled="form.processing" @click="enviar(false)" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 disabled:opacity-50">Guardar borrador</button><button type="button" :disabled="form.processing || totalAplicar <= 0 || Math.abs(totalAplicar-totalMedios) > 0.01" @click="enviar(true)" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ confirming ? 'Confirmando…' : 'Confirmar y liquidar' }}</button></div>
        </div>
    </AppLayout>
</template>
