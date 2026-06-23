<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed } from 'vue';

const props = defineProps({
    preRecibo: Object,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const confirmForm = useForm({
    send_email: !!(props.preRecibo?.cuenta?.email),
});
const confirmar = () => {
    confirmForm.post(route('cobranzas.pre-recibos.confirm', props.preRecibo.id));
};

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};
</script>

<template>
    <AppLayout :title="`Cobranzas / Pre-recibo #${preRecibo.id}`">
        <Head :title="`Cobranzas / Pre-recibo #${preRecibo.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Pre-recibo #{{ preRecibo.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ formatFecha(preRecibo.fecha) }} · {{ preRecibo.hoja_ruta?.deposito?.nombre || '-' }} · Estado: {{ preRecibo.estado }}
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 justify-end">
                    <a :href="route('cobranzas.pre-recibos.print', preRecibo.id)" target="_blank"><SecondaryButton>Imprimir / PDF</SecondaryButton></a>
                    <Link :href="route('cobranzas.recibos.index')"><SecondaryButton>Recibos</SecondaryButton></Link>
                    <SecondaryButton :href="route('cobranzas.pre-recibos.index')" as="a">Volver</SecondaryButton>
                    <label v-if="preRecibo.estado !== 'confirmado' && preRecibo.cuenta?.email" class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" v-model="confirmForm.send_email" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        Enviar recibo por email
                    </label>
                    <PrimaryButton v-if="preRecibo.estado !== 'confirmado'" :disabled="confirmForm.processing" @click.prevent="confirmar">Confirmar</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded">
                {{ flashSuccess }}
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Cuenta</div>
                        <div class="text-sm font-medium text-gray-900">{{ preRecibo.cuenta?.tercero?.razon_social || '-' }}</div>
                        <div class="text-xs text-gray-500">CUIT {{ preRecibo.cuenta?.tercero?.cuit || '-' }} · Nro {{ preRecibo.cuenta?.numero_cliente || '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Total</div>
                        <div class="text-sm font-medium text-gray-900">{{ preRecibo.moneda }} {{ preRecibo.total }}</div>
                        <div v-if="preRecibo.moneda !== 'ARS'" class="text-xs text-gray-500">Cotizacion {{ preRecibo.cotizacion_ars }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Hoja</div>
                        <div class="text-sm font-medium text-gray-900">
                            <Link class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.repartos.hojas.show', preRecibo.hoja_ruta_id)">Hoja #{{ preRecibo.hoja_ruta_id }}</Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Items de cobro</h3>
                </div>

                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="it in (preRecibo.items || [])" :key="it.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ it.medio }}</div>
                                <div class="text-xs text-gray-500">{{ it.moneda }} {{ it.importe }}</div>
                            </div>
                            <div class="text-xs text-gray-500">{{ it.moneda === 'ARS' ? '-' : it.cotizacion_ars }}</div>
                        </div>
                        <template v-if="it.detalle?.foto_remito_firmado">
                            <a :href="'/storage/' + it.detalle.foto_remito_firmado" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 underline block mt-2">Ver remito firmado</a>
                        </template>
                        <template v-if="it.detalle?.foto_comprobante_pago">
                            <a :href="'/storage/' + it.detalle.foto_comprobante_pago" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 underline block">Ver comprobante pago</a>
                        </template>
                        <pre class="mt-3 text-xs bg-gray-50 border border-gray-200 rounded p-2 overflow-auto whitespace-pre-wrap">{{ it.detalle ? JSON.stringify(it.detalle, null, 2) : '' }}</pre>
                    </div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="it in (preRecibo.items || [])" :key="it.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.medio }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.moneda }} {{ it.importe }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.moneda === 'ARS' ? '-' : it.cotizacion_ars }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <template v-if="it.detalle?.foto_remito_firmado">
                                        <a :href="'/storage/' + it.detalle.foto_remito_firmado" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 underline block">Ver remito firmado</a>
                                    </template>
                                    <template v-if="it.detalle?.foto_comprobante_pago">
                                        <a :href="'/storage/' + it.detalle.foto_comprobante_pago" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 underline block">Ver comprobante pago</a>
                                    </template>
                                    <pre v-if="it.detalle" class="text-xs bg-gray-50 border border-gray-200 rounded p-2 overflow-auto mt-1">{{ JSON.stringify(it.detalle, null, 2) }}</pre>
                                </td>
                            </tr>

                            <tr v-if="!(preRecibo.items || []).length">
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">Sin items.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Aplicaciones</h3>
                </div>

                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="ap in (preRecibo.aplicaciones || [])" :key="ap.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ ap.modo }}</div>
                                <div class="text-xs text-gray-500">{{ ap.comprobante_id ? ('#' + ap.comprobante_id) : '-' }}</div>
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ ap.moneda }} {{ ap.importe }}</div>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">Cotizacion: {{ ap.moneda === 'ARS' ? '-' : ap.cotizacion_ars }}</div>
                    </div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comprobante</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="ap in (preRecibo.aplicaciones || [])" :key="ap.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.modo }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.comprobante_id ? ('#' + ap.comprobante_id) : '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.moneda }} {{ ap.importe }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.moneda === 'ARS' ? '-' : ap.cotizacion_ars }}</td>
                            </tr>

                            <tr v-if="!(preRecibo.aplicaciones || []).length">
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">Sin aplicaciones.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
