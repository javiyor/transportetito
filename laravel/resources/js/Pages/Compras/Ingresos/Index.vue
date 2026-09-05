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

const page = usePage();
const flashSuccess = computed(() => page.props.tt?.flash?.success || page.props.flash?.success || null);
const flashError = computed(() => page.props.tt?.flash?.error || page.props.flash?.error || null);

const props = defineProps({
    ingresos: Object,
    cuentasContables: Array,
    bancos: Array,
    totales: Object,
});

const form = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    moneda: 'ARS',
    forma_pago: 'efectivo',
    banco_destino_id: '',
    tipo_cheque: 'fisico',
    cheque_numero: '',
    cheque_fecha_emision: '',
    fecha_cobro: '',
    distribucion: [{ cuenta_contable_id: '', importe: '' }],
    referencia: '',
    observacion: '',
});

const esCheque = computed(() => form.forma_pago === 'cheque');
const esTransferencia = computed(() => form.forma_pago === 'transferencia');

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
    form.post(route('compras.ingresos.store'), { preserveScroll: true });
};

const formaPagoLabel = (f) => ({ efectivo: 'Efectivo', transferencia: 'Transferencia', cheque: 'Cheque', tarjeta: 'Tarjeta' }[f] || f);

const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    fecha: '',
    moneda: 'ARS',
    forma_pago: 'efectivo',
    banco_destino_id: '',
    tipo_cheque: 'fisico',
    cheque_numero: '',
    cheque_fecha_emision: '',
    fecha_cobro: '',
    distribucion: [{ cuenta_contable_id: '', importe: '' }],
    referencia: '',
    observacion: '',
});
const esEditCheque = computed(() => editForm.forma_pago === 'cheque');
const esEditTransferencia = computed(() => editForm.forma_pago === 'transferencia');
const sumaEditDistribucion = computed(() => editForm.distribucion.reduce((s,d)=> s + (parseFloat(d.importe)||0),0));
const distribucionEditOk = computed(() => editForm.distribucion.every(d=> d.cuenta_contable_id && parseFloat(d.importe)>0));
const agregarEditDistribucion = () => editForm.distribucion.push({ cuenta_contable_id: '', importe: '' });
const quitarEditDistribucion = (idx) => { if (editForm.distribucion.length>1) editForm.distribucion.splice(idx,1); };
const openEdit = (g) => {
    editId.value = g.id;
    editForm.fecha = String(g.fecha||'').slice(0,10);
    editForm.moneda = g.moneda || 'ARS';
    editForm.forma_pago = g.forma_pago || g.medio || 'efectivo';
    editForm.banco_destino_id = g.banco_destino_id || '';
    editForm.tipo_cheque = g.detalle?.tipo_cheque || 'fisico';
    editForm.cheque_numero = g.detalle?.numero || '';
    editForm.cheque_fecha_emision = g.detalle?.fecha_emision || '';
    editForm.fecha_cobro = g.fecha_cobro ? String(g.fecha_cobro).slice(0,10) : '';
    editForm.referencia = g.referencia || '';
    editForm.observacion = g.observacion || '';
    if (g.categorias && g.categorias.length) {
        editForm.distribucion = g.categorias.map(c => ({ cuenta_contable_id: c.cuenta_contable_id, importe: c.importe }));
    } else {
        editForm.distribucion = [{ cuenta_contable_id: g.cuenta_contable_id || '', importe: g.importe || '' }];
    }
    editForm.clearErrors();
    editing.value = true;
};
const submitEdit = () => {
    editForm.importe = sumaEditDistribucion.value;
    editForm.put(route('compras.ingresos.update', editId.value), { preserveScroll: true, onSuccess: () => editing.value=false });
};
const confirmDelete = (g) => {
    if (confirm(`Eliminar ingreso #${g.id} ?`)) {
        router.delete(route('compras.ingresos.destroy', g.id), { preserveScroll: true });
    }
};

const detalleResumen = (g) => {
    if (g.forma_pago === 'cheque' && g.detalle) {
        const d = g.detalle;
        return (d.tipo_cheque || '') + ' ' + (d.numero || '');
    }
    if (g.banco_destino?.nombre) return g.banco_destino.nombre;
    return '';
};
</script>

