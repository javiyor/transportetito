<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';

const props = defineProps({
    egresos: Object,
    cuentasContables: Array,
    bancos: Array,
    totales: Object,
});

const form = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    moneda: 'ARS',
    forma_pago: 'efectivo',
    banco_origen_id: '',
    cheque_id: '',
    fecha_pago: '',
    distribucion: [{ cuenta_contable_id: '', importe: '' }],
    referencia: '',
    observacion: '',
});

const esTransferencia = computed(() => form.forma_pago === 'transferencia');
const esCheque = computed(() => form.forma_pago === 'cheque');

const sumaDistribucion = computed(() => {
    return form.distribucion.reduce((s, d) => s + (parseFloat(d.importe) || 0), 0);
});

const distribucionOk = computed(() => {
    return form.distribucion.every(d => d.cuenta_contable_id && parseFloat(d.importe) > 0);
});

const agregarDistribucion = () => {
    form.distribucion.push({ cuenta_contable_id: '', importe: '' });
};

const quitarDistribucion = (idx) => {
    if (form.distribucion.length > 1) {
        form.distribucion.splice(idx, 1);
    }
};

const submit = () => {
    form.importe = sumaDistribucion.value;
    form.post(route('finanzas.egresos.store'), { preserveScroll: true });
};

const formaPagoLabel = (f) => ({ efectivo: 'Efectivo', transferencia: 'Transferencia', cheque: 'Cheque', tarjeta: 'Tarjeta' }[f] || f);
</script>

<template>
    <AppLayout title="Finanzas / Egresos varios">
        <Head title="Finanzas / Egresos varios" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Finanzas / Egresos varios</h2>
                <div class="flex items-center gap-3">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('finanzas.egresos.export')">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.ingresos.index')">Ingresos varios</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.resumen-arca')">Resumen ARCA</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><div class="text-xs text-gray-500">Registros</div><div class="text-sm font-medium text-gray-900">{{ totales?.cantidad || 0 }}</div></div>
                <div><div class="text-xs text-gray-500">Total estimado en ARS</div><div class="text-sm font-medium text-gray-900">$ {{ Number(totales?.importe_total_ars || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nuevo egreso</h3>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div><InputLabel value="Fecha" /><TextInput v-model="form.fecha" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.fecha" /></div>
                        <div>
                            <InputLabel value="Forma de pago" />
                            <select v-model="form.forma_pago" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="cheque">Cheque</option>
                                <option value="tarjeta">Tarjeta</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.forma_pago" />
                        </div>
                        <div v-if="esTransferencia">
                            <InputLabel value="Banco origen (debito)" />
                            <select v-model="form.banco_origen_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar...</option>
                                <option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.banco_origen_id" />
                        </div>
                        <div v-if="esCheque">
                            <InputLabel value="Cheque ID" />
                            <TextInput v-model="form.cheque_id" type="number" min="1" class="mt-1 block w-full" placeholder="ID cheque" />
                            <InputError class="mt-2" :message="form.errors.cheque_id" />
                        </div>
                        <div><InputLabel value="Moneda" /><select v-model="form.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-2" :message="form.errors.moneda" /></div>
                        <div><InputLabel value="Fecha pago" /><TextInput v-model="form.fecha_pago" type="date" class="mt-1 block w-full" /></div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-gray-900">Distribucion por cuentas contables</h4>
                            <SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="agregarDistribucion">+ Agregar</SecondaryButton>
                        </div>
                        <div v-for="(d, idx) in form.distribucion" :key="idx" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end mb-2">
                            <div>
                                <InputLabel :value="'Cuenta ' + (idx + 1)" />
                                <select v-model="d.cuenta_contable_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="c in cuentasContables" :key="c.id" :value="c.id">{{ c.codigo }} - {{ c.nombre }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Importe" />
                                <TextInput v-model="d.importe" type="number" min="0.01" step="0.01" class="mt-1 block w-full text-sm" />
                            </div>
                            <div class="flex items-end pb-1">
                                <button v-if="form.distribucion.length > 1" type="button" class="text-red-500 text-lg font-bold" @click="quitarDistribucion(idx)">&times;</button>
                            </div>
                        </div>
                        <div class="text-sm font-semibold text-gray-700 mt-2">
                            Total distribuido: {{ sumaDistribucion.toFixed(2) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><InputLabel value="Referencia" /><TextInput v-model="form.referencia" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.referencia" /></div>
                        <div><InputLabel value="Observacion" /><TextInput v-model="form.observacion" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.observacion" /></div>
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing || !distribucionOk">Guardar y contabilizar</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Egresos registrados</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pago</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Importe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Obs.</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="g in egresos.data" :key="g.id">
                                <td class="px-6 py-4 text-sm text-gray-700">{{ String(g.fecha || '').slice(0,10) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div v-if="g.categorias?.length">{{ g.categorias.map(c => c.cuenta_contable?.nombre).join(', ') }}</div>
                                    <span v-else>{{ g.cuenta_contable?.nombre || g.categoria }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ formaPagoLabel(g.forma_pago) }}<span v-if="g.banco_origen?.nombre"> / {{ g.banco_origen.nombre }}</span></td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ g.referencia || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 text-right font-mono">{{ g.moneda }} {{ Number(g.importe).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ g.observacion || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>