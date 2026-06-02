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
    empresas: Array,
});

const createForm = useForm({
    razon_social: '',
    cuit: '',
    condicion_iva: '',
    moneda_base: 'ARS',
    arca_pv_default: 2,
    arca_env: 'homologacion',
    permite_guias_no_fiscales: false,

    telefono: '',
    email: '',
    whatsapp: '',
    sitio_web: '',
    instagram_url: '',
    facebook_url: '',
    linkedin_url: '',
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
    moneda_base: 'ARS',
    arca_pv_default: 2,
    arca_env: 'homologacion',
    permite_guias_no_fiscales: false,

    telefono: '',
    email: '',
    whatsapp: '',
    sitio_web: '',
    instagram_url: '',
    facebook_url: '',
    linkedin_url: '',
});

const openEdit = (e) => {
    editId.value = e.id;
    editForm.razon_social = e.razon_social;
    editForm.cuit = e.cuit;
    editForm.condicion_iva = e.condicion_iva || '';
    editForm.moneda_base = e.moneda_base || 'ARS';
    editForm.arca_pv_default = e.arca_pv_default;
    editForm.arca_env = e.arca_env;
    editForm.permite_guias_no_fiscales = !!e.permite_guias_no_fiscales;

    editForm.telefono = e.telefono || '';
    editForm.email = e.email || '';
    editForm.whatsapp = e.whatsapp || '';
    editForm.sitio_web = e.sitio_web || '';
    editForm.instagram_url = e.instagram_url || '';
    editForm.facebook_url = e.facebook_url || '';
    editForm.linkedin_url = e.linkedin_url || '';

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
                        <InputLabel value="Moneda base" />
                        <select v-model="createForm.moneda_base" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="ARS">ARS</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="BRL">BRL</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.moneda_base" />
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
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="createForm.permite_guias_no_fiscales" />
                            Permite emitir guias no fiscales
                        </label>
                    </div>

                    <div>
                        <InputLabel value="Telefono" />
                        <TextInput v-model="createForm.telefono" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.telefono" />
                    </div>
                    <div>
                        <InputLabel value="Email" />
                        <TextInput v-model="createForm.email" type="email" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.email" />
                    </div>
                    <div>
                        <InputLabel value="WhatsApp" />
                        <TextInput v-model="createForm.whatsapp" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.whatsapp" />
                    </div>

                    <div class="sm:col-span-3">
                        <InputLabel value="Sitio web" />
                        <TextInput v-model="createForm.sitio_web" type="url" class="mt-1 block w-full" placeholder="https://..." />
                        <InputError class="mt-2" :message="createForm.errors.sitio_web" />
                    </div>

                    <div class="sm:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="Instagram" />
                            <TextInput v-model="createForm.instagram_url" type="url" class="mt-1 block w-full" placeholder="https://..." />
                            <InputError class="mt-2" :message="createForm.errors.instagram_url" />
                        </div>
                        <div>
                            <InputLabel value="Facebook" />
                            <TextInput v-model="createForm.facebook_url" type="url" class="mt-1 block w-full" placeholder="https://..." />
                            <InputError class="mt-2" :message="createForm.errors.facebook_url" />
                        </div>
                        <div>
                            <InputLabel value="LinkedIn" />
                            <TextInput v-model="createForm.linkedin_url" type="url" class="mt-1 block w-full" placeholder="https://..." />
                            <InputError class="mt-2" :message="createForm.errors.linkedin_url" />
                        </div>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PV</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ARCA</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guias</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="e in empresas" :key="e.id">
                                <td class="px-6 py-4 text-sm text-gray-900">{{ e.razon_social }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ e.cuit }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ e.moneda_base || 'ARS' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ e.arca_pv_default }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ e.arca_env }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ e.permite_guias_no_fiscales ? 'Si' : 'No' }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <SecondaryButton class="text-xs" @click.prevent="openEdit(e)">Editar</SecondaryButton>
                                </td>
                            </tr>
                            <tr v-if="!empresas.length">
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">Sin empresas.</td>
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
                        <InputLabel value="Moneda base" />
                        <select v-model="editForm.moneda_base" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="ARS">ARS</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="BRL">BRL</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.moneda_base" />
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
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700 pt-6">
                            <Checkbox v-model:checked="editForm.permite_guias_no_fiscales" />
                            Permite emitir guias no fiscales
                        </label>
                    </div>

                    <div>
                        <InputLabel value="Telefono" />
                        <TextInput v-model="editForm.telefono" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.telefono" />
                    </div>
                    <div>
                        <InputLabel value="Email" />
                        <TextInput v-model="editForm.email" type="email" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.email" />
                    </div>
                    <div>
                        <InputLabel value="WhatsApp" />
                        <TextInput v-model="editForm.whatsapp" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.whatsapp" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Sitio web" />
                        <TextInput v-model="editForm.sitio_web" type="url" class="mt-1 block w-full" placeholder="https://..." />
                        <InputError class="mt-2" :message="editForm.errors.sitio_web" />
                    </div>

                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="Instagram" />
                            <TextInput v-model="editForm.instagram_url" type="url" class="mt-1 block w-full" placeholder="https://..." />
                            <InputError class="mt-2" :message="editForm.errors.instagram_url" />
                        </div>
                        <div>
                            <InputLabel value="Facebook" />
                            <TextInput v-model="editForm.facebook_url" type="url" class="mt-1 block w-full" placeholder="https://..." />
                            <InputError class="mt-2" :message="editForm.errors.facebook_url" />
                        </div>
                        <div>
                            <InputLabel value="LinkedIn" />
                            <TextInput v-model="editForm.linkedin_url" type="url" class="mt-1 block w-full" placeholder="https://..." />
                            <InputError class="mt-2" :message="editForm.errors.linkedin_url" />
                        </div>
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
