<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref } from 'vue';

const props = defineProps({
    empresas: Array,
});

const createForm = useForm({
    razon_social: '',
    cuit: '',
    condicion_iva: '',
    arca_pv_default: 2,
    arca_env: 'homologacion',
});

const submitCreate = () => {
    createForm.post(route('admin.empresas.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('razon_social', 'cuit', 'condicion_iva'),
    });
};

const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    razon_social: '',
    cuit: '',
    condicion_iva: '',
    arca_pv_default: 2,
    arca_env: 'homologacion',
});

const openEdit = (e) => {
    editId.value = e.id;
    editForm.razon_social = e.razon_social;
    editForm.cuit = e.cuit;
    editForm.condicion_iva = e.condicion_iva || '';
    editForm.arca_pv_default = e.arca_pv_default;
    editForm.arca_env = e.arca_env;
    editForm.clearErrors();
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.empresas.update', editId.value), { preserveScroll: true, onSuccess: () => (editing.value = false) });
};
</script>

<template>
    <AppLayout title="Admin / Empresas">
        <Head title="Admin / Empresas" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Empresas</h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nueva empresa</h3>

                <form class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4" @submit.prevent="submitCreate">
                    <div class="sm:col-span-2">
                        <InputLabel value="Razon social" />
                        <TextInput v-model="createForm.razon_social" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.razon_social" />
                    </div>
                    <div>
                        <InputLabel value="CUIT" />
                        <TextInput v-model="createForm.cuit" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.cuit" />
                    </div>

                    <div>
                        <InputLabel value="Condicion IVA" />
                        <TextInput v-model="createForm.condicion_iva" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.condicion_iva" />
                    </div>
                    <div>
                        <InputLabel value="PV default" />
                        <TextInput v-model="createForm.arca_pv_default" type="number" min="1" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.arca_pv_default" />
                    </div>
                    <div>
                        <InputLabel value="Entorno ARCA" />
                        <select v-model="createForm.arca_env" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="homologacion">Homologacion</option>
                            <option value="produccion">Produccion</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.arca_env" />
                    </div>

                    <div class="sm:col-span-3 flex justify-end">
                        <PrimaryButton :disabled="createForm.processing">Crear</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Empresas</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Razon social</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CUIT</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PV</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ARCA</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="e in empresas" :key="e.id">
                                <td class="px-6 py-4 text-sm text-gray-900">{{ e.razon_social }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ e.cuit }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ e.arca_pv_default }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ e.arca_env }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <SecondaryButton class="text-xs" @click.prevent="openEdit(e)">Editar</SecondaryButton>
                                </td>
                            </tr>
                            <tr v-if="!empresas.length">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Sin empresas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <DialogModal :show="editing" @close="editing = false">
            <template #title>Editar empresa</template>
            <template #content>
                <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="submitEdit">
                    <div class="sm:col-span-2">
                        <InputLabel value="Razon social" />
                        <TextInput v-model="editForm.razon_social" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.razon_social" />
                    </div>
                    <div>
                        <InputLabel value="CUIT" />
                        <TextInput v-model="editForm.cuit" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.cuit" />
                    </div>
                    <div>
                        <InputLabel value="Condicion IVA" />
                        <TextInput v-model="editForm.condicion_iva" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.condicion_iva" />
                    </div>
                    <div>
                        <InputLabel value="PV default" />
                        <TextInput v-model="editForm.arca_pv_default" type="number" min="1" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.arca_pv_default" />
                    </div>
                    <div>
                        <InputLabel value="Entorno ARCA" />
                        <select v-model="editForm.arca_env" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="homologacion">Homologacion</option>
                            <option value="produccion">Produccion</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.arca_env" />
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
