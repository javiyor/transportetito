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
    depositos: Array,
});

const createForm = useForm({
    empresa_id: props.empresaId || props.empresas?.[0]?.id || null,
    nombre: '',
    direccion: '',
    punto_venta_numero: 2,
    es_central: false,
});

const submitCreate = () => {
    createForm.post(route('admin.depositos.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('nombre', 'direccion'),
    });
};

const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    empresa_id: null,
    nombre: '',
    direccion: '',
    punto_venta_numero: 2,
    es_central: false,
});

const openEdit = (d) => {
    editId.value = d.id;
    editForm.empresa_id = d.empresa_id;
    editForm.nombre = d.nombre;
    editForm.direccion = d.direccion || '';
    editForm.punto_venta_numero = d.punto_venta_numero;
    editForm.es_central = !!d.es_central;
    editForm.clearErrors();
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.depositos.update', editId.value), { preserveScroll: true, onSuccess: () => (editing.value = false) });
};

const changeEmpresa = (id) => {
    router.get(route('admin.depositos.index'), { empresa_id: id || null }, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <AppLayout title="Admin / Depositos">
        <Head title="Admin / Depositos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Depositos</h2>
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
                <h3 class="text-base font-semibold text-gray-900">Nuevo deposito</h3>

                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="Empresa" />
                        <select v-model="createForm.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.empresa_id" />
                    </div>
                    <div>
                        <InputLabel value="Nombre" />
                        <TextInput v-model="createForm.nombre" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.nombre" />
                    </div>
                    <div>
                        <InputLabel value="Direccion" />
                        <TextInput v-model="createForm.direccion" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.direccion" />
                    </div>
                    <div>
                        <InputLabel value="PV" />
                        <TextInput v-model="createForm.punto_venta_numero" type="number" min="1" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.punto_venta_numero" />
                    </div>

                    <div class="sm:col-span-4 flex items-center gap-2">
                        <Checkbox v-model:checked="createForm.es_central" />
                        <span class="text-sm text-gray-700">Es deposito central</span>
                        <InputError class="mt-2" :message="createForm.errors.es_central" />
                    </div>
                    <div class="sm:col-span-4 flex justify-end">
                        <PrimaryButton :disabled="createForm.processing">Crear</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Depositos</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Direccion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PV</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Central</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="d in depositos" :key="d.id">
                                <td class="px-6 py-4 text-sm text-gray-700">{{ d.empresa?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ d.nombre }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ d.direccion || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ d.punto_venta_numero }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ d.es_central ? 'Si' : 'No' }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <SecondaryButton class="text-xs" @click.prevent="openEdit(d)">Editar</SecondaryButton>
                                </td>
                            </tr>
                            <tr v-if="!depositos.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Sin depositos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <DialogModal :show="editing" @close="editing = false">
            <template #title>Editar deposito</template>
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
                        <InputLabel value="Nombre" />
                        <TextInput v-model="editForm.nombre" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.nombre" />
                    </div>
                    <div>
                        <InputLabel value="Direccion" />
                        <TextInput v-model="editForm.direccion" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.direccion" />
                    </div>
                    <div>
                        <InputLabel value="PV" />
                        <TextInput v-model="editForm.punto_venta_numero" type="number" min="1" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.punto_venta_numero" />
                    </div>

                    <div class="sm:col-span-2 flex items-center gap-2">
                        <Checkbox v-model:checked="editForm.es_central" />
                        <span class="text-sm text-gray-700">Es deposito central</span>
                        <InputError class="mt-2" :message="editForm.errors.es_central" />
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
