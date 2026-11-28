<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    grupos: { type: Array, required: true },
    empresas: { type: Array, required: true },
    remitentes: { type: Array, required: true },
    destinatarios: { type: Array, required: true },
    filtros: { type: Object, required: true },
    agrupadoPor: { type: String, required: true },
})

const filtros = ref({ ...props.filtros })
const expandidos = ref(new Set())
const formatNum = (value) => Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const formatFecha = (value) => value ? String(value).slice(0, 10) : '—'
const total = (campo) => props.grupos.reduce((suma, grupo) => suma + Number(grupo[campo] || 0), 0)
const porcentajeTotal = computed(() => total('valor_declarado') > 0 ? total('total_cobrado') / total('valor_declarado') * 100 : 0)

const aplicarFiltros = () => {
    router.get(route('operacion.resumen-fletes'), {
        desde: filtros.value.desde || null,
        hasta: filtros.value.hasta || null,
        empresa_id: filtros.value.empresa_id || null,
        remitente_id: filtros.value.remitente_id || null,
        destinatario_id: filtros.value.destinatario_id || null,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

const toggleGrupo = (id) => {
    const siguientes = new Set(expandidos.value)
    siguientes.has(id) ? siguientes.delete(id) : siguientes.add(id)
    expandidos.value = siguientes
}
</script>

<template>
    <AppLayout title="Repartos / Resumen de fletes">
        <Head title="Repartos / Resumen de fletes" />
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Repartos / Resumen de fletes</h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 py-5 sm:px-6 lg:px-8">
            <section class="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
                <label class="text-sm">Desde<input v-model="filtros.desde" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" /></label>
                <label class="text-sm">Hasta<input v-model="filtros.hasta" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm" /></label>
                <label class="text-sm">Empresa<select v-model="filtros.empresa_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm"><option value="">Todas las empresas</option><option v-for="empresa in empresas" :key="empresa.id" :value="empresa.id">{{ empresa.razon_social }}</option></select></label>
                <label class="text-sm">Remitente<select v-model="filtros.remitente_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm"><option value="">Todos</option><option v-for="remitente in remitentes" :key="remitente.id" :value="remitente.id">{{ remitente.nombre }} · {{ remitente.cuit || 'Sin CUIT' }}</option></select></label>
                <label class="text-sm">Destinatario<select v-model="filtros.destinatario_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm"><option value="">Todos</option><option v-for="destinatario in destinatarios" :key="destinatario.id" :value="destinatario.id">{{ destinatario.nombre }} · {{ destinatario.cuit || 'Sin CUIT' }}</option></select></label>
                <div class="flex items-end"><button type="button" @click="aplicarFiltros" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button></div>
                <p class="text-xs text-gray-500 sm:col-span-2 lg:col-span-6">
                    {{ filtros.remitente_id ? 'Agrupado por destinatario' : 'Agrupado por remitente' }} · Importes convertidos a ARS según la cotización guardada en cada comprobante.
                </p>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="resumen in [
                    { label: 'Valor declarado', value: total('valor_declarado') },
                    { label: 'Fletes cobrados', value: total('flete') },
                    { label: 'Seguros', value: total('seguro') },
                    { label: 'Bultos', value: total('bultos'), money: false },
                    { label: 'Palets', value: total('palets'), money: false },
                ]" :key="resumen.label" class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="text-xs text-gray-500">{{ resumen.label }}</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ resumen.money === false ? formatNum(resumen.value) : `ARS ${formatNum(resumen.value)}` }}</div>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b px-4 py-3 text-sm font-semibold text-gray-800">Resumen por {{ agrupadoPor }} ({{ grupos.length }} grupos)</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr><th class="px-3 py-3">{{ agrupadoPor === 'destinatario' ? 'Destinatario' : 'Remitente' }}</th><th class="px-3 py-3 text-right">Valor declarado</th><th class="px-3 py-3 text-right">Fletes</th><th class="px-3 py-3 text-right">Seguros</th><th class="px-3 py-3 text-right">Bultos</th><th class="px-3 py-3 text-right">Palets</th><th class="px-3 py-3 text-right">Prom. / bulto</th><th class="px-3 py-3 text-right">Prom. / palet</th><th class="px-3 py-3 text-right">Cobro / valor</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-for="grupo in grupos" :key="grupo.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2"><button type="button" class="mr-2 font-bold text-indigo-700" @click="toggleGrupo(grupo.id)">{{ expandidos.has(grupo.id) ? '−' : '+' }}</button><span class="font-medium">{{ grupo.contraparte }}</span><span v-if="grupo.cuit" class="ml-1 text-xs text-gray-500">{{ grupo.cuit }}</span><div class="ml-7 text-xs text-gray-500">{{ Object.values(grupo.empresas || {}).join(', ') }}</div></td>
                                    <td class="px-3 py-2 text-right font-mono">ARS {{ formatNum(grupo.valor_declarado) }}</td><td class="px-3 py-2 text-right font-mono">ARS {{ formatNum(grupo.flete) }}</td><td class="px-3 py-2 text-right font-mono">ARS {{ formatNum(grupo.seguro) }}</td><td class="px-3 py-2 text-right">{{ formatNum(grupo.bultos) }}</td><td class="px-3 py-2 text-right">{{ formatNum(grupo.palets) }}</td><td class="px-3 py-2 text-right font-mono">ARS {{ formatNum(grupo.promedio_bulto) }}</td><td class="px-3 py-2 text-right font-mono">ARS {{ formatNum(grupo.promedio_palet) }}</td><td class="px-3 py-2 text-right">{{ formatNum(grupo.porcentaje_valor_declarado) }}%</td>
                                </tr>
                                <tr v-if="expandidos.has(grupo.id)"><td colspan="9" class="bg-gray-50 p-3">
                                    <div class="overflow-x-auto"><table class="min-w-full text-xs"><thead><tr class="text-left uppercase text-gray-500"><th class="px-2 py-2">Fecha</th><th class="px-2 py-2">Comprobante</th><th class="px-2 py-2">Empresa</th><th class="px-2 py-2">Remitente</th><th class="px-2 py-2">Destinatario</th><th class="px-2 py-2">Remitos</th><th class="px-2 py-2 text-right">Valor declarado</th><th class="px-2 py-2 text-right">Flete</th><th class="px-2 py-2 text-right">Seguro</th><th class="px-2 py-2 text-right">Bultos</th><th class="px-2 py-2 text-right">Palets</th></tr></thead><tbody class="divide-y divide-gray-200"><tr v-for="viaje in grupo.viajes" :key="`${viaje.comprobante_id}-${viaje.remitente_id}-${viaje.destinatario_id}`"><td class="px-2 py-2">{{ formatFecha(viaje.fecha) }}</td><td class="px-2 py-2">{{ viaje.comprobante_tipo }} #{{ viaje.comprobante_id }}</td><td class="px-2 py-2">{{ viaje.empresa }}</td><td class="px-2 py-2">{{ viaje.remitente }}</td><td class="px-2 py-2">{{ viaje.destinatario }}</td><td class="px-2 py-2">{{ viaje.remitos.join(', ') || '—' }}</td><td class="px-2 py-2 text-right font-mono">ARS {{ formatNum(viaje.valor_declarado) }}</td><td class="px-2 py-2 text-right font-mono">ARS {{ formatNum(viaje.flete) }}</td><td class="px-2 py-2 text-right font-mono">ARS {{ formatNum(viaje.seguro) }}</td><td class="px-2 py-2 text-right">{{ viaje.bultos }}</td><td class="px-2 py-2 text-right">{{ viaje.palets }}</td></tr></tbody></table></div>
                                </td></tr>
                            </template>
                    <tr v-if="!grupos.length"><td colspan="9" class="px-4 py-10 text-center text-gray-500">No hay viajes facturados para esos filtros.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <p class="text-xs text-gray-500">Promedios calculados sobre flete + seguro. Relación sobre el valor declarado = (flete + seguro) / valor declarado.</p>
        </div>
    </AppLayout>
</template>
