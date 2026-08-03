<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
    pendientes: Object,
});

const cotizarForm = useForm({
    flete_sugerido: '',
    flete_final: '',
    fecha_validez: '',
    observacion: '',
});

const cotizarDialog = ref(false);
const cotizarItem = ref(null);

const abrirCotizar = (c) => {
    cotizarItem.value = c;
    cotizarForm.flete_sugerido = c.flete_sugerido || '';
    cotizarForm.flete_final = '';
    cotizarForm.fecha_validez = '';
    cotizarForm.observacion = c.observacion || '';
    cotizarForm.clearErrors();
    cotizarDialog.value = true;
};

const submitCotizar = () => {
    cotizarForm.put(route('facturacion.cotizaciones.cotizar', cotizarItem.value.id), {
        preserveScroll: true,
        onSuccess: () => { cotizarDialog.value = false; cotizarItem.value = null; },
    });
};

const totalItems = (items) => (items || []).reduce((s, i) => s + (Number(i.cantidad) || 0), 0);
const totalValor = (items) => (items || []).reduce((s, i) => s + (Number(i.valor_declarado) || 0), 0);

const formatFecha = (v) => v ? String(v).slice(0, 10) : '-';
const formatNum = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });
const ivaLabel = (v) => ({ ri: 'Resp. Inscripto', monotributo: 'Monotributo', consumidor_final: 'Cons. Final', exento: 'Exento' }[v] || v || '-');
</script>

