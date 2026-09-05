<script setup>
import { Head, useForm, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    gastos: Object,
    cuentasContables: Array,
    bancos: Array,
    chequesDisponibles: Array,
    totales: Object,
});

const page = usePage();
const flashSuccess = computed(() => page.props.tt?.flash?.success || page.props.flash?.success || null);
const flashError = computed(() => page.props.tt?.flash?.error || page.props.flash?.error || null);

const form = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    cuenta_contable_id: '',
    moneda: 'ARS',
    importe: '',
    forma_pago: 'efectivo',
    banco_origen_id: '',
    tipo_cheque: 'propio',
    cheque_id: '',
    cheque_banco_id: '',
    cheque_numero: '',
    cheque_importe: '',
    cheque_fecha_vencimiento: '',
    cheque_titular: '',
    fecha_pago: '',
    referencia: '',
    observacion: '',
});

const esTransferencia = computed(() => form.forma_pago === 'transferencia');
const esCheque = computed(() => form.forma_pago === 'cheque');
const esChequePropio = computed(() => form.forma_pago === 'cheque' && form.tipo_cheque === 'propio');
const esChequeTercero = computed(() => form.forma_pago === 'cheque' && form.tipo_cheque === 'tercero');

const submit = () => form.post(route('compras.gastos.store'), { preserveScroll: true });

const formaPagoLabel = (f) => ({ efectivo: 'Efectivo', transferencia: 'Transferencia', cheque: 'Cheque', tarjeta: 'Tarjeta', cuenta_corriente: 'Cuenta corriente' }[f] || f);

// Editar / Eliminar
const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    fecha: '',
    cuenta_contable_id: '',
    moneda: 'ARS',
    importe: '',
    forma_pago: 'efectivo',
    banco_origen_id: '',
    tipo_cheque: 'propio',
    cheque_id: '',
    cheque_banco_id: '',
    cheque_numero: '',
    cheque_importe: '',
    cheque_fecha_vencimiento: '',
    cheque_titular: '',
    fecha_pago: '',
    referencia: '',
    observacion: '',
});
const esEditTransferencia = computed(() => editForm.forma_pago === 'transferencia');
const esEditCheque = computed(() => editForm.forma_pago === 'cheque');
const esEditChequePropio = computed(() => editForm.forma_pago === 'cheque' && editForm.tipo_cheque === 'propio');
const esEditChequeTercero = computed(() => editForm.forma_pago === 'cheque' && editForm.tipo_cheque === 'tercero');

