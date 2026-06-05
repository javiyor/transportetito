<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { ref } from 'vue';

const props = defineProps({
    empresas: Array,
    empresaId: [Number, null],
    vehiculos: Array,
});

const createForm = useForm({
    empresa_id: props.empresaId || props.empresas?.[0]?.id || null,
    patente: '',
    marca: '',
    modelo: '',
    activo: true,
});

const submitCreate = () => {
    createForm.post(route('admin.vehiculos.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('patente', 'marca', 'modelo'),
    });
};

const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    empresa_id: null,
    patente: '',
    marca: '',
    modelo: '',
    activo: true,
});

const openEdit = (v) => {
    editId.value = v.id;
    editForm.empresa_id = v.empresa_id;
    editForm.patente = v.patente;
    editForm.marca = v.marca || '';
    editForm.modelo = v.modelo || '';
    editForm.activo = !!v.activo;
    editForm.clearErrors();
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.vehiculos.update', editId.value), { preserveScroll: true, onSuccess: () => (editing.value = false) });
};

const changeEmpresa = (id) => {
    router.get(route('admin.vehiculos.index'), { empresa_id: id || null }, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <AppLayout title="Admin / Vehiculos">
        <Head title="Admin / Vehiculos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Vehiculos</h2>
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
                <h3 class="text-base font-semibold text-gray-900">Nuevo vehiculo</h3>

                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="Empresa" />
                        <select v-model="createForm.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.empresa_id" />
                    </div>
                    <div>
                        <InputLabel value="Patente" />
                        <TextInput v-model="createForm.patente" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.patente" />
                    </div>
                    <div>
                        <InputLabel value="Marca" />
                        <TextInput v-model="createForm.marca" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.marca" />
                    </div>
                    <div>
                        <InputLabel value="Modelo" />
                        <TextInput v-model="createForm.modelo" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.modelo" />
                    </div>

                    <div class="sm:col-span-4 flex items-center gap-2">
                        <Checkbox v-model:checked="createForm.activo" />
                        <span class="text-sm text-gray-700">Activo</span>
                    </div>
                    <div class="sm:col-span-4 flex justify-end">
                        <PrimaryButton :disabled="createForm.processing">Crear</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Vehiculos</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marca</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modelo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="v in vehiculos" :key="v.id">
                                <td class="px-6 py-4 text-sm text-gray-700">{{ v.empresa?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ v.patente }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ v.marca || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ v.modelo || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ v.activo ? 'Si' : 'No' }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <SecondaryButton class="text-xs" @click.prevent="openEdit(v)">Editar</SecondaryButton>
                                </td>
                            </tr>
                            <tr v-if="!vehiculos.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Sin vehiculos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <DialogModal :show="editing" @close="editing = false">
            <template #title>Editar vehiculo</template>
            <template #content>
                <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="submitEdit">
                    <div class="sm:col-span-2">
                        <InputLabel value="Empresa" />
                        <select v-model="editForm.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.empresa_id" />
                    </div>
                    <div>
                        <InputLabel value="Patente" />
                        <TextInput v-model="editForm.patente" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.patente" />
                    </div>
                    <div>
                        <InputLabel value="Marca" />
                        <TextInput v-model="editForm.marca" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.marca" />
                    </div>
                    <div>
                        <InputLabel value="Modelo" />
                        <TextInput v-model="editForm.modelo" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.modelo" />
                    </div>

                    <div class="sm:col-span-2 flex items-center gap-2">
                        <Checkbox v-model:checked="editForm.activo" />
                        <span class="text-sm text-gray-700">Activo</span>
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
