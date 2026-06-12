<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    hojas: Object,
    depositos: Array,
    empresas: Array,
    filtros: Object,
    resumen: Object,
});

const filterForm = useForm({
    desde: props.filtros.desde,
    hasta: props.filtros.hasta,
    estado: props.filtros.estado,
    deposito_id: props.filtros.deposito_id,
    empresa_id: props.filtros.empresa_id,
});

const applyFilters = () => {
    router.get(route('operacion.repartos.hojas.index'), {
        ...(filterForm.desde && { desde: filterForm.desde }),
        ...(filterForm.hasta && { hasta: filterForm.hasta }),
        ...(filterForm.estado && { estado: filterForm.estado }),
        ...(filterForm.deposito_id && { deposito_id: filterForm.deposito_id }),
        ...(filterForm.empresa_id && { empresa_id: filterForm.empresa_id }),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const medioLabel = (m) => {
    const map = { efectivo: 'Efectivo', transferencia: 'Transferencia', cheque_propio: 'Cheque propio', cheque_tercero: 'Cheque tercero', sin_medio: 'Sin medio' };
    return map[m] || m;
};

const formatFecha = (v) => v ? String(v).slice(0, 10) : '-';
</script>

<template>
    <AppLayout title="Operacion / Hojas de ruta">
        <Head title="Operacion / Hojas de ruta" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Hojas de ruta</h2>
                <Link :href="route('operacion.repartos.facturas')">
                    <PrimaryButton>Nueva hoja</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-4 items-end">
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Desde</div>
                        <TextInput v-model="filterForm.desde" type="date" class="block w-full" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Hasta</div>
                        <TextInput v-model="filterForm.hasta" type="date" class="block w-full" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Estado</div>
                        <select v-model="filterForm.estado" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todos</option>
                            <option value="borrador">Borrador</option>
                            <option value="cerrada">Cerrada</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Depósito</div>
                        <select v-model="filterForm.deposito_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todos</option>
                            <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Empresa</div>
                        <select v-model="filterForm.empresa_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todas</option>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                    </div>
                    <div>
                        <PrimaryButton @click="applyFilters">Filtrar</PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Resumen</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Hojas</div>
                        <div class="text-lg font-semibold text-gray-900">{{ resumen.total_hojas }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Items</div>
                        <div class="text-lg font-semibold text-gray-900">{{ resumen.total_items }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Total cobrado</div>
                        <div class="text-lg font-semibold text-gray-900">$ {{ resumen.total_cobrado }}</div>
                    </div>
                </div>
                <div v-if="Object.keys(resumen.por_medio).length" class="mt-4 border-t border-gray-100 pt-4">
                    <div class="text-xs font-medium text-gray-700 mb-2">Por medio de cobro</div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div v-for="(total, medio) in resumen.por_medio" :key="medio" class="bg-gray-50 rounded p-3">
                            <div class="text-xs text-gray-500">{{ medioLabel(medio) }}</div>
                            <div class="text-sm font-semibold text-gray-900">$ {{ total }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Depósito</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="h in hojas.data" :key="h.id">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">#{{ h.id }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(h.fecha) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ h.deposito?.nombre || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ h.empresa?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ h.items?.length || 0 }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="h.estado === 'cerrada' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">{{ h.estado }}</span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <Link class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.repartos.hojas.show', h.id)">Ver</Link>
                                </td>
                            </tr>
                            <tr v-if="!hojas.data.length">
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">Sin hojas de ruta.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="hojas.total > hojas.per_page" class="p-4 border-t border-gray-200 flex items-center justify-between text-sm">
                    <div>Página {{ hojas.current_page }} de {{ hojas.last_page }} ({{ hojas.total }})</div>
                    <div class="flex gap-2">
                        <SecondaryButton v-if="hojas.prev_page_url" @click="router.get(hojas.prev_page_url, {}, { preserveState: true, preserveScroll: true })">Anterior</SecondaryButton>
                        <SecondaryButton v-if="hojas.next_page_url" @click="router.get(hojas.next_page_url, {}, { preserveState: true, preserveScroll: true })">Siguiente</SecondaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
