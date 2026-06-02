<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    empresas: Array,
    empresaId: [Number, null],
    terceros: Array,
    tarifas: Array,
});

const changeEmpresa = (id) => {
    router.get(route('admin.tarifas.index'), { empresa_id: id || null }, { preserveState: true, preserveScroll: true, replace: true });
};

const createForm = useForm({
    empresa_id: props.empresaId || props.empresas?.[0]?.id || null,
    remitente_tercero_id: null,
    destinatario_tercero_id: null,
    moneda: 'ARS',
    tarifa_bulto: 10000,
    tarifa_palet: 100000,
    tarifa_valor_declarado_pct: 0.03,
    flete_minimo: 20000,
    seguro_pct: 0.007,
    seguro_minimo: '',
    seguro_tope: '',
    cr_comision_pct: 0.025,
    cr_comision_minimo: '',
    cr_comision_tope: '',
    iva_pct: 0.21,
    activo: true,
});

const submitCreate = () => {
    createForm
        .transform((data) => ({
            ...data,
            seguro_minimo: data.seguro_minimo === '' ? null : data.seguro_minimo,
            seguro_tope: data.seguro_tope === '' ? null : data.seguro_tope,
            cr_comision_minimo: data.cr_comision_minimo === '' ? null : data.cr_comision_minimo,
            cr_comision_tope: data.cr_comision_tope === '' ? null : data.cr_comision_tope,
        }))
        .post(route('admin.tarifas.store'), { preserveScroll: true });
};

const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    moneda: 'ARS',
    tarifa_bulto: 10000,
    tarifa_palet: 100000,
    tarifa_valor_declarado_pct: 0.03,
    flete_minimo: 20000,
    seguro_pct: 0.007,
    seguro_minimo: '',
    seguro_tope: '',
    cr_comision_pct: 0.025,
    cr_comision_minimo: '',
    cr_comision_tope: '',
    iva_pct: 0.21,
    activo: true,
});

const openEdit = (t) => {
    editId.value = t.id;
    editForm.moneda = t.moneda || 'ARS';
    editForm.tarifa_bulto = Number(t.tarifa_bulto || 0);
    editForm.tarifa_palet = Number(t.tarifa_palet || 0);
    editForm.tarifa_valor_declarado_pct = Number(t.tarifa_valor_declarado_pct || 0);
    editForm.flete_minimo = Number(t.flete_minimo || 0);
    editForm.seguro_pct = Number(t.seguro_pct || 0);
    editForm.seguro_minimo = t.seguro_minimo === null ? '' : Number(t.seguro_minimo || 0);
    editForm.seguro_tope = t.seguro_tope === null ? '' : Number(t.seguro_tope || 0);
    editForm.cr_comision_pct = Number(t.cr_comision_pct || 0);
    editForm.cr_comision_minimo = t.cr_comision_minimo === null ? '' : Number(t.cr_comision_minimo || 0);
    editForm.cr_comision_tope = t.cr_comision_tope === null ? '' : Number(t.cr_comision_tope || 0);
    editForm.iva_pct = Number(t.iva_pct || 0);
    editForm.activo = !!t.activo;
    editForm.clearErrors();
    editing.value = true;
};

const submitEdit = () => {
    editForm
        .transform((data) => ({
            ...data,
            seguro_minimo: data.seguro_minimo === '' ? null : data.seguro_minimo,
            seguro_tope: data.seguro_tope === '' ? null : data.seguro_tope,
            cr_comision_minimo: data.cr_comision_minimo === '' ? null : data.cr_comision_minimo,
            cr_comision_tope: data.cr_comision_tope === '' ? null : data.cr_comision_tope,
        }))
        .put(route('admin.tarifas.update', editId.value), { preserveScroll: true, onSuccess: () => (editing.value = false) });
};

const terceroLabel = (t) => `${t.razon_social} · CUIT ${t.cuit}`;
</script>

