<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';

const props = defineProps({
    cheques: Object,
    empresas: Array,
    empresaId: [Number, null],
    filtros: Object,
});

const editId = ref(null);
const editForm = useForm({
    estado: '',
    tipo: '',
    numero: '',
    banco: '',
    fecha_deposito: '',
    fecha_cobro: '',
    fecha_rechazo: '',
    endosado_a: '',
    observacion: '',
});

const openEdit = (c) => {
    editId.value = c.id;
    editForm.estado = c.estado;
    editForm.tipo = c.tipo;
    editForm.numero = c.numero || '';
    editForm.banco = c.banco || '';
    editForm.fecha_deposito = c.fecha_deposito ? String(c.fecha_deposito).slice(0, 10) : '';
    editForm.fecha_cobro = c.fecha_cobro ? String(c.fecha_cobro).slice(0, 10) : '';
    editForm.fecha_rechazo = c.fecha_rechazo ? String(c.fecha_rechazo).slice(0, 10) : '';
    editForm.endosado_a = c.endosado_a || '';
    editForm.observacion = c.observacion || '';
    editForm.clearErrors();
};

const submitEdit = () => {
    editForm.put(route('admin.cheques.update', editId.value), {
        preserveScroll: true,
        onSuccess: () => { editId.value = null; },
    });
};

const filterForm = useForm({
    estado: props.filtros.estado,
    tipo: props.filtros.tipo,
    origen: props.filtros.origen,
    desde: props.filtros.desde,
    hasta: props.filtros.hasta,
});

const applyFilters = () => {
    router.get(route('admin.cheques.index'), {
        ...(filterForm.estado && { estado: filterForm.estado }),
        ...(filterForm.tipo && { tipo: filterForm.tipo }),
        ...(filterForm.origen && { origen: filterForm.origen }),
        ...(filterForm.desde && { desde: filterForm.desde }),
        ...(filterForm.hasta && { hasta: filterForm.hasta }),
        ...(props.empresaId && { empresa_id: props.empresaId }),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const estadoBadgeClass = (estado) => {
    const map = {
        en_cartera: 'bg-yellow-100 text-yellow-800',
        depositado: 'bg-blue-100 text-blue-800',
        cobrado: 'bg-green-100 text-green-800',
        rechazado: 'bg-red-100 text-red-800',
        endosado: 'bg-purple-100 text-purple-800',
        anulado: 'bg-gray-100 text-gray-800',
    };
    return map[estado] || 'bg-gray-100 text-gray-800';
};

const estadoLabel = (e) => {
    const map = { en_cartera: 'En cartera', depositado: 'Depositado', cobrado: 'Cobrado', rechazado: 'Rechazado', endosado: 'Endosado', anulado: 'Anulado' };
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

const formatFecha = (v) => v ? String(v).slice(0, 10) : '-';
</script>

<template>
    <AppLayout title="Admin / Cheques">
        <Head title="Admin / Cheques" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Cheques</h2>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-4 items-end">
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Estado</div>
                        <select v-model="filterForm.estado" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todos</option>
                            <option value="en_cartera">En cartera</option>
                            <option value="depositado">Depositado</option>
                            <option value="cobrado">Cobrado</option>
                            <option value="rechazado">Rechazado</option>
                            <option value="endosado">Endosado</option>
                            <option value="anulado">Anulado</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Tipo</div>
                        <select v-model="filterForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todos</option>
                            <option value="fisico">Físico</option>
                            <option value="echeq">E-Cheq</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Origen</div>
                        <select v-model="filterForm.origen" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todos</option>
                            <option value="propio">Propio</option>
                            <option value="tercero">Tercero</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Desde</div>
                        <TextInput v-model="filterForm.desde" type="date" class="block w-full" />
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Hasta</div>
                        <TextInput v-model="filterForm.hasta" type="date" class="block w-full" />
                    </div>
                    <div>
                        <PrimaryButton @click="applyFilters">Filtrar</PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origen</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nro</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Banco</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Importe</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Venc.</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Librado por</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Endosado a</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Depósito</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cobro</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in cheques.data" :key="c.id">
                                <td class="px-4 py-3 text-sm font-mono text-gray-900">#{{ c.id }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ tipoLabel(c.tipo) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ origenLabel(c.origen) }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ c.numero || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ c.banco || '-' }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ c.moneda }} {{ c.importe }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatFecha(c.fecha_vencimiento) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ c.librado_por || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ c.endosado_a || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatFecha(c.fecha_deposito) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatFecha(c.fecha_cobro) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="estadoBadgeClass(c.estado)">{{ estadoLabel(c.estado) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <SecondaryButton class="text-xs" @click="openEdit(c)">Editar</SecondaryButton>
                                </td>
                            </tr>
                            <tr v-if="!cheques.data.length">
                                <td colspan="13" class="px-6 py-10 text-center text-sm text-gray-500">Sin cheques.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="cheques.total > cheques.per_page" class="p-4 border-t border-gray-200 flex items-center justify-between text-sm">
                    <div>Página {{ cheques.current_page }} de {{ cheques.last_page }} ({{ cheques.total }} cheques)</div>
                    <div class="flex gap-2">
                        <SecondaryButton v-if="cheques.prev_page_url" @click="router.get(cheques.prev_page_url, {}, { preserveState: true, preserveScroll: true })">Anterior</SecondaryButton>
                        <SecondaryButton v-if="cheques.next_page_url" @click="router.get(cheques.next_page_url, {}, { preserveState: true, preserveScroll: true })">Siguiente</SecondaryButton>
                    </div>
                </div>
            </div>

            <div v-if="editId" class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Editar cheque #{{ editId }}</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submitEdit">
                    <div>
                        <div class="text-xs font-medium text-gray-700 mb-1">Estado</div>
                        <select v-model="editForm.estado" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option v-for="e in ['en_cartera','depositado','cobrado','rechazado','endosado','anulado']" :key="e" :value="e">{{ estadoLabel(e) }}</option>
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
                        <div class="text-xs font-medium text-gray-700 mb-1">Banco</div>
                        <TextInput v-model="editForm.banco" type="text" class="block w-full" />
                        <InputError :message="editForm.errors.banco" />
                    </div>
                    <div v-if="editForm.estado === 'depositado' || editForm.estado === 'cobrado'">
                        <div class="text-xs font-medium text-gray-700 mb-1">Fecha depósito</div>
                        <TextInput v-model="editForm.fecha_deposito" type="date" class="block w-full" />
                        <InputError :message="editForm.errors.fecha_deposito" />
                    </div>
                    <div v-if="editForm.estado === 'cobrado'">
                        <div class="text-xs font-medium text-gray-700 mb-1">Fecha cobro</div>
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
        </div>
    </AppLayout>
</template>
