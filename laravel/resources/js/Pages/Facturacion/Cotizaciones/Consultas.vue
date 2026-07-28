<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
    cotizaciones: Object,
    filtros: Object,
});

const desde = ref(props.filtros?.desde || '');
const hasta = ref(props.filtros?.hasta || '');
const vencida = ref(props.filtros?.vencida || '');

const filtrar = () => {
    router.get(route('facturacion.cotizaciones.consultas'), {
        desde: desde.value,
        hasta: hasta.value,
        vencida: vencida.value,
    }, { preserveState: true, preserveScroll: true });
};

const formatFecha = (v) => v ? String(v).slice(0, 10) : '-';
const formatNum = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });

const estaVencida = (c) => {
    if (!c.fecha_validez) return false;
    return new Date(c.fecha_validez) < new Date(new Date().toISOString().slice(0, 10));
};
</script>

<template>
    <AppLayout title="Facturacion / Cotizaciones / Consultas">
        <Head title="Facturacion / Cotizaciones / Consultas" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cotizaciones / Consultas</h2>
                <div class="flex items-center gap-3">
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('facturacion.cotizaciones.pedido.create')">Nuevo pedido</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('facturacion.cotizaciones.pendientes')">Pendientes</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="text-xs text-gray-500">Desde</label>
                        <TextInput v-model="desde" type="date" class="mt-1 block w-full text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Hasta</label>
                        <TextInput v-model="hasta" type="date" class="mt-1 block w-full text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Vencida</label>
                        <select v-model="vencida" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todas</option>
                            <option value="no">Vigentes</option>
                            <option value="si">Vencidas</option>
                        </select>
                    </div>
                    <div>
                        <PrimaryButton class="!text-xs !px-3 !py-1.5" @click="filtrar">Filtrar</PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Cotizaciones realizadas ({{ cotizaciones.total }})</h3></div>

                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="c in cotizaciones.data" :key="c.id" class="rounded-lg border border-gray-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ c.remitente?.tercero?.razon_social || '#' + c.tercero_cuenta_id }}</div>
                                <div class="text-xs text-gray-500">{{ formatFecha(c.created_at) }} · Validez: {{ formatFecha(c.fecha_validez) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold" :class="estaVencida(c) ? 'text-red-600' : 'text-green-700'">$ {{ formatNum(c.flete_final) }}</div>
                                <div class="text-xs" :class="estaVencida(c) ? 'text-red-500' : 'text-green-600'">{{ estaVencida(c) ? 'Vencida' : 'Vigente' }}</div>
                            </div>
                        </div>
                        <div class="mt-1 text-xs text-gray-600">{{ c.origen || '-' }} → {{ c.destino || '-' }}</div>
                    </div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Remitente</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ruta</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Flete</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Validez</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in cotizaciones.data" :key="c.id">
                                <td class="px-4 py-2 text-sm text-gray-700">{{ formatFecha(c.created_at) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ c.remitente?.tercero?.razon_social || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ c.origen || '-' }} → {{ c.destino || c.destinatario?.tercero?.razon_social || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-right font-mono">$ {{ formatNum(c.flete_final) }}</td>
                                <td class="px-4 py-2 text-sm text-center">{{ formatFecha(c.fecha_validez) }}</td>
                                <td class="px-4 py-2 text-sm text-center">
                                    <span :class="estaVencida(c) ? 'text-red-600' : 'text-green-700'" class="font-medium">{{ estaVencida(c) ? 'Vencida' : 'Vigente' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>