<template>
    <AppLayout title="Facturacion / Cotizaciones / Pendientes">
        <Head title="Facturacion / Cotizaciones / Pendientes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cotizaciones / Pendientes</h2>
                <div class="flex items-center gap-3">
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('facturacion.cotizaciones.pedido.create')">Nuevo pedido</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('facturacion.cotizaciones.consultas')">Consultas</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Pedidos pendientes de cotizar ({{ pendientes.total }})</h3></div>

                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="c in pendientes.data" :key="c.id" class="rounded-lg border border-gray-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ c.remitente?.tercero?.razon_social || '#' + c.tercero_cuenta_id }}</div>
                                <div class="text-xs text-gray-500">{{ formatFecha(c.created_at) }} · {{ totalItems(c.items) }} items · ${{ formatNum(totalValor(c.items)) }}</div>
                            </div>
                            <SecondaryButton class="!text-xs !px-2 !py-1" @click="abrirCotizar(c)">Cotizar</SecondaryButton>
                        </div>
                        <div class="mt-2 text-xs text-gray-600"><span class="font-medium">Origen:</span> {{ c.origen || '-' }}</div>
                        <div class="text-xs text-gray-600"><span class="font-medium">Destino:</span> {{ c.destino || c.destinatario?.tercero?.razon_social || '-' }}</div>
                        <div class="mt-1 text-xs text-gray-500" v-if="c.destinatario?.tercero">Destinatario: {{ c.destinatario.tercero.razon_social }} ({{ ivaLabel(c.destinatario.tercero.condicion_iva) }})</div>
                        <div class="mt-1 text-xs text-gray-500" v-if="c.observacion">Obs: {{ c.observacion }}</div>
                    </div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Remitente</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">CUIT</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Destinatario</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Destino</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Items</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Valor declarado</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in pendientes.data" :key="c.id">
                                <td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap">{{ formatFecha(c.created_at) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-700">{{ c.remitente?.tercero?.razon_social || '-' }}</td>
                                <td class="px-3 py-2 text-sm font-mono text-gray-600">{{ c.remitente?.tercero?.cuit || '-' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-700">{{ c.origen || '-' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-700">
                                    <template v-if="c.destinatario?.tercero">
                                        {{ c.destinatario.tercero.razon_social }}<br><span class="text-xs text-gray-500">{{ ivaLabel(c.destinatario.tercero.condicion_iva) }}</span>
                                    </template>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-3 py-2 text-sm text-gray-700">{{ c.destino || '-' }}</td>
                                <td class="px-3 py-2 text-sm text-gray-700 text-center">{{ totalItems(c.items) }}</td>
                                <td class="px-3 py-2 text-sm text-gray-700 text-right font-mono">$ {{ formatNum(totalValor(c.items)) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <SecondaryButton class="!text-xs !px-2 !py-1" @click="abrirCotizar(c)">Cotizar</SecondaryButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Cotizar Dialog -->
        <div v-if="cotizarDialog && cotizarItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="cotizarDialog = false">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Cotizar pedido</h3>

                <div class="bg-gray-50 rounded-lg p-4 mb-4 text-sm space-y-2">
                    <div class="grid grid-cols-2 gap-x-6 gap-y-1">
                        <div><span class="font-medium text-gray-600">Remitente:</span> {{ cotizarItem.remitente?.tercero?.razon_social || '-' }}</div>
                        <div><span class="font-medium text-gray-600">CUIT:</span> <span class="font-mono">{{ cotizarItem.remitente?.tercero?.cuit || '-' }}</span></div>
                        <div><span class="font-medium text-gray-600">Origen:</span> {{ cotizarItem.origen || '-' }}</div>
                        <div><span class="font-medium text-gray-600">IVA:</span> {{ ivaLabel(cotizarItem.remitente?.tercero?.condicion_iva) }}</div>
                        <div v-if="cotizarItem.destinatario?.tercero"><span class="font-medium text-gray-600">Destinatario:</span> {{ cotizarItem.destinatario.tercero.razon_social }}</div>
                        <div v-if="cotizarItem.destinatario?.tercero"><span class="font-medium text-gray-600">CUIT dest.:</span> <span class="font-mono">{{ cotizarItem.destinatario.tercero.cuit }}</span></div>
                        <div><span class="font-medium text-gray-600">Destino:</span> {{ cotizarItem.destino || '-' }}</div>
                        <div v-if="cotizarItem.observacion"><span class="font-medium text-gray-600">Obs:</span> {{ cotizarItem.observacion }}</div>
                    </div>

                    <div class="mt-3">
                        <h4 class="font-medium text-gray-700 mb-1">Items</h4>
                        <table class="min-w-full text-xs">
                            <thead><tr class="text-gray-500"><th class="text-left pr-2">Cant</th><th class="text-left pr-2">Descripcion</th><th class="text-left pr-2">Tipo</th><th class="text-right pr-2">Valor declarado</th><th class="text-right">CR</th></tr></thead>
                            <tbody>
                                <tr v-for="(it, i) in (cotizarItem.items || [])" :key="i">
                                    <td class="pr-2 text-gray-700">{{ it.cantidad }}</td>
                                    <td class="pr-2 text-gray-700">{{ it.descripcion || '-' }}</td>
                                    <td class="pr-2 text-gray-500">{{ it.tipo || '-' }}</td>
                                    <td class="pr-2 text-right text-gray-700 font-mono">$ {{ formatNum(it.valor_declarado) }}</td>
                                    <td class="text-right text-gray-700 font-mono">$ {{ formatNum(it.cr) }}</td>
                                </tr>
                            </tbody>
                            <tfoot><tr class="font-semibold border-t border-gray-300"><td>{{ totalItems(cotizarItem.items) }}</td><td></td><td></td><td class="text-right">$ {{ formatNum(totalValor(cotizarItem.items)) }}</td><td></td></tr></tfoot>
                        </table>
                    </div>
                </div>

                <form @submit.prevent="submitCotizar" class="space-y-3">
                    <div>
                        <InputLabel value="Flete sugerido (opcional)" />
                        <TextInput v-model="cotizarForm.flete_sugerido" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Flete final *" />
                        <TextInput v-model="cotizarForm.flete_final" type="number" min="0.01" step="0.01" class="mt-1 block w-full" />
                        <p v-if="cotizarForm.errors.flete_final" class="text-sm text-red-600 mt-1">{{ cotizarForm.errors.flete_final }}</p>
                    </div>
                    <div>
                        <InputLabel value="Fecha validez *" />
                        <TextInput v-model="cotizarForm.fecha_validez" type="date" class="mt-1 block w-full" />
                        <p v-if="cotizarForm.errors.fecha_validez" class="text-sm text-red-600 mt-1">{{ cotizarForm.errors.fecha_validez }}</p>
                    </div>
                    <div>
                        <InputLabel value="Observacion" />
                        <textarea v-model="cotizarForm.observacion" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2"></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton type="button" @click="cotizarDialog = false">Cancelar</SecondaryButton>
                        <PrimaryButton :disabled="cotizarForm.processing">Cotizar</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>