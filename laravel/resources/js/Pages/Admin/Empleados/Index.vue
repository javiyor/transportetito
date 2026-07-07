<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    empleados: Object,
    empresaId: Number,
});

const emptyTel = () => ({ numero: '', referencia: '' });

const createForm = useForm({
    nombre: '',
    apellido: '',
    dni: '',
    fecha_nacimiento: '',
    domicilio: '',
    puesto: '',
    fecha_ingreso: '',
    dias_vacaciones: 14,
    razon_social: '',
    observaciones: '',
    telefonos: [emptyTel()],
});

const submitCreate = () => {
    createForm.post(route('admin.empleados.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(
            'nombre', 'apellido', 'dni', 'fecha_nacimiento', 'domicilio',
            'puesto', 'fecha_ingreso', 'razon_social', 'observaciones'
        ),
    });
};

const addTel = (f) => { f.telefonos.push(emptyTel()); };
const removeTel = (f, i) => { if (f.telefonos.length > 1) f.telefonos.splice(i, 1); };

const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    nombre: '', apellido: '', dni: '', fecha_nacimiento: '', domicilio: '',
    puesto: '', fecha_ingreso: '', dias_vacaciones: 14, razon_social: '',
    observaciones: '', telefonos: [emptyTel()], activo: true,
});

const openEdit = (e) => {
    editId.value = e.id;
    editForm.nombre = e.nombre;
    editForm.apellido = e.apellido;
    editForm.dni = e.dni;
    editForm.fecha_nacimiento = e.fecha_nacimiento || '';
    editForm.domicilio = e.domicilio || '';
    editForm.puesto = e.puesto || '';
    editForm.fecha_ingreso = e.fecha_ingreso || '';
    editForm.dias_vacaciones = e.dias_vacaciones ?? 14;
    editForm.razon_social = e.razon_social || '';
    editForm.observaciones = e.observaciones || '';
    editForm.telefonos = (e.telefonos?.length ? e.telefonos : [emptyTel()]).map(t => ({ numero: t.numero || '', referencia: t.referencia || '' }));
    editForm.activo = !!e.activo;
    editForm.clearErrors();
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.empleados.update', editId.value), {
        preserveScroll: true,
        onSuccess: () => { editing.value = false; },
    });
};

const confirmDelete = (e) => {
    if (confirm(`Eliminar a ${e.nombre} ${e.apellido}?`)) {
        router.delete(route('admin.empleados.destroy', e.id), { preserveScroll: true });
    }
};

const calcularAntiguedad = (fechaIngreso) => {
    if (!fechaIngreso) return '-';
    const desde = new Date(fechaIngreso);
    const hoy = new Date();
    let años = hoy.getFullYear() - desde.getFullYear();
    let meses = hoy.getMonth() - desde.getMonth();
    if (meses < 0) { años--; meses += 12; }
    if (años > 0) return `${años}a ${meses}m`;
    return `${meses}m`;
};
</script>

