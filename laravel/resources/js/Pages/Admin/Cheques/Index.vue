<script setup>
import { Head, router, useForm, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { ref, nextTick, computed } from 'vue';

const page = usePage();
const flashSuccess = computed(() => page.props.tt?.flash?.success || page.props.flash?.success || null);
const flashError = computed(() => page.props.tt?.flash?.error || page.props.flash?.error || null);

const props = defineProps({
    chequesPropios: Object,
    chequesTerceros: Object,
    totalesPropios: Object,
    totalesTerceros: Object,
    empresas: Array,
    empresaId: [Number, null],
    filtros: Object,
    bancos: Array,
});

const secciones = computed(() => [
    {
        key: 'propio',
        titulo: 'Cheques propios',
        cheques: props.chequesPropios,
        totales: props.totalesPropios,
        pageParam: 'propios_page',
    },
    {
        key: 'tercero',
        titulo: 'Cheques de terceros',
        cheques: props.chequesTerceros,
        totales: props.totalesTerceros,
        pageParam: 'terceros_page',
    },
]);

const bancosPropios = computed(() => (props.bancos || []).filter((b) => !!b.es_propio));

const showForm = ref(false);
const createForm = useForm({
    tipo: 'fisico',
    origen: 'tercero',
    numero: '',
    banco: '',
    importe: '',
    moneda: 'ARS',
    fecha_emision: new Date().toISOString().slice(0, 10),
    fecha_vencimiento: '',
    titular: '',
    librado_por: '',
    observacion: '',
});

const submitCreate = () => {
    createForm.post(route('admin.cheques.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showForm.value = false;
        },
    });
};

const editId = ref(null);
const editFormRef = ref(null);
const editForm = useForm({
    origen: '',
    estado: '',
    tipo: '',
    numero: '',
    banco: '',
    fecha_deposito: '',
    fecha_cobro: '',
    fecha_rechazo: '',
    endosado_a: '',
    banco_deposito_id: '',
    observacion: '',
});

