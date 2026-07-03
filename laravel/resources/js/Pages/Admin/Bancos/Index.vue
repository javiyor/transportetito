<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DialogModal from '@/Components/DialogModal.vue';
import { ref } from 'vue';

const props = defineProps({
    bancos: Array,
});

const form = useForm({ nombre: '', codigo: '', activo: true });

const submit = () => {
    form.post(route('admin.bancos.store'), { preserveScroll: true, onSuccess: () => form.reset() });
};

const editing = ref(false);
const editForm = useForm({ nombre: '', codigo: '', activo: true });
const editId = ref(null);

const openEdit = (b) => {
    editId.value = b.id;
    editForm.nombre = b.nombre;
    editForm.codigo = b.codigo || '';
    editForm.activo = !!b.activo;
    editForm.clearErrors();
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.bancos.update', editId.value), { preserveScroll: true, onSuccess: () => (editing.value = false) });
};
</script>

<template>
    <AppLayout title="Admin / Bancos">
        <Head title="Admin / Bancos" />

        <template #header>
            <h2 class="font-semibold text-lg text-gray-800 leading-tight">Admin / Bancos</h2>
        </template>

        <div class="max-w-4xl mx-auto py-6 sm:px-4 lg:px-6 space-y-4">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900">Nuevo banco</h3>
                <form class="mt-3 grid grid-cols-1 sm:grid-cols-4 gap-3" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Nombre" />
                        <TextInput v-model="form.nombre" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.nombre" />
                    </div>
                    <div>
                        <InputLabel value="Codigo" />
                        <TextInput v-model="form.codigo" type="text" class="mt-1 block w-full" maxlength="8" />
                        <InputError class="mt-1" :message="form.errors.codigo" />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.activo" type="checkbox" class="rounded border-gray-300" />
                            Activo
                        </label>
                    </div>
                    <div class="flex items-end">
                        <PrimaryButton :disabled="form.processing">Crear</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Codigo</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Accion</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="b in bancos" :key="b.id">
                            <td class="px-3 py-2 text-xs text-gray-900">{{ b.nombre }}</td>
                            <td class="px-3 py-2 text-xs text-gray-700">{{ b.codigo || '-' }}</td>
                            <td class="px-3 py-2 text-xs">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="b.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">{{ b.activo ? 'Si' : 'No' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right text-xs">
                                <SecondaryButton class="text-xs" @click="openEdit(b)">Editar</SecondaryButton>
                            </td>
                        </tr>
                        <tr v-if="!bancos.length">
                            <td colspan="4" class="px-3 py-6 text-center text-xs text-gray-500">Sin bancos.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <DialogModal :show="editing" @close="editing = false">
                <template #title>Editar banco</template>
                <template #content>
                    <form class="grid grid-cols-1 gap-3" @submit.prevent="submitEdit">
                        <div>
                            <InputLabel value="Nombre" />
                            <TextInput v-model="editForm.nombre" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="editForm.errors.nombre" />
                        </div>
                        <div>
                            <InputLabel value="Codigo" />
                            <TextInput v-model="editForm.codigo" type="text" class="mt-1 block w-full" maxlength="8" />
                            <InputError class="mt-1" :message="editForm.errors.codigo" />
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="editForm.activo" type="checkbox" class="rounded border-gray-300" />
                            Activo
                        </label>
                    </form>
                </template>
                <template #footer>
                    <SecondaryButton @click="editing = false">Cancelar</SecondaryButton>
                    <PrimaryButton class="ms-3" :disabled="editForm.processing" @click="submitEdit">Guardar</PrimaryButton>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>