<template>
    <AppLayout title="Admin / Tarifas">
        <Head title="Admin / Tarifas" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Tarifas</h2>
                <div class="w-72">
                    <select
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        :value="empresaId || ''"
                        @change="changeEmpresa($event.target.value ? parseInt($event.target.value, 10) : null)"
                    >
                        <option value="">Todas las empresas</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nueva tarifa (relacion)</h3>

                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="Empresa" />
                        <select v-model="createForm.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.empresa_id" />
                    </div>
                    <div>
                        <InputLabel value="Remitente" />
                        <select v-model="createForm.remitente_tercero_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="" disabled>(seleccionar)</option>
                            <option v-for="t in terceros" :key="t.id" :value="t.id">{{ terceroLabel(t) }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.remitente_tercero_id" />
                    </div>
                    <div>
                        <InputLabel value="Destinatario" />
                        <select v-model="createForm.destinatario_tercero_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="" disabled>(seleccionar)</option>
                            <option v-for="t in terceros" :key="t.id" :value="t.id">{{ terceroLabel(t) }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.destinatario_tercero_id" />
                    </div>
                    <div>
                        <InputLabel value="Moneda" />
                        <TextInput v-model="createForm.moneda" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.moneda" />
                    </div>

                    <div>
                        <InputLabel value="Tarifa bulto" />
                        <TextInput v-model="createForm.tarifa_bulto" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.tarifa_bulto" />
                    </div>
                    <div>
                        <InputLabel value="Tarifa palet" />
                        <TextInput v-model="createForm.tarifa_palet" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.tarifa_palet" />
                    </div>
                    <div>
                        <InputLabel value="% valor declarado" />
                        <TextInput v-model="createForm.tarifa_valor_declarado_pct" type="number" min="0" step="0.0001" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.tarifa_valor_declarado_pct" />
                    </div>
                    <div>
                        <InputLabel value="Flete minimo" />
                        <TextInput v-model="createForm.flete_minimo" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.flete_minimo" />
                    </div>

                    <div>
                        <InputLabel value="% seguro" />
                        <TextInput v-model="createForm.seguro_pct" type="number" min="0" step="0.0001" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.seguro_pct" />
                    </div>
                    <div>
                        <InputLabel value="Seguro minimo" />
                        <TextInput v-model="createForm.seguro_minimo" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                        <InputError class="mt-2" :message="createForm.errors.seguro_minimo" />
                    </div>
                    <div>
                        <InputLabel value="Seguro tope" />
                        <TextInput v-model="createForm.seguro_tope" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                        <InputError class="mt-2" :message="createForm.errors.seguro_tope" />
                    </div>
                    <div>
                        <InputLabel value="% CR" />
                        <TextInput v-model="createForm.cr_comision_pct" type="number" min="0" step="0.0001" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.cr_comision_pct" />
                    </div>

                    <div>
                        <InputLabel value="CR minimo" />
                        <TextInput v-model="createForm.cr_comision_minimo" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                        <InputError class="mt-2" :message="createForm.errors.cr_comision_minimo" />
                    </div>
                    <div>
                        <InputLabel value="CR tope" />
                        <TextInput v-model="createForm.cr_comision_tope" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                        <InputError class="mt-2" :message="createForm.errors.cr_comision_tope" />
                    </div>
                    <div>
                        <InputLabel value="% IVA" />
                        <TextInput v-model="createForm.iva_pct" type="number" min="0" step="0.0001" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.iva_pct" />
                    </div>

                    <div class="sm:col-span-4 flex items-center gap-2">
                        <Checkbox v-model:checked="createForm.activo" />
                        <span class="text-sm text-gray-700">Activa</span>
                        <InputError class="mt-2" :message="createForm.errors.activo" />
                    </div>

                    <div class="sm:col-span-4 flex justify-end">
                        <PrimaryButton :disabled="createForm.processing">Guardar</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Tarifas</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Relacion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarifas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activa</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="t in tarifas" :key="t.id">
                                <td class="px-6 py-4 text-sm text-gray-700">{{ t.empresa?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="font-medium">{{ t.remitente?.razon_social || '-' }} → {{ t.destinatario?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">CUIT {{ t.remitente?.cuit || '-' }} → {{ t.destinatario?.cuit || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ t.moneda }}</td>
                                <td class="px-6 py-4 text-xs text-gray-700">
                                    bulto {{ t.tarifa_bulto }} · palet {{ t.tarifa_palet }} · %VD {{ t.tarifa_valor_declarado_pct }} · min {{ t.flete_minimo }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ t.activo ? 'Si' : 'No' }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <SecondaryButton class="text-xs" @click.prevent="openEdit(t)">Editar</SecondaryButton>
                                </td>
                            </tr>
                            <tr v-if="!tarifas.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Sin tarifas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <DialogModal :show="editing" @close="editing = false">
            <template #title>Editar tarifa</template>
            <template #content>
                <form class="grid grid-cols-1 sm:grid-cols-3 gap-4" @submit.prevent="submitEdit">
                    <div class="sm:col-span-3">
                        <InputLabel value="Moneda" />
                        <TextInput v-model="editForm.moneda" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.moneda" />
                    </div>

                    <div>
                        <InputLabel value="Tarifa bulto" />
                        <TextInput v-model="editForm.tarifa_bulto" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.tarifa_bulto" />
                    </div>
                    <div>
                        <InputLabel value="Tarifa palet" />
                        <TextInput v-model="editForm.tarifa_palet" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.tarifa_palet" />
                    </div>
                    <div>
                        <InputLabel value="% valor declarado" />
                        <TextInput v-model="editForm.tarifa_valor_declarado_pct" type="number" min="0" step="0.0001" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.tarifa_valor_declarado_pct" />
                    </div>

                    <div>
                        <InputLabel value="Flete minimo" />
                        <TextInput v-model="editForm.flete_minimo" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.flete_minimo" />
                    </div>
                    <div>
                        <InputLabel value="% seguro" />
                        <TextInput v-model="editForm.seguro_pct" type="number" min="0" step="0.0001" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.seguro_pct" />
                    </div>
                    <div>
                        <InputLabel value="% CR" />
                        <TextInput v-model="editForm.cr_comision_pct" type="number" min="0" step="0.0001" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.cr_comision_pct" />
                    </div>

                    <div>
                        <InputLabel value="Seguro minimo" />
                        <TextInput v-model="editForm.seguro_minimo" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                        <InputError class="mt-2" :message="editForm.errors.seguro_minimo" />
                    </div>
                    <div>
                        <InputLabel value="Seguro tope" />
                        <TextInput v-model="editForm.seguro_tope" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                        <InputError class="mt-2" :message="editForm.errors.seguro_tope" />
                    </div>
                    <div>
                        <InputLabel value="% IVA" />
                        <TextInput v-model="editForm.iva_pct" type="number" min="0" step="0.0001" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.iva_pct" />
                    </div>

                    <div>
                        <InputLabel value="CR minimo" />
                        <TextInput v-model="editForm.cr_comision_minimo" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                        <InputError class="mt-2" :message="editForm.errors.cr_comision_minimo" />
                    </div>
                    <div>
                        <InputLabel value="CR tope" />
                        <TextInput v-model="editForm.cr_comision_tope" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                        <InputError class="mt-2" :message="editForm.errors.cr_comision_tope" />
                    </div>
                    <div class="flex items-end gap-2">
                        <Checkbox v-model:checked="editForm.activo" />
                        <span class="text-sm text-gray-700">Activa</span>
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="editing = false">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="editForm.processing" @click="submitEdit">Guardar</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
