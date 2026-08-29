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
    alertasCount: Number,
});

const createForm = useForm({
    empresa_id: props.empresaId || props.empresas?.[0]?.id || null,
    patente: '',
    marca: '',
    modelo: '',
    activo: true,
    titulo_archivo: null,
    rto_archivo: null,
    seguro_archivo: null,
    observaciones: '',
    controles: [],
});

const submitCreate = () => {
    createForm.post(route('admin.vehiculos.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('patente', 'marca', 'modelo', 'titulo_archivo', 'rto_archivo', 'seguro_archivo', 'observaciones', 'controles'),
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
    titulo_archivo: null,
    rto_archivo: null,
    seguro_archivo: null,
    observaciones: '',
    controles: [],
});
const editExistingFiles = ref({});

const openEdit = (v) => {
    editId.value = v.id;
    editForm.empresa_id = v.empresa_id;
    editForm.patente = v.patente;
    editForm.marca = v.marca || '';
    editForm.modelo = v.modelo || '';
    editForm.activo = !!v.activo;
    editForm.observaciones = v.observaciones || '';
    editForm.titulo_archivo = null;
    editForm.rto_archivo = null;
    editForm.seguro_archivo = null;
    editForm.controles = (v.controles || []).map(c => ({
        tipo: c.tipo || '',
        fecha_vencimiento: c.fecha_vencimiento ? String(c.fecha_vencimiento).slice(0, 10) : '',
        observacion: c.observacion || '',
    }));
    editForm.clearErrors();
    editExistingFiles.value = {
        titulo: v.titulo_archivo || null,
        rto: v.rto_archivo || null,
        seguro: v.seguro_archivo || null,
    };
    editing.value = true;
};

const submitEdit = () => {
    editForm.put(route('admin.vehiculos.update', editId.value), {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
};

const agregarControl = (form) => {
    form.controles.push({ tipo: '', fecha_vencimiento: '', observacion: '' });
};

const quitarControl = (form, idx) => {
    form.controles.splice(idx, 1);
};

const changeEmpresa = (id) => {
    router.get(route('admin.vehiculos.index'), { empresa_id: id || null }, { preserveState: true, preserveScroll: true, replace: true });
};

const formatFileSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const docUrl = (filename) => {
    if (!filename) return null;
    return '/storage/vehiculos/' + filename;
};

const formatFecha = (v) => v ? String(v).slice(0, 10) : '-';

const alertaProxima = (controles) => {
    const hoy = new Date();
    const limite = new Date();
    limite.setDate(limite.getDate() + 10);
    return (controles || []).some(c => {
        if (!c.fecha_vencimiento) return false;
        const f = new Date(String(c.fecha_vencimiento).slice(0, 10));
        return f >= hoy && f <= limite;
    });
};

const alertaVencida = (controles) => {
    const hoy = new Date();
    return (controles || []).some(c => {
        if (!c.fecha_vencimiento) return false;
        const f = new Date(String(c.fecha_vencimiento).slice(0, 10));
        return f < hoy;
    });
};

const tiposControl = ['RTO', 'VTV', 'Matafuegos', 'Seguro', 'Carga térmica', 'Lubricentro', 'Otro'];
</script>

<template>
    <AppLayout title="Admin / Vehiculos">
        <Head title="Admin / Vehiculos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Vehiculos</h2>
                <div class="flex items-center gap-3">
                    <span v-if="alertasCount > 0" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-bold text-white bg-red-500 rounded-full">
                        {{ alertasCount }} alerta{{ alertasCount > 1 ? 's' : '' }}
                    </span>
                    <div class="w-72">
                        <select class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" :value="empresaId || ''" @change="changeEmpresa($event.target.value ? parseInt($event.target.value, 10) : null)">
                            <option value="">Todas las empresas</option>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div class="bg-white shadow sm:rounded-lg p-4">
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

                    <div>
                        <InputLabel value="Titulo (PDF)" />
                        <input type="file" accept=".pdf" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" @change="createForm.titulo_archivo = $event.target.files[0] || null" />
                        <InputError class="mt-2" :message="createForm.errors.titulo_archivo" />
                    </div>
                    <div>
                        <InputLabel value="RTO / VTV (PDF)" />
                        <input type="file" accept=".pdf" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" @change="createForm.rto_archivo = $event.target.files[0] || null" />
                        <InputError class="mt-2" :message="createForm.errors.rto_archivo" />
                    </div>
                    <div>
                        <InputLabel value="Seguro (PDF)" />
                        <input type="file" accept=".pdf" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" @change="createForm.seguro_archivo = $event.target.files[0] || null" />
                        <InputError class="mt-2" :message="createForm.errors.seguro_archivo" />
                    </div>
                    <div>
                        <InputLabel value="Observaciones" />
                        <textarea v-model="createForm.observaciones" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" rows="2"></textarea>
                        <InputError class="mt-2" :message="createForm.errors.observaciones" />
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
                <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Vehiculos</h3>
                    <span v-if="alertasCount > 0" class="text-xs text-red-600 font-medium">{{ alertasCount }} alerta{{ alertasCount > 1 ? 's' : '' }} de vencimiento proximo</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Alertas</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Patente</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Marca</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Modelo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Documentos</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Controles</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Observaciones</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Activo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="v in vehiculos" :key="v.id" :class="alertaVencida(v.controles) ? 'bg-red-50' : alertaProxima(v.controles) ? 'bg-yellow-50' : ''">
                                <td class="px-4 py-2">
                                    <span v-if="alertaVencida(v.controles)" class="inline-block w-2.5 h-2.5 rounded-full bg-red-500" title="Vencido"></span>
                                    <span v-else-if="alertaProxima(v.controles)" class="inline-block w-2.5 h-2.5 rounded-full bg-yellow-500" title="Proximo a vencer"></span>
                                    <span v-else class="inline-block w-2.5 h-2.5 rounded-full bg-green-400" title="Al dia"></span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">{{ v.empresa?.razon_social || '-' }}</td>
                                <td class="px-4 py-2 text-sm font-mono text-gray-900 font-semibold">{{ v.patente }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ v.marca || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ v.modelo || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">
                                    <div v-if="v.titulo_archivo" class="mb-1"><a :href="docUrl(v.titulo_archivo)" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs">Titulo</a></div>
                                    <div v-if="v.rto_archivo" class="mb-1"><a :href="docUrl(v.rto_archivo)" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs">RTO/VTV</a></div>
                                    <div v-if="v.seguro_archivo"><a :href="docUrl(v.seguro_archivo)" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs">Seguro</a></div>
                                    <span v-if="!v.titulo_archivo && !v.rto_archivo && !v.seguro_archivo" class="text-xs text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700">
                                    <div v-if="v.controles?.length">
                                        <div v-for="c in v.controles" :key="c.id" class="text-xs" :class="c.fecha_vencimiento && new Date(c.fecha_vencimiento) < new Date() ? 'text-red-600 font-medium' : c.fecha_vencimiento && new Date(c.fecha_vencimiento) <= new Date(Date.now() + 10*86400000) ? 'text-yellow-600' : 'text-gray-600'">
                                            {{ c.tipo }}: {{ formatFecha(c.fecha_vencimiento) }}
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700 max-w-xs truncate">{{ v.observaciones || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ v.activo ? 'Si' : 'No' }}</td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <SecondaryButton class="text-xs" @click.prevent="openEdit(v)">Editar</SecondaryButton>
                                </td>
                            </tr>
                            <tr v-if="!vehiculos.length">
                                <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">Sin vehiculos.</td>
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

                    <div>
                        <InputLabel value="Titulo (PDF)" />
                        <div v-if="editExistingFiles.titulo" class="mb-1"><a :href="docUrl(editExistingFiles.titulo)" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs">Ver actual</a></div>
                        <input type="file" accept=".pdf" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" @change="editForm.titulo_archivo = $event.target.files[0] || null" />
                        <InputError class="mt-2" :message="editForm.errors.titulo_archivo" />
                    </div>
                    <div>
                        <InputLabel value="RTO / VTV (PDF)" />
                        <div v-if="editExistingFiles.rto" class="mb-1"><a :href="docUrl(editExistingFiles.rto)" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs">Ver actual</a></div>
                        <input type="file" accept=".pdf" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" @change="editForm.rto_archivo = $event.target.files[0] || null" />
                        <InputError class="mt-2" :message="editForm.errors.rto_archivo" />
                    </div>
                    <div>
                        <InputLabel value="Seguro (PDF)" />
                        <div v-if="editExistingFiles.seguro" class="mb-1"><a :href="docUrl(editExistingFiles.seguro)" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs">Ver actual</a></div>
                        <input type="file" accept=".pdf" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" @change="editForm.seguro_archivo = $event.target.files[0] || null" />
                        <InputError class="mt-2" :message="editForm.errors.seguro_archivo" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Observaciones" />
                        <textarea v-model="editForm.observaciones" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" rows="2"></textarea>
                        <InputError class="mt-2" :message="editForm.errors.observaciones" />
                    </div>

                    <div class="sm:col-span-2 flex items-center gap-2">
                        <Checkbox v-model:checked="editForm.activo" />
                        <span class="text-sm text-gray-700">Activo</span>
                    </div>

                    <div class="sm:col-span-2 border-t border-gray-200 pt-4 mt-2">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-semibold text-gray-900">Controles / Verificaciones tecnicas</h4>
                            <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium" @click="agregarControl(editForm)">+ Agregar control</button>
                        </div>
                        <div v-if="!editForm.controles.length" class="text-xs text-gray-400 py-2">Sin controles cargados.</div>
                        <div v-for="(c, idx) in editForm.controles" :key="idx" class="grid grid-cols-1 sm:grid-cols-4 gap-2 mb-2 pb-2 border-b border-gray-100 last:border-b-0">
                            <div>
                                <select v-model="c.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-xs py-1">
                                    <option value="">Seleccionar...</option>
                                    <option v-for="t in tiposControl" :key="t" :value="t">{{ t }}</option>
                                </select>
                                <InputError class="mt-1" :message="editForm.errors['controles.' + idx + '.tipo']" />
                            </div>
                            <div>
                                <TextInput v-model="c.fecha_vencimiento" type="date" class="block w-full text-xs py-1" />
                                <InputError class="mt-1" :message="editForm.errors['controles.' + idx + '.fecha_vencimiento']" />
                            </div>
                            <div>
                                <input v-model="c.observacion" type="text" class="block w-full border-gray-300 rounded-md shadow-sm text-xs py-1 px-2" placeholder="Observacion" />
                            </div>
                            <div class="flex items-end pb-1">
                                <button type="button" class="text-xs text-red-600 hover:text-red-800" @click="quitarControl(editForm, idx)">Quitar</button>
                            </div>
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