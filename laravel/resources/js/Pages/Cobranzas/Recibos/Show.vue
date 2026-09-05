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
const isAdmin = computed(() => (page.props.tt?.roles || []).includes('admin'));

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
    const d = new Date(String(value).slice(0, 10));
    const dd = String(d.getDate()).padStart(2, '0'); const mm = String(d.getMonth() + 1).padStart(2, '0'); const yyyy = d.getFullYear(); return `${dd}-${mm}-${yyyy}`;
};

const formatNum = (n) => {
    const val = Number(n || 0);
    return val.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const comprobanteNumero = (c) => {
    if (!c) return '-';
    if (c.arca_punto_venta && c.arca_numero) {
        return String(parseInt(c.arca_punto_venta)) + '-' + String(c.arca_numero).padStart(8, '0');
    }
    if (c.numero_interno) return '#' + c.numero_interno;
    return c.tipo || '-';
};

const importeRet = (v) => {
    if (!v) return 0;
    return typeof v === 'object' ? Number(v.importe || 0) : Number(v || 0);
};

const totalRetenciones = computed(() => {
    const r = props.recibo.retenciones || {};
    return importeRet(r.iibb) + importeRet(r.iva) + importeRet(r.ganancias);
});

const medioLabel = (medio) => {
    const map = { efectivo: 'Efectivo', transferencia: 'Transferencia', cheque_tercero: 'Cheque tercero', cheque_propio: 'Cheque propio' };
    return map[medio] || medio;
};

const formatChequeDetalle = (detalle) => {
    if (!detalle || typeof detalle !== 'object') return null;
    const parts = [];
    if (detalle.banco) parts.push(`Banco: ${detalle.banco}`);
    if (detalle.numero) parts.push(`N°: ${detalle.numero}`);
    if (detalle.fecha_vencimiento) parts.push(`Vto: ${formatFecha(detalle.fecha_vencimiento)}`);
    if (detalle.titular) parts.push(`Titular: ${detalle.titular}`);
    if (detalle.detalle) parts.push(`Obs: ${detalle.detalle}`);
    return parts.length ? parts : null;
};

const fechaForm = useForm({ fecha: props.recibo.fecha ? String(props.recibo.fecha).slice(0,10) : '' });
const guardarFecha = () => {
    fechaForm.put(route('cobranzas.recibos.fecha.update', props.recibo.id), { preserveScroll: true });
};

const anularForm = useForm({ motivo: '' });
const submitAnular = () => {
    if (!confirm('¿Estas seguro de anular este recibo? Se revertiran los movimientos de cuenta corriente.')) return;
    anularForm.post(route('cobranzas.recibos.anular', props.recibo.id), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :title="`Cobranzas / Recibo #${recibo.id}`">
        <Head :title="`Cobranzas / Recibo #${recibo.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cobranzas / Recibo #{{ recibo.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">{{ formatFecha(recibo.fecha) }}
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="recibo.estado === 'anulada' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'">{{ recibo.estado }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 justify-end">
                    <a :href="route('cobranzas.recibos.print', recibo.id)" target="_blank"><SecondaryButton>Imprimir / PDF</SecondaryButton></a>
                    <Link :href="route('cobranzas.recibos.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                    <template v-if="recibo.estado !== 'anulada'">
                        <TextInput v-model="anularForm.motivo" type="text" class="!w-48 text-xs" placeholder="Motivo de anulacion" />
                        <PrimaryButton class="!bg-red-600 hover:!bg-red-700 !text-xs" :disabled="anularForm.processing || !anularForm.motivo" @click="submitAnular">Anular recibo</PrimaryButton>
                        <InputError :message="anularForm.errors.motivo" />
                    </template>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded">
                {{ flashSuccess }}
            </div>

            <div class="bg-white shadow sm:rounded-lg p-4">
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
                <div v-if="isAdmin && recibo.estado !== 'anulada'" class="mt-3 flex items-end gap-2">
                    <div><InputLabel value="Editar fecha (admin)" class="!text-xs" /><TextInput v-model="fechaForm.fecha" type="date" class="mt-1 block w-full text-sm" /><InputError class="mt-1 text-xs" :message="fechaForm.errors.fecha" /></div>
                    <PrimaryButton class="!text-xs" :disabled="fechaForm.processing" @click="guardarFecha">Guardar fecha</PrimaryButton>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Items</h3></div>
                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="it in (recibo.items || [])" :key="it.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ medioLabel(it.medio) }}</div>
                                <div class="text-xs text-gray-500">{{ it.moneda }} {{ formatNum(it.importe) }}</div>
                            </div>
                            <div class="text-xs text-gray-500">{{ it.moneda === 'ARS' ? '-' : it.cotizacion_ars }}</div>
                        </div>
                        <div v-if="formatChequeDetalle(it.detalle)" class="mt-3 text-xs bg-blue-50 border border-blue-200 rounded p-2 space-y-1">
                            <div v-for="(part, idx) in formatChequeDetalle(it.detalle)" :key="idx" class="text-gray-700">{{ part }}</div>
                        </div>
                        <div v-else-if="it.detalle?.detalle" class="mt-3 text-xs bg-gray-50 border border-gray-200 rounded p-2 text-gray-700">{{ it.detalle.detalle }}</div>
                        <div v-else-if="it.detalle && Object.keys(it.detalle).length" class="mt-3 text-xs bg-gray-50 border border-gray-200 rounded p-2 text-gray-500">{{ JSON.stringify(it.detalle) }}</div>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ medioLabel(it.medio) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.moneda }} {{ formatNum(it.importe) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.moneda === 'ARS' ? '-' : it.cotizacion_ars }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div v-if="formatChequeDetalle(it.detalle)" class="text-xs bg-blue-50 border border-blue-200 rounded p-2 space-y-0.5">
                                        <div v-for="(part, idx) in formatChequeDetalle(it.detalle)" :key="idx" class="text-gray-700">{{ part }}</div>
                                    </div>
                                    <div v-else-if="it.detalle?.detalle" class="text-xs bg-gray-50 border border-gray-200 rounded p-2 text-gray-700">{{ it.detalle.detalle }}</div>
                                    <div v-else-if="it.detalle && Object.keys(it.detalle).length" class="text-xs text-gray-400">{{ JSON.stringify(it.detalle) }}</div>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Imputaciones</h3></div>
                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="ap in (recibo.aplicaciones || [])" :key="ap.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ ap.modo }}</div>
                                <div class="text-xs text-gray-500">{{ ap.comprobante ? comprobanteNumero(ap.comprobante) : '-' }}</div>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.comprobante ? comprobanteNumero(ap.comprobante) : '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.moneda }} {{ ap.importe }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ ap.moneda === 'ARS' ? '-' : ap.cotizacion_ars }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-4">
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