const openEdit = (c) => {
    editId.value = c.id;
    editForm.origen = c.origen;
    editForm.estado = c.estado;
    editForm.tipo = c.tipo;
    editForm.numero = c.numero || '';
    editForm.banco = c.banco || '';
    editForm.fecha_deposito = c.fecha_deposito ? String(c.fecha_deposito).slice(0, 10) : '';
    editForm.fecha_cobro = c.fecha_cobro ? String(c.fecha_cobro).slice(0, 10) : '';
    editForm.fecha_rechazo = c.fecha_rechazo ? String(c.fecha_rechazo).slice(0, 10) : '';
    editForm.endosado_a = c.endosado_a || '';
    editForm.banco_deposito_id = c.banco_deposito_id || '';
    editForm.observacion = c.observacion || '';
    editForm.clearErrors();
    nextTick(() => {
        editFormRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const submitEdit = () => {
    editForm.put(route('admin.cheques.update', editId.value), {
        preserveScroll: true,
        onSuccess: () => { editId.value = null; },
    });
};

const deleteId = ref(null);
const deleteInfo = ref(null);
const deleteProcessing = ref(false);

const openDelete = (c) => {
    deleteId.value = c.id;
    deleteInfo.value = `#${c.id} · ${c.banco || '-'} · ${c.moneda} ${c.importe}`;
    if (editId.value === c.id) editId.value = null;
};

const submitDelete = () => {
    if (!deleteId.value) return;
    deleteProcessing.value = true;
    router.delete(route('admin.cheques.destroy', deleteId.value), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            deleteId.value = null;
            deleteInfo.value = null;
        },
    });
};

const filtrosPropios = useForm({
    estado: props.filtros.propios.estado,
    tipo: props.filtros.propios.tipo,
    desde: props.filtros.propios.desde,
    hasta: props.filtros.propios.hasta,
});

const filtrosTerceros = useForm({
    estado: props.filtros.terceros.estado,
    tipo: props.filtros.terceros.tipo,
    desde: props.filtros.terceros.desde,
    hasta: props.filtros.terceros.hasta,
});

const filtrosPorSeccion = {
    propio: filtrosPropios,
    tercero: filtrosTerceros,
};

const applyFilters = () => {
    router.get(route('admin.cheques.index'), {
        ...(filtrosPropios.estado && { p_estado: filtrosPropios.estado }),
        ...(filtrosPropios.tipo && { p_tipo: filtrosPropios.tipo }),
        ...(filtrosPropios.desde && { p_desde: filtrosPropios.desde }),
        ...(filtrosPropios.hasta && { p_hasta: filtrosPropios.hasta }),
        ...(filtrosTerceros.estado && { t_estado: filtrosTerceros.estado }),
        ...(filtrosTerceros.tipo && { t_tipo: filtrosTerceros.tipo }),
        ...(filtrosTerceros.desde && { t_desde: filtrosTerceros.desde }),
        ...(filtrosTerceros.hasta && { t_hasta: filtrosTerceros.hasta }),
        ...(props.empresaId && { empresa_id: props.empresaId }),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const estadosPropio = ['emitido', 'pagado', 'rechazado', 'vencido', 'reemplazado'];
const estadosTercero = ['en_cartera', 'depositado', 'cobrado', 'rechazado', 'endosado', 'anulado'];

const estadoBadgeClass = (estado) => {
    const map = {
        en_cartera: 'bg-yellow-100 text-yellow-800',
        depositado: 'bg-blue-100 text-blue-800',
        cobrado: 'bg-green-100 text-green-800',
        rechazado: 'bg-red-100 text-red-800',
        endosado: 'bg-purple-100 text-purple-800',
        anulado: 'bg-gray-100 text-gray-800',
        emitido: 'bg-indigo-100 text-indigo-800',
        pagado: 'bg-green-100 text-green-800',
        vencido: 'bg-orange-100 text-orange-800',
        reemplazado: 'bg-gray-100 text-gray-800',
    };
    return map[estado] || 'bg-gray-100 text-gray-800';
};

const estadoLabel = (e) => {
    const map = {
        en_cartera: 'En cartera',
        depositado: 'Depositado',
        cobrado: 'Cobrado',
        rechazado: 'Rechazado',
        endosado: 'Endosado',
        anulado: 'Anulado',
        emitido: 'Emitido',
        pagado: 'Pagado',
        vencido: 'Vencido',
        reemplazado: 'Reemplazado',
    };
    return map[e] || e;
};

const origenLabel = (o) => {
    const map = { propio: 'Propio', tercero: 'Tercero' };
    return map[o] || o;
};

const tipoLabel = (t) => {
    const map = { fisico: 'Físico', echeq: 'E-Cheq' };
    return map[t] || t;
};

const formatFecha = (v) => {
    if (!v) return '-';
    const s = String(v).slice(0, 10);
    const d = new Date(s + 'T12:00:00');
    if (isNaN(d.getTime())) return s.split('-').reverse().join('-');
    const dd = String(d.getDate()).padStart(2, '0'); const mm = String(d.getMonth() + 1).padStart(2, '0'); const yyyy = d.getFullYear(); return `${dd}-${mm}-${yyyy}`;
};
</script>

<template>
    <AppLayout title="Admin / Cheques">
        <Head title="Admin / Cheques" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Cheques</h2>
                <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('admin.bancos.index')">Bancos</Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-2 rounded text-sm">{{ flashSuccess }}</div>
            <div v-if="flashError" class="bg-red-50 border border-red-200 text-red-900 px-4 py-2 rounded text-sm">{{ flashError }}</div>
            <div class="flex justify-end">
                <PrimaryButton @click="showForm = !showForm">{{ showForm ? 'Cancelar' : '+ Nuevo cheque' }}</PrimaryButton>
            </div>

            <div v-if="showForm" class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Nuevo cheque</h3>
                <form class="grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submitCreate">
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Tipo</div>
                        <select v-model="createForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="fisico">Físico</option>
                            <option value="echeq">E-Cheq</option>
                        </select>
                        <InputError :message="createForm.errors.tipo" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Origen</div>
                        <select v-model="createForm.origen" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="propio">Propio</option>
                            <option value="tercero">Tercero</option>
                        </select>
                        <InputError :message="createForm.errors.origen" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Nro cheque</div>
                        <TextInput v-model="createForm.numero" type="text" class="block w-full" placeholder="Número" />
                        <InputError :message="createForm.errors.numero" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Banco{{ createForm.origen === 'propio' ? ' (cuenta propia)' : '' }}</div>
                        <select v-model="createForm.banco" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">(seleccionar)</option>
                            <option v-for="b in (createForm.origen === 'propio' ? bancosPropios : bancos)" :key="b.id" :value="b.nombre">{{ b.nombre }}</option>
                        </select>
                        <InputError :message="createForm.errors.banco" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Importe</div>
                        <TextInput v-model="createForm.importe" type="number" min="0.01" step="0.01" class="block w-full" placeholder="0.00" />
                        <InputError :message="createForm.errors.importe" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Moneda</div>
                        <select v-model="createForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="ARS">ARS</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="BRL">BRL</option>
                        </select>
                        <InputError :message="createForm.errors.moneda" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Fecha emisión</div>
                        <TextInput v-model="createForm.fecha_emision" type="date" class="block w-full" />
                        <InputError :message="createForm.errors.fecha_emision" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Fecha vencimiento</div>
                        <TextInput v-model="createForm.fecha_vencimiento" type="date" class="block w-full" />
                        <InputError :message="createForm.errors.fecha_vencimiento" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Titular</div>
                        <TextInput v-model="createForm.titular" type="text" class="block w-full" placeholder="Titular" />
                        <InputError :message="createForm.errors.titular" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Librado por</div>
                        <TextInput v-model="createForm.librado_por" type="text" class="block w-full" placeholder="Librado por" />
                        <InputError :message="createForm.errors.librado_por" />
                    </div>
                    <div class="sm:col-span-4">
                        <div class="text-xs font-medium text-gray-700 mb-1">Observación</div>
                        <textarea v-model="createForm.observacion" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" rows="2"></textarea>
                        <InputError :message="createForm.errors.observacion" />
                    </div>
                    <div class="sm:col-span-4 flex justify-end gap-2">
                        <SecondaryButton type="button" @click="showForm = false">Cancelar</SecondaryButton>
                        <PrimaryButton :disabled="createForm.processing">Guardar cheque</PrimaryButton>
                    </div>
                </form>
            </div>

            <div v-for="seccion in secciones" :key="seccion.key" class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="px-3 py-2 border-b border-gray-200 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-sm text-gray-900">{{ seccion.titulo }}</h3>
                        <div class="flex gap-2 text-xs">
                            <span class="font-medium text-gray-700">Físico: <span class="font-mono">{{ seccion.totales.fisico.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</span></span>
                            <span class="font-medium text-gray-700">E-Cheq: <span class="font-mono">{{ seccion.totales.echeq.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</span></span>
                            <span class="font-medium text-gray-900">Total: <span class="font-mono">{{ (seccion.totales.fisico + seccion.totales.echeq).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</span></span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="seccion.key === 'propio'">
                            <span class="text-[10px] font-medium text-gray-500 uppercase">Filtro</span>
                            <select v-model="filtrosPropios.estado" class="border-gray-300 rounded-md shadow-sm text-[10px] py-0.5 px-1.5">
                                <option value="">Estado</option>
                                <option v-for="e in estadosPropio" :key="e" :value="e">{{ estadoLabel(e) }}</option>
                            </select>
                            <select v-model="filtrosPropios.tipo" class="border-gray-300 rounded-md shadow-sm text-[10px] py-0.5 px-1.5">
                                <option value="">Tipo</option>
                                <option value="fisico">Físico</option>
                                <option value="echeq">E-Cheq</option>
                            </select>
                            <input v-model="filtrosPropios.desde" type="date" class="border-gray-300 rounded-md shadow-sm text-[10px] py-0.5 px-1.5" />
                            <input v-model="filtrosPropios.hasta" type="date" class="border-gray-300 rounded-md shadow-sm text-[10px] py-0.5 px-1.5" />
                        </template>
                        <template v-else>
                            <span class="text-[10px] font-medium text-gray-500 uppercase">Filtro</span>
                            <select v-model="filtrosTerceros.estado" class="border-gray-300 rounded-md shadow-sm text-[10px] py-0.5 px-1.5">
                                <option value="">Estado</option>
                                <option v-for="e in estadosTercero" :key="e" :value="e">{{ estadoLabel(e) }}</option>
                            </select>
                            <select v-model="filtrosTerceros.tipo" class="border-gray-300 rounded-md shadow-sm text-[10px] py-0.5 px-1.5">
                                <option value="">Tipo</option>
                                <option value="fisico">Físico</option>
                                <option value="echeq">E-Cheq</option>
                            </select>
                            <input v-model="filtrosTerceros.desde" type="date" class="border-gray-300 rounded-md shadow-sm text-[10px] py-0.5 px-1.5" />
                            <input v-model="filtrosTerceros.hasta" type="date" class="border-gray-300 rounded-md shadow-sm text-[10px] py-0.5 px-1.5" />
                        </template>
                        <SecondaryButton class="!text-[10px] !px-2 !py-0.5" @click="applyFilters">Filtrar</SecondaryButton>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Nro</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Banco</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Importe</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Venc.</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Librado por</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Endosado a</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Depósito</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Cobro</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Banco depósito</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Mov. bancario</th>
                                <th class="px-2 py-1 text-left text-[10px] font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-2 py-1 text-right text-[10px] font-medium text-gray-500 uppercase">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in seccion.cheques.data" :key="c.id">
                                <td class="px-2 py-0.5 text-xs font-mono text-gray-900">#{{ c.id }}</td>
                                <td class="px-2 py-0.5 text-xs text-gray-700">{{ tipoLabel(c.tipo) }}</td>
                                <td class="px-2 py-0.5 text-xs font-mono text-gray-900">{{ c.numero || '-' }}</td>
                                <td class="px-2 py-0.5 text-xs text-gray-700">{{ c.banco || '-' }}</td>
                                <td class="px-2 py-0.5 text-xs font-mono text-gray-900">{{ c.moneda }} {{ c.importe }}</td>
                                <td class="px-2 py-0.5 text-xs text-gray-700">{{ formatFecha(c.fecha_vencimiento) }}</td>
                                <td class="px-2 py-0.5 text-xs text-gray-700">{{ c.librado_por || '-' }}</td>
                                <td class="px-2 py-0.5 text-xs text-gray-700">{{ c.endosado_a || '-' }}</td>
                                <td class="px-2 py-0.5 text-xs text-gray-700">{{ formatFecha(c.fecha_deposito) }}</td>
                                <td class="px-2 py-0.5 text-xs text-gray-700">{{ formatFecha(c.fecha_cobro) }}</td>
                                <td class="px-2 py-0.5 text-xs text-gray-700">{{ c.banco_deposito?.nombre || c.bancoDeposito?.nombre || '-' }}<span v-if="c.estado_deposito" class="ml-1 text-[10px]" :class="c.estado_deposito === 'pendiente' ? 'text-blue-600' : 'text-green-600'">({{ c.estado_deposito }})</span></td>
                                <td class="px-2 py-0.5 text-xs text-gray-700"><span v-if="c.movimiento_bancario_id" class="text-[10px] text-indigo-600">#{{ c.movimiento_bancario_id }}<span v-if="c.movimientoBancario?.contabilizado" class="text-green-600"> ✓</span><span v-else class="text-amber-600"> ⏳</span></span><span v-else class="text-gray-400">-</span></td>
                                <td class="px-2 py-0.5 text-xs">
                                    <span class="inline-flex items-center rounded-full px-2 py-0 text-[10px] font-medium" :class="estadoBadgeClass(c.estado)">{{ estadoLabel(c.estado) }}</span>
                                </td>
                                <td class="px-2 py-0.5 text-right text-xs whitespace-nowrap">
                                    <SecondaryButton class="!text-[10px] !px-2 !py-0.5" @click="openEdit(c)">Editar</SecondaryButton>
                                    <button type="button" class="ms-1 text-[10px] text-red-600 hover:text-red-800" @click="openDelete(c)">Eliminar</button>
                                </td>
                            </tr>
                            <tr v-if="!seccion.cheques.data.length">
                                <td colspan="14" class="px-6 py-2 text-center text-xs text-gray-500">Sin cheques.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="seccion.cheques.total > seccion.cheques.per_page" class="px-4 py-2 border-t border-gray-200 flex items-center justify-between text-xs">
                    <div>Pág. {{ seccion.cheques.current_page }} de {{ seccion.cheques.last_page }} ({{ seccion.cheques.total }})</div>
                    <div class="flex gap-2">
                        <SecondaryButton v-if="seccion.cheques.prev_page_url" class="!text-[10px] !px-2 !py-0.5" @click="router.get(seccion.cheques.prev_page_url, {}, { preserveState: true, preserveScroll: true })">Anterior</SecondaryButton>
                        <SecondaryButton v-if="seccion.cheques.next_page_url" class="!text-[10px] !px-2 !py-0.5" @click="router.get(seccion.cheques.next_page_url, {}, { preserveState: true, preserveScroll: true })">Siguiente</SecondaryButton>
                    </div>
                </div>
            </div>

            <div v-if="editId" ref="editFormRef" class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-base font-semibold text-gray-900">Editar cheque #{{ editId }}</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submitEdit">
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Estado</div>
                        <select v-model="editForm.estado" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option v-for="e in (editForm.origen === 'propio' ? estadosPropio : estadosTercero)" :key="e" :value="e">{{ estadoLabel(e) }}</option>
                        </select>
                        <InputError :message="editForm.errors.estado" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Tipo</div>
                        <select v-model="editForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="fisico">Físico</option>
                            <option value="echeq">E-Cheq</option>
                        </select>
                        <InputError :message="editForm.errors.tipo" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Nro cheque</div>
                        <TextInput v-model="editForm.numero" type="text" class="block w-full" />
                        <InputError :message="editForm.errors.numero" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Banco{{ editForm.origen === 'propio' ? ' (cuenta propia)' : '' }}</div>
                        <select v-model="editForm.banco" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">(seleccionar)</option>
                            <option v-for="b in (editForm.origen === 'propio' ? bancosPropios : bancos)" :key="b.id" :value="b.nombre">{{ b.nombre }}</option>
                        </select>
                        <InputError :message="editForm.errors.banco" />
                    </div>
                    <div v-if="editForm.estado === 'depositado' || editForm.estado === 'cobrado'">
                        <div class="text-xs font-medium text-gray-700 mb-1">Fecha depósito</div>
                        <TextInput v-model="editForm.fecha_deposito" type="date" class="block w-full" />
                        <InputError :message="editForm.errors.fecha_deposito" />
                    </div>
                    <div v-if="editForm.estado === 'depositado' || editForm.estado === 'cobrado'">
                        <div class="text-xs font-medium text-gray-700 mb-1">Banco donde se deposita (cuenta propia)</div>
                        <select v-model="editForm.banco_deposito_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">(seleccionar banco)</option>
                            <option v-for="b in bancosPropios" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                        </select>
                        <InputError :message="editForm.errors.banco_deposito_id" />
                        <div v-if="editForm.estado === 'depositado'" class="text-xs text-blue-600 mt-1">Se generará movimiento bancario pendiente</div>
                        <div v-if="editForm.estado === 'cobrado'" class="text-xs text-green-600 mt-1">Se marcará como acreditado</div>
                    </div>
                    <div v-if="editForm.estado === 'cobrado'">
                        <div class="text-xs font-medium text-gray-700 mb-1">Fecha cobro (acreditación)</div>
                        <TextInput v-model="editForm.fecha_cobro" type="date" class="block w-full" />
                        <InputError :message="editForm.errors.fecha_cobro" />
                    </div>
                    <div v-if="editForm.estado === 'rechazado'">
                        <div class="text-xs font-medium text-gray-700 mb-1">Fecha rechazo</div>
                        <TextInput v-model="editForm.fecha_rechazo" type="date" class="block w-full" />
                        <InputError :message="editForm.errors.fecha_rechazo" />
                    </div>
                    <div v-if="editForm.estado === 'endosado'">
                        <div class="text-xs font-medium text-gray-700 mb-1">Endosado a</div>
                        <TextInput v-model="editForm.endosado_a" type="text" class="block w-full" placeholder="Proveedor / persona" />
                        <InputError :message="editForm.errors.endosado_a" />
                    </div>
                    <div class="sm:col-span-4">
                        <div class="text-xs font-medium text-gray-700 mb-1">Observación</div>
                        <textarea v-model="editForm.observacion" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" rows="2"></textarea>
                        <InputError :message="editForm.errors.observacion" />
                    </div>
                    <div class="sm:col-span-4 flex justify-end gap-2">
                        <SecondaryButton @click="editId = null">Cancelar</SecondaryButton>
                        <PrimaryButton :disabled="editForm.processing">Guardar</PrimaryButton>
                    </div>
                </form>
            </div>

            <DialogModal :show="!!deleteId" @close="deleteId = null">
                <template #title>Eliminar cheque</template>
                <template #content>
                    <p class="text-sm text-gray-700">¿Eliminar el cheque <span class="font-semibold">{{ deleteInfo }}</span>? Esta acción no se puede deshacer.</p>
                    <p class="mt-2 text-xs text-gray-500">No se puede eliminar si está usado en recibos, egresos, órdenes de pago o movimientos contabilizados.</p>
                </template>
                <template #footer>
                    <SecondaryButton class="!text-xs !px-3 !py-1.5" @click="deleteId = null">Cancelar</SecondaryButton>
                    <button type="button" class="ms-3 inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2" :disabled="deleteProcessing" @click="submitDelete">Eliminar</button>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>


