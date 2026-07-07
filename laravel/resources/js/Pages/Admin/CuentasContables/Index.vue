<script setup>
import { Head, useForm } from '@inertiajs/vue3';
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
    cuentas: Array,
});

const createForm = useForm({
    codigo: '',
    nombre: '',
    tipo: '',
    moneda: '',
    activo: true,
});

const submitCreate = () => {
    createForm.post(route('admin.cuentas-contables.index'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('codigo', 'nombre', 'tipo', 'moneda'),
    });
};

const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    codigo: '',
    nombre: '',
    tipo: '',
    moneda: '',
    activo: true,
});

const openEdit = (e) => {
    editId.value = e.id;
    editForm.codigo = e.codigo;
    editForm.nombre = e.nombre;
    editForm.tipo = e.tipo;
    editForm.moneda = e.moneda || '';
    editForm.activo = !!e.activo;
    editForm.clearErrors();
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.cuentas-contables.update', editId.value), { preserveScroll: true, onSuccess: () => (editing.value = false) });
};
</script>

<template>
    <AppLayout title="Admin / Categorias">
        <Head title="Admin / Categorias" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Categorias de ing/egr</h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nueva categoria</h3>

                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="Codigo" />
                        <TextInput v-model="createForm.codigo" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.codigo" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Nombre" />
                        <TextInput v-model="createForm.nombre" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.nombre" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="createForm.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">Seleccionar...</option>
                            <option value="ingreso">Ingreso</option>
                            <option value="egreso">Egreso</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Moneda" />
                        <select v-model="createForm.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todas</option>
                            <option value="ARS">ARS</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="BRL">BRL</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.moneda" />
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="createForm.activo" />
                            Activa
                        </label>
                    </div>
                    <div class="sm:col-span-3 flex justify-end">
                        <PrimaryButton :disabled="createForm.processing">Crear</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Categorias</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codigo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activa</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in cuentas" :key="c.id">
                                <td class="px-6 py-4 text-sm text-gray-900">{{ c.codigo }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.nombre }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.tipo }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda || 'Todas' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.activo ? 'Si' : 'No' }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <SecondaryButton class="text-xs" @click.prevent="openEdit(c)">Editar</SecondaryButton>
                                </td>
                            </tr>
                            <tr v-if="!cuentas.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Sin categorias.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <DialogModal :show="editing" @close="editing = false">
            <template #title>Editar categoria</template>
            <template #content>
                <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="submitEdit">
                    <div>
                        <InputLabel value="Codigo" />
                        <TextInput v-model="editForm.codigo" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.codigo" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Nombre" />
                        <TextInput v-model="editForm.nombre" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editForm.errors.nombre" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="editForm.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">Seleccionar...</option>
                            <option value="ingreso">Ingreso</option>
                            <option value="egreso">Egreso</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.tipo" />
                    </div>
                    <div>
                        <InputLabel value="Moneda" />
                        <select v-model="editForm.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Todas</option>
                            <option value="ARS">ARS</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="BRL">BRL</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.moneda" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="editForm.activo" />
                            Activa
                        </label>
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
