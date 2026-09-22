<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DialogModal from '@/Components/DialogModal.vue';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    bancos: Array,
    orden: String,
});

const ordenar = (campo) => {
    router.get(route('admin.bancos.index'), { orden: campo }, { preserveState: true, preserveScroll: true, replace: true });
};

const page = usePage();
const importResult = computed(() => page.props.tt?.import_result || null);
const flashSuccess = computed(() => page.props.tt?.flash?.success || page.props.flash?.success || null);
const flashError = computed(() => page.props.tt?.flash?.error || page.props.flash?.error || null);

const form = useForm({ nombre: '', codigo: '', activo: true, es_propio: false });

const submit = () => {
    form.post(route('admin.bancos.store'), { preserveScroll: true, onSuccess: () => form.reset() });
};

const editing = ref(false);
const editForm = useForm({ nombre: '', codigo: '', activo: true, es_propio: false });
const editId = ref(null);

const openEdit = (b) => {
    editId.value = b.id;
    editForm.nombre = b.nombre;
    editForm.codigo = b.codigo || '';
    editForm.activo = !!b.activo;
    editForm.es_propio = !!b.es_propio;
    editForm.clearErrors();
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.bancos.update', editId.value), { preserveScroll: true, onSuccess: () => (editing.value = false) });
};

const deleteId = ref(null);
const deleteInfo = ref(null);
const deleteProcessing = ref(false);

const openDelete = (b) => {
    deleteId.value = b.id;
    deleteInfo.value = b.nombre;
    if (editing.value && editId.value === b.id) editing.value = false;
};

const submitDelete = () => {
    if (!deleteId.value) return;
    deleteProcessing.value = true;
    router.delete(route('admin.bancos.destroy', deleteId.value), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            deleteId.value = null;
            deleteInfo.value = null;
        },
    });
};
</script>

<template>
    <AppLayout title="Admin / Bancos">
        <Head title="Admin / Bancos" />

        <template #header>
            <h2 class="font-semibold text-lg text-gray-800 leading-tight">Admin / Bancos</h2>
        </template>

        <div class="max-w-4xl mx-auto py-3 sm:px-4 lg:px-6 space-y-4">
            <div v-if="importResult" class="bg-green-50 border border-green-200 text-green-900 px-4 py-2 rounded text-sm">{{ importResult.message || importResult }}</div>
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-2 rounded text-sm">{{ flashSuccess }}</div>
            <div v-if="flashError" class="bg-red-50 border border-red-200 text-red-900 px-4 py-2 rounded text-sm">{{ flashError }}</div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900">Nuevo banco</h3>
                <form class="mt-3 grid grid-cols-1 sm:grid-cols-5 gap-3" @submit.prevent="submit">
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
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-sm text-gray-700" title="El banco tiene cuentas propias de la empresa (cheques propios, transferencias)">
                            <input v-model="form.es_propio" type="checkbox" class="rounded border-gray-300" />
                            Cuenta propia
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
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><button type="button" class="hover:text-gray-800" @click="ordenar('nombre')">Nombre {{ orden === 'nombre' ? '▲' : '' }}</button></th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><button type="button" class="hover:text-gray-800" @click="ordenar('codigo')">Codigo {{ orden !== 'nombre' ? '▲' : '' }}</button></th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cta. propia</th>
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
                            <td class="px-3 py-2 text-xs">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="b.es_propio ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'">{{ b.es_propio ? 'Si' : 'No' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right text-xs whitespace-nowrap">
                                <SecondaryButton class="text-xs" @click="openEdit(b)">Editar</SecondaryButton>
                                <button type="button" class="ms-2 text-xs text-red-600 hover:text-red-800" @click="openDelete(b)">Eliminar</button>
                            </td>
                        </tr>
                        <tr v-if="!bancos.length">
                            <td colspan="5" class="px-3 py-3 text-center text-xs text-gray-500">Sin bancos.</td>
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
                        <label class="flex items-center gap-2 text-sm text-gray-700" title="El banco tiene cuentas propias de la empresa (cheques propios, transferencias)">
                            <input v-model="editForm.es_propio" type="checkbox" class="rounded border-gray-300" />
                            Cuenta propia
                        </label>
                    </form>
                </template>
                <template #footer>
                    <SecondaryButton @click="editing = false">Cancelar</SecondaryButton>
                    <PrimaryButton class="ms-3" :disabled="editForm.processing" @click="submitEdit">Guardar</PrimaryButton>
                </template>
            </DialogModal>

            <DialogModal :show="!!deleteId" @close="deleteId = null">
                <template #title>Eliminar banco</template>
                <template #content>
                    <p class="text-sm text-gray-700">¿Eliminar el banco <span class="font-semibold">{{ deleteInfo }}</span>? Esta acción no se puede deshacer.</p>
                    <p class="mt-2 text-xs text-gray-500">No se puede eliminar si tiene cheques depositados, egresos o movimientos bancarios.</p>
                </template>
                <template #footer>
                    <SecondaryButton class="!text-xs !px-3 !py-1.5" @click="deleteId = null">Cancelar</SecondaryButton>
                    <button type="button" class="ms-3 inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2" :disabled="deleteProcessing" @click="submitDelete">Eliminar</button>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>