<template>
    <AppLayout title="Admin / Empleados">
        <Head title="Admin / Empleados" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Empleados</h2>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <!-- CREATE FORM -->
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nuevo empleado</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4" @submit.prevent="submitCreate">
                    <div><InputLabel value="Nombre" /><TextInput v-model="createForm.nombre" type="text" class="mt-1 block w-full" required /><InputError :message="createForm.errors.nombre" /></div>
                    <div><InputLabel value="Apellido" /><TextInput v-model="createForm.apellido" type="text" class="mt-1 block w-full" required /><InputError :message="createForm.errors.apellido" /></div>
                    <div><InputLabel value="DNI" /><TextInput v-model="createForm.dni" type="text" class="mt-1 block w-full" required /><InputError :message="createForm.errors.dni" /></div>
                    <div><InputLabel value="Fecha nacimiento" /><TextInput v-model="createForm.fecha_nacimiento" type="date" class="mt-1 block w-full" /><InputError :message="createForm.errors.fecha_nacimiento" /></div>
                    <div><InputLabel value="Domicilio" /><TextInput v-model="createForm.domicilio" type="text" class="mt-1 block w-full" /><InputError :message="createForm.errors.domicilio" /></div>
                    <div><InputLabel value="Puesto" /><TextInput v-model="createForm.puesto" type="text" class="mt-1 block w-full" /><InputError :message="createForm.errors.puesto" /></div>
                    <div><InputLabel value="Fecha ingreso" /><TextInput v-model="createForm.fecha_ingreso" type="date" class="mt-1 block w-full" /><InputError :message="createForm.errors.fecha_ingreso" /></div>
                    <div><InputLabel value="Dias vacaciones" /><TextInput v-model="createForm.dias_vacaciones" type="number" min="0" max="99" class="mt-1 block w-full" /><InputError :message="createForm.errors.dias_vacaciones" /></div>
                    <div><InputLabel value="Razon social (empleado)" /><TextInput v-model="createForm.razon_social" type="text" class="mt-1 block w-full" /><InputError :message="createForm.errors.razon_social" /></div>
                    <div class="sm:col-span-3"><InputLabel value="Observaciones" /><textarea v-model="createForm.observaciones" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2"></textarea><InputError :message="createForm.errors.observaciones" /></div>

                    <div class="sm:col-span-3 border-t border-gray-200 pt-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Telefonos</span>
                            <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800" @click="addTel(createForm)">+ Agregar</button>
                        </div>
                        <div v-for="(t, i) in createForm.telefonos" :key="i" class="flex items-center gap-2 mb-2">
                            <TextInput v-model="t.numero" type="text" class="block w-48" placeholder="Numero" />
                            <TextInput v-model="t.referencia" type="text" class="block w-48" placeholder="Referencia (ej: particular)" />
                            <button type="button" class="text-xs text-red-600 hover:text-red-800" @click="removeTel(createForm, i)">Quitar</button>
                        </div>
                    </div>

                    <div class="sm:col-span-3 flex justify-end"><PrimaryButton :disabled="createForm.processing">Crear</PrimaryButton></div>
                </form>
            </div>

            <!-- TABLE -->
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Empleados</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">DNI</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Puesto</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ingreso</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Antigüedad</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Vac.</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Telefonos</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activo</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="e in empleados.data" :key="e.id">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-900">{{ e.apellido }}, {{ e.nombre }}</div>
                                    <div class="text-xs text-gray-500">{{ e.razon_social || '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ e.dni }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ e.puesto || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ e.fecha_ingreso || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 font-medium">{{ calcularAntiguedad(e.fecha_ingreso) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-center">{{ e.dias_vacaciones }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <div v-for="t in e.telefonos" :key="t.id" class="text-xs">
                                        {{ t.numero }}<span v-if="t.referencia" class="text-gray-400"> ({{ t.referencia }})</span>
                                    </div>
                                    <span v-if="!e.telefonos?.length" class="text-xs text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="e.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">{{ e.activo ? 'Si' : 'No' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                                    <button class="text-indigo-600 hover:text-indigo-800 mr-2" @click="openEdit(e)">Editar</button>
                                    <button class="text-red-600 hover:text-red-800" @click="confirmDelete(e)">Eliminar</button>
                                </td>
                            </tr>
                            <tr v-if="!empleados.data?.length">
                                <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-400">Sin empleados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- EDIT MODAL -->
        <DialogModal :show="editing" @close="editing = false">
            <template #title>Editar empleado</template>
            <template #content>
                <form class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><InputLabel value="Nombre" /><TextInput v-model="editForm.nombre" type="text" class="mt-1 block w-full" /><InputError :message="editForm.errors.nombre" /></div>
                    <div><InputLabel value="Apellido" /><TextInput v-model="editForm.apellido" type="text" class="mt-1 block w-full" /><InputError :message="editForm.errors.apellido" /></div>
                    <div><InputLabel value="DNI" /><TextInput v-model="editForm.dni" type="text" class="mt-1 block w-full" /><InputError :message="editForm.errors.dni" /></div>
                    <div><InputLabel value="Fecha nacimiento" /><TextInput v-model="editForm.fecha_nacimiento" type="date" class="mt-1 block w-full" /></div>
                    <div class="sm:col-span-2"><InputLabel value="Domicilio" /><TextInput v-model="editForm.domicilio" type="text" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Puesto" /><TextInput v-model="editForm.puesto" type="text" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Fecha ingreso" /><TextInput v-model="editForm.fecha_ingreso" type="date" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Dias vacaciones" /><TextInput v-model="editForm.dias_vacaciones" type="number" min="0" max="99" class="mt-1 block w-full" /></div>
                    <div class="sm:col-span-2"><InputLabel value="Razon social" /><TextInput v-model="editForm.razon_social" type="text" class="mt-1 block w-full" /></div>
                    <div class="sm:col-span-2"><InputLabel value="Observaciones" /><textarea v-model="editForm.observaciones" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2"></textarea></div>
                    <div class="sm:col-span-2 flex items-center gap-2">
                        <input type="checkbox" v-model="editForm.activo" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">Activo</span>
                    </div>

                    <div class="sm:col-span-2 border-t border-gray-200 pt-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Telefonos</span>
                            <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800" @click="addTel(editForm)">+ Agregar</button>
                        </div>
                        <div v-for="(t, i) in editForm.telefonos" :key="i" class="flex items-center gap-2 mb-2">
                            <TextInput v-model="t.numero" type="text" class="block w-48" placeholder="Numero" />
                            <TextInput v-model="t.referencia" type="text" class="block w-48" placeholder="Referencia" />
                            <button type="button" class="text-xs text-red-600 hover:text-red-800" @click="removeTel(editForm, i)">Quitar</button>
                        </div>
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="editing = false">Cancelar</SecondaryButton>
                <PrimaryButton class="ml-2" :disabled="editForm.processing" @click="submitEdit">Guardar</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>