<template>
    <AppLayout title="Compras / Ingresos varios">
        <Head title="Compras / Ingresos varios" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compras / Ingresos varios</h2>
                <div class="flex items-center gap-3">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.ingresos.export')">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.gastos.index')">Gastos</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.index')">Volver a compras</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div v-if="flashSuccess" class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ flashSuccess }}</div>
            <div v-if="flashError" class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ flashError }}</div>
            <div class="bg-white shadow sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><div class="text-xs text-gray-500">Registros</div><div class="text-sm font-medium text-gray-900">{{ totales?.cantidad || 0 }}</div></div>
                <div><div class="text-xs text-gray-500">Total estimado en ARS</div><div class="text-sm font-medium text-gray-900">$ {{ Number(totales?.importe_total_ars || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-base font-semibold text-gray-900">Nuevo ingreso</h3>
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
                            <InputLabel value="Banco destino (acreditacion)" />
                            <select v-model="form.banco_destino_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar...</option>
                                <option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.banco_destino_id" />
                        </div>
                        <div><InputLabel value="Moneda" /><select v-model="form.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-2" :message="form.errors.moneda" /></div>
                        <div><InputLabel value="Fecha cobro" /><TextInput v-model="form.fecha_cobro" type="date" class="mt-1 block w-full" /></div>
                    </div>

                    <div v-if="esCheque" class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Detalle del cheque</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div>
                                <InputLabel value="Tipo" />
                                <select v-model="form.tipo_cheque" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="fisico">Fisico</option>
                                    <option value="echeq">E-cheq</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Nro. Cheque" />
                                <TextInput v-model="form.cheque_numero" type="text" class="mt-1 block w-full text-sm" />
                            </div>
                            <div>
                                <InputLabel value="Fecha emision" />
                                <TextInput v-model="form.cheque_fecha_emision" type="date" class="mt-1 block w-full text-sm" />
                            </div>
                        </div>
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
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Ingresos registrados</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pago</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detalle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Importe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Obs.</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="g in ingresos.data" :key="g.id">
                                <td class="px-6 py-4 text-sm text-gray-700">{{ String(g.fecha || '').slice(0,10) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div v-if="g.categorias?.length">{{ g.categorias.map(c => c.cuenta_contable?.nombre).join(', ') }}</div>
                                    <span v-else>{{ g.cuenta_contable?.nombre || g.categoria }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ formaPagoLabel(g.forma_pago || g.medio) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ detalleResumen(g) || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ g.referencia || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 text-right font-mono">{{ g.moneda }} {{ Number(g.importe).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ g.observacion || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-right whitespace-nowrap"><SecondaryButton class="!text-xs !px-2 !py-1" @click="openEdit(g)">Editar</SecondaryButton><button type="button" class="ml-1 text-xs text-red-600 hover:text-red-800" @click="confirmDelete(g)">Eliminar</button></td>
                            </tr>
                            <tr v-if="!ingresos.data.length"><td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">Sin registros.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <DialogModal :show="editing" max-width="3xl" @close="editing = false">
            <template #title>Editar ingreso #{{ editId }}</template>
            <template #content>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div><InputLabel value="Fecha" class="!text-xs" /><TextInput v-model="editForm.fecha" type="date" class="mt-0.5 block w-full text-sm" /><InputError class="mt-1 text-xs" :message="editForm.errors.fecha" /></div>
                        <div><InputLabel value="Moneda" class="!text-xs" /><select v-model="editForm.moneda" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select></div>
                        <div><InputLabel value="Forma de pago" class="!text-xs" /><select v-model="editForm.forma_pago" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="cheque">Cheque</option><option value="tarjeta">Tarjeta</option></select></div>
                        <div v-if="esEditTransferencia"><InputLabel value="Banco destino" class="!text-xs" /><select v-model="editForm.banco_destino_id" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Seleccionar...</option><option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option></select></div>
                        <div><InputLabel value="Fecha cobro" class="!text-xs" /><TextInput v-model="editForm.fecha_cobro" type="date" class="mt-0.5 block w-full text-sm" /></div>
                    </div>
                    <div v-if="esEditCheque" class="border border-gray-200 rounded-lg p-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div><InputLabel value="Tipo cheque" class="!text-xs" /><select v-model="editForm.tipo_cheque" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="fisico">Fisico</option><option value="echeq">E-cheq</option></select></div>
                            <div><InputLabel value="Nro. Cheque" class="!text-xs" /><TextInput v-model="editForm.cheque_numero" type="text" class="mt-0.5 block w-full text-sm" /></div>
                            <div><InputLabel value="Fecha emision" class="!text-xs" /><TextInput v-model="editForm.cheque_fecha_emision" type="date" class="mt-0.5 block w-full text-sm" /></div>
                        </div>
                    </div>
                    <div class="border border-gray-200 rounded-lg p-3">
                        <div class="flex items-center justify-between mb-2"><span class="text-xs font-semibold text-gray-700">Distribución</span><button type="button" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold" @click="agregarEditDistribucion">+ Agregar</button></div>
                        <div v-for="(d, idx) in editForm.distribucion" :key="idx" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end mb-2">
                            <div><InputLabel :value="'Cuenta '+(idx+1)" class="!text-xs" /><select v-model="d.cuenta_contable_id" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Seleccionar...</option><option v-for="c in cuentasContables" :key="c.id" :value="c.id">{{ c.codigo }} - {{ c.nombre }}</option></select></div>
                            <div><InputLabel value="Importe" class="!text-xs" /><TextInput v-model="d.importe" type="number" step="0.01" class="mt-0.5 block w-full text-sm" /></div>
                            <div class="flex items-end pb-1"><button v-if="editForm.distribucion.length>1" type="button" class="text-red-500 text-lg font-bold" @click="quitarEditDistribucion(idx)">&times;</button></div>
                        </div>
                        <div class="text-xs font-semibold text-gray-700">Total: {{ sumaEditDistribucion.toFixed(2) }}</div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><InputLabel value="Referencia" class="!text-xs" /><TextInput v-model="editForm.referencia" type="text" class="mt-0.5 block w-full text-sm" /></div>
                        <div><InputLabel value="Observacion" class="!text-xs" /><TextInput v-model="editForm.observacion" type="text" class="mt-0.5 block w-full text-sm" /></div>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="editing=false">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="editForm.processing || !distribucionEditOk" @click="submitEdit">Guardar</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>