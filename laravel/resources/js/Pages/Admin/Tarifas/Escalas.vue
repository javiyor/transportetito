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

const props = defineProps({
    empresas: Array,
    empresaId: [Number, null],
    escalas: Array,
});

const changeEmpresa = (id) => {
    router.get(route('admin.tarifas.escalas.index'), { empresa_id: id || null }, { preserveState: true, preserveScroll: true, replace: true });
};

const createForm = useForm({
    empresa_id: props.empresaId || props.empresas?.[0]?.id || null,
    origen_localidad: '',
    destino_localidad: '',
    tipo_envio: '',
    producto: '',
    precio_kg: 0,
    precio_bulto: 0,
    precio_medida_bulto: 0,
    precio_palet: 0,
    servicio_minimo: 0,
    servicio_retiro: 0,
});

const submitCreate = () => {
    createForm.post(route('admin.tarifas.escalas.store'), { preserveScroll: true });
};

const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    origen_localidad: '',
    destino_localidad: '',
    tipo_envio: '',
    producto: '',
    precio_kg: 0,
    precio_bulto: 0,
    precio_medida_bulto: 0,
    precio_palet: 0,
    servicio_minimo: 0,
    servicio_retiro: 0,
    activo: true,
});

const openEdit = (e) => {
    editId.value = e.id;
    editForm.origen_localidad = e.origen_localidad;
    editForm.destino_localidad = e.destino_localidad;
    editForm.tipo_envio = e.tipo_envio || '';
    editForm.producto = e.producto || '';
    editForm.precio_kg = e.precio_kg;
    editForm.precio_bulto = e.precio_bulto;
    editForm.precio_medida_bulto = e.precio_medida_bulto;
    editForm.precio_palet = e.precio_palet;
    editForm.servicio_minimo = e.servicio_minimo;
    editForm.servicio_retiro = e.servicio_retiro;
    editForm.activo = !!e.activo;
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.tarifas.escalas.update', editId.value), { preserveScroll: true, onSuccess: () => editing.value = false });
};
</script>

<template>
    <AppLayout title="Tarifas Escala Standard">
        <Head title="Tarifas Escala Standard" />
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tarifas Escala Standard</h2>
                <select class="block border-gray-300 rounded-md shadow-sm text-sm" :value="empresaId || ''" @change="changeEmpresa($event.target.value ? parseInt($event.target.value,10) : null)">
                    <option value="">Todas</option>
                    <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                </select>
            </div>
        </template>
        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-base font-semibold text-gray-900">Nueva escala</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submitCreate">
                    <div><InputLabel value="Empresa" /><select v-model="createForm.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option></select><InputError :message="createForm.errors.empresa_id" /></div>
                    <div><InputLabel value="Origen" /><TextInput v-model="createForm.origen_localidad" type="text" class="mt-1 block w-full" required /><InputError :message="createForm.errors.origen_localidad" /></div>
                    <div><InputLabel value="Destino" /><TextInput v-model="createForm.destino_localidad" type="text" class="mt-1 block w-full" required /><InputError :message="createForm.errors.destino_localidad" /></div>
                    <div><InputLabel value="Tipo envío" /><TextInput v-model="createForm.tipo_envio" type="text" class="mt-1 block w-full" placeholder="estándar" /><InputError :message="createForm.errors.tipo_envio" /></div>
                    <div><InputLabel value="Producto" /><TextInput v-model="createForm.producto" type="text" class="mt-1 block w-full" placeholder="general" /><InputError :message="createForm.errors.producto" /></div>
                    <div><InputLabel value="Precio kg" /><TextInput v-model="createForm.precio_kg" type="number" step="0.01" class="mt-1 block w-full" required /><InputError :message="createForm.errors.precio_kg" /></div>
                    <div><InputLabel value="Precio bulto" /><TextInput v-model="createForm.precio_bulto" type="number" step="0.01" class="mt-1 block w-full" required /><InputError :message="createForm.errors.precio_bulto" /></div>
                    <div><InputLabel value="Precio medida" /><TextInput v-model="createForm.precio_medida_bulto" type="number" step="0.01" class="mt-1 block w-full" /><InputError :message="createForm.errors.precio_medida_bulto" /></div>
                    <div><InputLabel value="Precio palet" /><TextInput v-model="createForm.precio_palet" type="number" step="0.01" class="mt-1 block w-full" required /><InputError :message="createForm.errors.precio_palet" /></div>
                    <div><InputLabel value="Servicio mínimo" /><TextInput v-model="createForm.servicio_minimo" type="number" step="0.01" class="mt-1 block w-full" /><InputError :message="createForm.errors.servicio_minimo" /></div>
                    <div><InputLabel value="Servicio retiro" /><TextInput v-model="createForm.servicio_retiro" type="number" step="0.01" class="mt-1 block w-full" /><InputError :message="createForm.errors.servicio_retiro" /></div>
                    <div class="sm:col-span-4 flex justify-end"><PrimaryButton :disabled="createForm.processing">Guardar</PrimaryButton></div>
                </form>
            </div>
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Escalas</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Origen → Destino</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo/Producto</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">kg/bulto/med/palet</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Mín/Retiro</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="e in escalas" :key="e.id">
                                <td class="px-4 py-2 text-sm">{{ e.origen_localidad }} → {{ e.destino_localidad }}</td>
                                <td class="px-4 py-2 text-sm">{{ e.tipo_envio || '-' }} / {{ e.producto || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-right font-mono">{{ e.precio_kg }} / {{ e.precio_bulto }} / {{ e.precio_medida_bulto }} / {{ e.precio_palet }}</td>
                                <td class="px-4 py-2 text-sm text-right font-mono">{{ e.servicio_minimo }} / {{ e.servicio_retiro }}</td>
                                <td class="px-4 py-2 text-right"><SecondaryButton class="text-xs" @click="openEdit(e)">Editar</SecondaryButton></td>
                            </tr>
                            <tr v-if="!escalas.length"><td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Sin escalas.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <DialogModal :show="editing" @close="editing=false">
            <template #title>Editar escala</template>
            <template #content>
                <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="submitEdit">
                    <div><InputLabel value="Origen" /><TextInput v-model="editForm.origen_localidad" type="text" class="mt-1 block w-full" required /></div>
                    <div><InputLabel value="Destino" /><TextInput v-model="editForm.destino_localidad" type="text" class="mt-1 block w-full" required /></div>
                    <div><InputLabel value="Tipo envío" /><TextInput v-model="editForm.tipo_envio" type="text" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Producto" /><TextInput v-model="editForm.producto" type="text" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Precio kg" /><TextInput v-model="editForm.precio_kg" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Precio bulto" /><TextInput v-model="editForm.precio_bulto" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Precio medida" /><TextInput v-model="editForm.precio_medida_bulto" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Precio palet" /><TextInput v-model="editForm.precio_palet" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Servicio mínimo" /><TextInput v-model="editForm.servicio_minimo" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Servicio retiro" /><TextInput v-model="editForm.servicio_retiro" type="number" step="0.01" class="mt-1 block w-full" /></div>
                </form>
            </template>
            <template #footer><SecondaryButton @click="editing=false">Cancelar</SecondaryButton><PrimaryButton class="ms-3" @click="submitEdit">Guardar</PrimaryButton></template>
        </DialogModal>
    </AppLayout>
</template>
