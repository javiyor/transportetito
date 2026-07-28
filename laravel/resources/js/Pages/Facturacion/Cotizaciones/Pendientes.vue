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
const cotizarId = ref(null);

const abrirCotizar = (c) => {
    cotizarId.value = c.id;
    cotizarForm.flete_sugerido = c.flete_sugerido || '';
    cotizarForm.flete_final = '';
    cotizarForm.fecha_validez = '';
    cotizarForm.observacion = c.observacion || '';
    cotizarForm.clearErrors();
    cotizarDialog.value = true;
};

const submitCotizar = () => {
    cotizarForm.put(route('facturacion.cotizaciones.cotizar', cotizarId.value), {
        preserveScroll: true,
        onSuccess: () => { cotizarDialog.value = false; },
    });
};

const totalItems = (items) => (items || []).reduce((s, i) => s + (Number(i.cantidad) || 0), 0);

const formatFecha = (v) => v ? String(v).slice(0, 10) : '-';
const formatNum = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });
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
                                <div class="text-xs text-gray-500">{{ formatFecha(c.created_at) }} · {{ totalItems(c.items) }} items</div>
                            </div>
                            <SecondaryButton class="!text-xs !px-2 !py-1" @click="abrirCotizar(c)">Cotizar</SecondaryButton>
                        </div>
                        <div class="mt-2 text-xs text-gray-600" v-if="c.origen || c.destino">{{ c.origen || '-' }} → {{ c.destino || '-' }}</div>
                    </div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Remitente</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Destino</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Items</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in pendientes.data" :key="c.id">
                                <td class="px-4 py-2 text-sm text-gray-700">{{ formatFecha(c.created_at) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ c.remitente?.tercero?.razon_social || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ c.destino || c.destinatario?.tercero?.razon_social || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 text-center">{{ totalItems(c.items) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <SecondaryButton class="!text-xs !px-2 !py-1" @click="abrirCotizar(c)">Cotizar</SecondaryButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Cotizar Dialog -->
        <div v-if="cotizarDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="cotizarDialog = false">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Cotizar pedido #{{ cotizarId }}</h3>
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