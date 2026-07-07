<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    recibo: Object,
});

const page = usePage();
const flashSuccess = computed(() => page.props.tt?.flash?.success);

const normalizarRet = (v) => {
    if (!v || typeof v === 'number' || typeof v === 'string') return { descripcion: '', importe: Number(v || 0) || '' };
    return { descripcion: v.descripcion || '', importe: v.importe ?? '' };
};

const retencionesForm = useForm({
    retenciones: {
        iibb: normalizarRet(props.recibo.retenciones?.iibb),
        iva: normalizarRet(props.recibo.retenciones?.iva),
        ganancias: normalizarRet(props.recibo.retenciones?.ganancias),
    },
});

const guardarRetenciones = () => {
    retencionesForm.put(route('cobranzas.recibos.retenciones.update', props.recibo.id), {
        preserveScroll: true,
    });
};

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};

const formatNum = (n) => {
    const val = Number(n || 0);
    return val.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const importeRet = (v) => {
    if (!v) return 0;
    return typeof v === 'object' ? Number(v.importe || 0) : Number(v || 0);
};

const totalRetenciones = computed(() => {
    const r = props.recibo.retenciones || {};
    return importeRet(r.iibb) + importeRet(r.iva) + importeRet(r.ganancias);
});
</script>

<template>
    <AppLayout :title="`Cobranzas / Recibo #${recibo.id}`">
        <Head :title="`Cobranzas / Recibo #${recibo.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Recibo #{{ recibo.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">{{ formatFecha(recibo.fecha) }} · Estado: {{ recibo.estado }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2 justify-end">
                    <a :href="route('cobranzas.recibos.print', recibo.id)" target="_blank"><SecondaryButton>Imprimir / PDF</SecondaryButton></a>
                    <Link :href="route('cobranzas.recibos.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded">
                {{ flashSuccess }}
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Cuenta</div>
                        <div class="text-sm font-medium text-gray-900">{{ recibo.cuenta?.tercero?.razon_social || '-' }}</div>
                        <div class="text-xs text-gray-500">CUIT {{ recibo.cuenta?.tercero?.cuit || '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Total cobrado</div>
                        <div class="text-sm font-medium text-gray-900">{{ recibo.moneda }} {{ formatNum(recibo.total) }}</div>
                        <div v-if="recibo.moneda !== 'ARS'" class="text-xs text-gray-500">Cotizacion {{ recibo.cotizacion_ars }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Pre-recibo origen</div>
                        <div class="text-sm font-medium text-gray-900">{{ recibo.pre_recibo_id ? ('#' + recibo.pre_recibo_id) : '-' }}</div>
                    </div>
                    <div v-if="totalRetenciones > 0">
                        <div class="text-xs text-gray-500">Retenciones</div>
                        <div class="text-sm font-medium text-amber-700">{{ recibo.moneda }} {{ formatNum(totalRetenciones) }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Items</h3></div>
                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="it in (recibo.items || [])" :key="it.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ it.medio }}</div>
                                <div class="text-xs text-gray-500">{{ it.moneda }} {{ it.importe }}</div>
                            </div>
                            <div class="text-xs text-gray-500">{{ it.moneda === 'ARS' ? '-' : it.cotizacion_ars }}</div>
                        </div>
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
                            <tr v-for="it in (recibo.items || [])" :key="it.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.medio }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.moneda }} {{ it.importe }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.moneda === 'ARS' ? '-' : it.cotizacion_ars }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700"><pre class="text-xs bg-gray-50 border border-gray-200 rounded p-2 overflow-auto">{{ it.detalle ? JSON.stringify(it.detalle, null, 2) : '' }}</pre></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Aplicaciones</h3></div>
                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="ap in (recibo.aplicaciones || [])" :key="ap.id" class="rounded-lg border border-gray-200 p-4">
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
                            <tr v-for="ap in (recibo.aplicaciones || [])" :key="ap.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.modo }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.comprobante_id ? ('#' + ap.comprobante_id) : '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.moneda }} {{ ap.importe }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.moneda === 'ARS' ? '-' : ap.cotizacion_ars }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Retenciones de impuestos</h3>
                <form @submit.prevent="guardarRetenciones" class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div><InputLabel value="IIBB Descripcion" /><TextInput v-model="retencionesForm.retenciones.iibb.descripcion" type="text" class="mt-1 block w-full" placeholder="Descripcion" /></div>
                        <div><InputLabel value="IIBB Importe" /><TextInput v-model="retencionesForm.retenciones.iibb.importe" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="0.00" /></div>
                        <div class="flex items-end"><PrimaryButton :disabled="retencionesForm.processing">Guardar retenciones</PrimaryButton></div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div><InputLabel value="IVA Descripcion" /><TextInput v-model="retencionesForm.retenciones.iva.descripcion" type="text" class="mt-1 block w-full" placeholder="Descripcion" /></div>
                        <div><InputLabel value="IVA Importe" /><TextInput v-model="retencionesForm.retenciones.iva.importe" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="0.00" /></div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div><InputLabel value="Ganancias Descripcion" /><TextInput v-model="retencionesForm.retenciones.ganancias.descripcion" type="text" class="mt-1 block w-full" placeholder="Descripcion" /></div>
                        <div><InputLabel value="Ganancias Importe" /><TextInput v-model="retencionesForm.retenciones.ganancias.importe" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="0.00" /></div>
                    </div>
                </form>
                <InputError class="mt-2" :message="retencionesForm.errors.retenciones" />
            </div>
        </div>
    </AppLayout>
</template>