const openEdit = (g) => {
    editId.value = g.id;
    editForm.fecha = String(g.fecha || '').slice(0,10);
    editForm.cuenta_contable_id = g.cuenta_contable_id || '';
    editForm.moneda = g.moneda || 'ARS';
    editForm.importe = g.importe || '';
    editForm.forma_pago = g.forma_pago || 'efectivo';
    editForm.banco_origen_id = g.banco_origen_id || '';
    editForm.tipo_cheque = 'propio';
    editForm.cheque_id = g.cheque_id || '';
    editForm.cheque_banco_id = '';
    editForm.cheque_numero = '';
    editForm.cheque_importe = '';
    editForm.cheque_fecha_vencimiento = '';
    editForm.cheque_titular = '';
    editForm.fecha_pago = g.fecha_pago ? String(g.fecha_pago).slice(0,10) : '';
    editForm.referencia = g.referencia || '';
    editForm.observacion = g.observacion || '';
    editForm.clearErrors();
    editing.value = true;
};
const submitEdit = () => {
    editForm.put(route('compras.gastos.update', editId.value), { preserveScroll: true, onSuccess: () => editing.value = false });
};
const confirmDelete = (g) => {
    if (confirm(`Eliminar gasto #${g.id} - ${g.referencia || g.categoria || ''}?`)) {
        router.delete(route('compras.gastos.destroy', g.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout title="Compras / Gastos sin proveedor">
        <Head title="Compras / Gastos sin proveedor" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compras / Gastos sin proveedor</h2>
                <div class="flex items-center gap-3">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.gastos.export')">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.combustibles.index')">Combustibles</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.index')">Volver a compras</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div v-if="flashSuccess" class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ flashSuccess }}</div>
            <div v-if="flashError" class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ flashError }}</div>
            <div class="bg-white shadow sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><div class="text-xs text-gray-500">Registros</div><div class="text-sm font-medium text-gray-900">{{ totales?.cantidad || 0 }}</div></div>
                <div><div class="text-xs text-gray-500">Total estimado en ARS</div><div class="text-sm font-medium text-gray-900">{{ totales?.importe_total_ars || 0 }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-base font-semibold text-gray-900">Nuevo gasto</h3>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div><InputLabel value="Fecha" /><TextInput v-model="form.fecha" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.fecha" /></div>
                        <div>
                            <InputLabel value="Categoria" />
                            <select v-model="form.cuenta_contable_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar...</option>
                                <option v-for="c in cuentasContables" :key="c.id" :value="c.id">{{ c.codigo }} - {{ c.nombre }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.cuenta_contable_id" />
                        </div>
                        <div><InputLabel value="Moneda" /><select v-model="form.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-2" :message="form.errors.moneda" /></div>
                        <div><InputLabel value="Importe" /><TextInput v-model="form.importe" type="number" min="0.01" step="0.01" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.importe" /></div>
                        <div>
                            <InputLabel value="Forma de pago" />
                            <select v-model="form.forma_pago" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="cheque">Cheque</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="cuenta_corriente">Cuenta corriente</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.forma_pago" />
                        </div>
                        <div v-if="esTransferencia">
                            <InputLabel value="Banco origen" />
                            <select v-model="form.banco_origen_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Seleccionar...</option>
                                <option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.banco_origen_id" />
                        </div>
                        <div><InputLabel value="Fecha pago" /><TextInput v-model="form.fecha_pago" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.fecha_pago" /></div>
                    </div>

                    <div v-if="esCheque" class="border border-gray-200 rounded-lg p-3">
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">Detalle del cheque</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <InputLabel value="Tipo" />
                                <select v-model="form.tipo_cheque" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="propio">Cheque propio</option>
                                    <option value="tercero">Cheque de tercero</option>
                                </select>
                            </div>
                            <template v-if="esChequePropio">
                                <div>
                                    <InputLabel value="Banco" />
                                    <select v-model="form.cheque_banco_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Seleccionar...</option>
                                        <option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.cheque_banco_id" />
                                </div>
                                <div><InputLabel value="Número" /><TextInput v-model="form.cheque_numero" type="text" class="mt-1 block w-full text-sm" /></div>
                                <div><InputLabel value="Importe cheque" /><TextInput v-model="form.cheque_importe" type="number" min="0.01" step="0.01" class="mt-1 block w-full text-sm" /><InputError class="mt-2" :message="form.errors.cheque_importe" /></div>
                                <div><InputLabel value="Vencimiento" /><TextInput v-model="form.cheque_fecha_vencimiento" type="date" class="mt-1 block w-full text-sm" /><InputError class="mt-2" :message="form.errors.cheque_fecha_vencimiento" /></div>
                                <div><InputLabel value="Titular" /><TextInput v-model="form.cheque_titular" type="text" class="mt-1 block w-full text-sm" /></div>
                            </template>
                            <template v-if="esChequeTercero">
                                <div class="sm:col-span-2">
                                    <InputLabel value="Cheque en cartera" />
                                    <select v-model="form.cheque_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Seleccionar...</option>
                                        <option v-for="ch in chequesDisponibles" :key="ch.id" :value="ch.id">{{ ch.banco }} #{{ ch.numero || '—' }} — {{ ch.moneda }} {{ Number(ch.importe).toLocaleString('es-AR', {minimumFractionDigits:2}) }} — Vence: {{ ch.fecha_vencimiento || '—' }}</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.cheque_id" />
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div><InputLabel value="Referencia" /><TextInput v-model="form.referencia" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.referencia" /></div>
                        <div class="sm:col-span-2"><InputLabel value="Observacion" /><TextInput v-model="form.observacion" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.observacion" /></div>
                    </div>
                    <div class="flex justify-end"><PrimaryButton :disabled="form.processing">Guardar</PrimaryButton></div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Gastos registrados</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pago</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referencia</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="g in gastos.data" :key="g.id"><td class="px-6 py-4 text-sm text-gray-700">{{ String(g.fecha || '').slice(0,10) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.cuenta_contable?.nombre || g.categoria }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ formaPagoLabel(g.forma_pago) }}<span v-if="g.banco_origen?.nombre"> / {{ g.banco_origen.nombre }}</span><span v-if="g.cheque"> / {{ g.cheque.banco }} #{{ g.cheque.numero }}</span></td><td class="px-6 py-4 text-sm text-gray-700">{{ g.referencia || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700 text-right font-mono">{{ g.moneda }} {{ Number(g.importe).toLocaleString('es-AR', {minimumFractionDigits:2}) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.observacion || '-' }}</td><td class="px-6 py-4 text-sm text-right whitespace-nowrap"><SecondaryButton class="!text-xs !px-2 !py-1" @click="openEdit(g)">Editar</SecondaryButton><button type="button" class="ml-1 text-xs text-red-600 hover:text-red-800" @click="confirmDelete(g)">Eliminar</button></td></tr><tr v-if="!gastos.data.length"><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">Sin registros.</td></tr></tbody></table></div>
            </div>
        </div>

        <DialogModal :show="editing" max-width="3xl" @close="editing = false">
            <template #title>Editar gasto #{{ editId }}</template>
            <template #content>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div><InputLabel value="Fecha" class="!text-xs" /><TextInput v-model="editForm.fecha" type="date" class="mt-0.5 block w-full text-sm" /><InputError class="mt-1 text-xs" :message="editForm.errors.fecha" /></div>
                        <div><InputLabel value="Categoria" class="!text-xs" /><select v-model="editForm.cuenta_contable_id" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Seleccionar...</option><option v-for="c in cuentasContables" :key="c.id" :value="c.id">{{ c.codigo }} - {{ c.nombre }}</option></select><InputError class="mt-1 text-xs" :message="editForm.errors.cuenta_contable_id" /></div>
                        <div><InputLabel value="Moneda" class="!text-xs" /><select v-model="editForm.moneda" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select></div>
                        <div><InputLabel value="Importe" class="!text-xs" /><TextInput v-model="editForm.importe" type="number" step="0.01" class="mt-0.5 block w-full text-sm" /><InputError class="mt-1 text-xs" :message="editForm.errors.importe" /></div>
                        <div><InputLabel value="Forma de pago" class="!text-xs" /><select v-model="editForm.forma_pago" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="cheque">Cheque</option><option value="tarjeta">Tarjeta</option><option value="cuenta_corriente">Cuenta corriente</option></select></div>
                        <div v-if="esEditTransferencia"><InputLabel value="Banco origen" class="!text-xs" /><select v-model="editForm.banco_origen_id" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Seleccionar...</option><option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option></select></div>
                        <div><InputLabel value="Fecha pago" class="!text-xs" /><TextInput v-model="editForm.fecha_pago" type="date" class="mt-0.5 block w-full text-sm" /></div>
                    </div>
                    <div v-if="esEditCheque" class="border border-gray-200 rounded-lg p-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div><InputLabel value="Tipo cheque" class="!text-xs" /><select v-model="editForm.tipo_cheque" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="propio">Propio</option><option value="tercero">Tercero</option></select></div>
                            <template v-if="esEditChequePropio">
                                <div><InputLabel value="Banco" class="!text-xs" /><select v-model="editForm.cheque_banco_id" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Seleccionar...</option><option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option></select></div>
                                <div><InputLabel value="Número" class="!text-xs" /><TextInput v-model="editForm.cheque_numero" type="text" class="mt-0.5 block w-full text-sm" /></div>
                                <div><InputLabel value="Importe cheque" class="!text-xs" /><TextInput v-model="editForm.cheque_importe" type="number" step="0.01" class="mt-0.5 block w-full text-sm" /></div>
                                <div><InputLabel value="Vencimiento" class="!text-xs" /><TextInput v-model="editForm.cheque_fecha_vencimiento" type="date" class="mt-0.5 block w-full text-sm" /></div>
                                <div><InputLabel value="Titular" class="!text-xs" /><TextInput v-model="editForm.cheque_titular" type="text" class="mt-0.5 block w-full text-sm" /></div>
                            </template>
                            <template v-if="esEditChequeTercero">
                                <div class="sm:col-span-2"><InputLabel value="Cheque en cartera" class="!text-xs" /><select v-model="editForm.cheque_id" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Seleccionar...</option><option v-for="ch in chequesDisponibles" :key="ch.id" :value="ch.id">{{ ch.banco }} #{{ ch.numero || '—' }} — {{ ch.moneda }} {{ Number(ch.importe).toLocaleString('es-AR',{minimumFractionDigits:2}) }}</option></select></div>
                            </template>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><InputLabel value="Referencia" class="!text-xs" /><TextInput v-model="editForm.referencia" type="text" class="mt-0.5 block w-full text-sm" /></div>
                        <div><InputLabel value="Observacion" class="!text-xs" /><TextInput v-model="editForm.observacion" type="text" class="mt-0.5 block w-full text-sm" /></div>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="editing=false">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="editForm.processing" @click="submitEdit">Guardar</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
