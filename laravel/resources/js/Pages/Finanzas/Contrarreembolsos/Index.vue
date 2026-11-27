<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    comprobantes: { type: Object, required: true },
    filtros: { type: Object, required: true },
})

const formatNum = (value) => Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const filtrar = (event) => router.get(route('finanzas.contrarreembolsos.index'), { ...props.filtros, [event.target.name]: event.target.value || undefined }, { preserveState: true, replace: true })
</script>

<template>
    <AppLayout title="Contrarreembolsos">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Contrarreembolsos</h2>
                <Link :href="route('finanzas.contrarreembolsos.create')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Nueva liquidación</Link>
            </div>
        </template>
        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap gap-3">
                <input name="buscar" :value="filtros.buscar" @change="filtrar" placeholder="N.º, beneficiario o CUIT" class="rounded-md border-gray-300 text-sm" />
                <select name="estado" :value="filtros.estado" @change="filtrar" class="rounded-md border-gray-300 text-sm">
                    <option value="">Todos los estados</option><option value="borrador">Borrador</option><option value="confirmado">Confirmado</option><option value="anulada">Anulada</option>
                </select>
            </div>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">N.º / Fecha</th><th class="px-4 py-3">Beneficiario</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in comprobantes.data" :key="item.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3"><Link :href="route('finanzas.contrarreembolsos.show', item.id)" class="font-medium text-indigo-700">#{{ item.numero_interno }}</Link><div class="text-xs text-gray-500">{{ item.fecha }}</div></td>
                            <td class="px-4 py-3">{{ item.beneficiario_cuenta?.tercero?.razon_social || item.beneficiario_cuenta?.nombre_cuenta || '—' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ item.estado }}</span></td>
                            <td class="px-4 py-3 text-right font-mono">{{ item.moneda }} {{ formatNum(item.total) }}</td>
                        </tr>
                        <tr v-if="!comprobantes.data.length"><td colspan="4" class="px-4 py-10 text-center text-gray-500">No hay liquidaciones para mostrar.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-between text-sm"><Link v-if="comprobantes.prev_page_url" :href="comprobantes.prev_page_url" class="text-indigo-700">← Anterior</Link><span v-else></span><Link v-if="comprobantes.next_page_url" :href="comprobantes.next_page_url" class="text-indigo-700">Siguiente →</Link></div>
        </div>
    </AppLayout>
</template>
