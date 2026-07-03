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
import { ref, watch } from 'vue';

const props = defineProps({
    empresas: Array,
    empresaId: [Number, null],
    cuentas: Array,
    cuitInicial: String,
    provincias: Array,
    tipoInicial: String,
    proximoNumeroCliente: Number,
    cobradores: Array,
    condicionesIva: Array,
    compartidos: Boolean,
});

const localidades = ref([]);
const editLocalidades = ref([]);

const cargarLocalidades = async (provinciaId, target) => {
    if (!provinciaId) { target.value = []; return; }
    const url = route('admin.terceros.localidades-por-provincia', { provincia: provinciaId });
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    target.value = await res.json();
};

const buscarTercero = async (cuit, formObj) => {
    if (!cuit) return;
    const url = route('admin.terceros.lookup-cuit', { cuit });
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const data = await res.json();
    if (data.found) {
        formObj.razon_social = data.tercero.razon_social || '';
        formObj.condicion_iva = data.tercero.condicion_iva || '';
        formObj.condicion_iva_id = data.tercero.condicion_iva_id || '';
    }
};

const buscandoArca = ref(false);
const buscarEnArca = async (cuit, formObj) => {
    if (!cuit) return;
    buscandoArca.value = true;
    try {
        const url = route('admin.terceros.lookup-arca-cuit', { cuit });
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json();
        if (data.found) {
            formObj.razon_social = data.razon_social || '';
            const match = (props.condicionesIva || []).find((c) => c.nombre === data.condicion_iva);
            formObj.condicion_iva = data.condicion_iva || '';
            formObj.condicion_iva_id = match?.id || '';
        } else {
            alert(data.error || 'No encontrado en ARCA.');
        }
    } catch (e) {
        alert('Error al buscar en ARCA.');
    } finally {
        buscandoArca.value = false;
    }
};

const form = useForm({
    empresa_id: props.empresaId || props.empresas?.[0]?.id || null,
    numero_cliente: props.proximoNumeroCliente,
    cuit: props.cuitInicial || '',
    razon_social: '',
    condicion_iva: '',
    condicion_iva_id: '',
    nombre_cuenta: '',
    localidad: '',
    barrio: '',
    provincia_id: '',
    localidad_id: '',
    email: '',
    enviar_comprobantes_por_email: false,
    cobrador_user_id: '',
    es_cliente: props.tipoInicial === 'cliente' || !props.tipoInicial,
    es_proveedor: props.tipoInicial === 'proveedor',
});

watch(() => form.provincia_id, (val) => {
    form.localidad_id = '';
    cargarLocalidades(val, localidades);
});

const submit = () => {
    form.post(route('admin.terceros.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('numero_cliente', 'cuit', 'razon_social', 'condicion_iva', 'condicion_iva_id', 'nombre_cuenta', 'localidad', 'barrio', 'email', 'provincia_id', 'localidad_id'),
    });
};

const mostrarCompartidos = ref(props.compartidos);
const empresaFiltroId = ref(props.empresaId || '');

const toggleCompartidos = () => {
    router.get(route('admin.terceros.index'), { compartidos: mostrarCompartidos.value ? '1' : null, empresa_id: empresaFiltroId.value || null }, { preserveState: true, preserveScroll: true, replace: true });
};

const filtrarEmpresa = () => {
    router.get(route('admin.terceros.index'), { empresa_id: empresaFiltroId.value || null, compartidos: mostrarCompartidos.value ? '1' : null }, { preserveState: true, preserveScroll: true, replace: true });
};

const editing = ref(false);
const editId = ref(null);
const editForm = useForm({
    cuit: '',
    razon_social: '',
    condicion_iva: '',
    condicion_iva_id: '',
    nombre_cuenta: '',
    localidad: '',
    barrio: '',
    provincia_id: '',
    localidad_id: '',
    email: '',
    enviar_comprobantes_por_email: false,
    cobrador_user_id: '',
    es_cliente: false,
    es_proveedor: false,
});

const openEdit = (c) => {
    editId.value = c.id;
    editForm.cuit = c.tercero?.cuit || '';
    editForm.razon_social = c.tercero?.razon_social || '';
    editForm.condicion_iva = c.tercero?.condicion_iva || '';
    editForm.condicion_iva_id = c.tercero?.condicion_iva_id || '';
    editForm.nombre_cuenta = c.nombre_cuenta || '';
    editForm.localidad = c.localidad || '';
    editForm.barrio = c.barrio || '';
    editForm.provincia_id = c.provincia_id || '';
    editForm.localidad_id = c.localidad_id || '';
    editForm.email = c.email || '';
    editForm.enviar_comprobantes_por_email = !!c.enviar_comprobantes_por_email;
    editForm.cobrador_user_id = c.cobrador_user_id || '';
    editForm.es_cliente = !!c.es_cliente;
    editForm.es_proveedor = !!c.es_proveedor;
    editForm.clearErrors();
    editing.value = true;
    if (c.provincia_id) {
        cargarLocalidades(c.provincia_id, editLocalidades);
    }
};

watch(() => editForm.provincia_id, (val) => {
    editForm.localidad_id = '';
    cargarLocalidades(val, editLocalidades);
});

const submitEdit = () => {
    editForm.put(route('admin.terceros.update', editId.value), { preserveScroll: true, onSuccess: () => (editing.value = false) });
};

const provinciaNombre = (id) => {
    const p = props.provincias.find((x) => x.id === id);
    return p ? p.nombre : '';
};

const localidadNombre = (c) => {
    if (c.localidadRel) return c.localidadRel.nombre;
    if (c.localidad) return c.localidad;
    return '-';
};
</script>

<template>
    <AppLayout title="Admin / Terceros">
        <Head title="Admin / Terceros" />

        <template #header>
            <h2 class="font-semibold text-lg text-gray-800 leading-tight">Admin / Terceros</h2>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-4 lg:px-6 space-y-4">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900">Nueva cuenta</h3>

                <form class="mt-3 grid grid-cols-1 sm:grid-cols-6 gap-3" @submit.prevent="submit">
                    <div class="sm:col-span-2">
                        <InputLabel value="Empresa" />
                        <select v-model="form.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.empresa_id" />
                    </div>

                    <div>
                        <InputLabel value="Numero de cliente" />
                        <TextInput :value="form.numero_cliente" type="number" class="mt-1 block w-full bg-gray-100" readonly />
                        <InputError class="mt-1" :message="form.errors.numero_cliente" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="CUIT" />
                        <div class="flex gap-1">
                            <TextInput v-model="form.cuit" type="text" class="mt-1 block w-full" @blur="buscarTercero(form.cuit, form)" />
                            <SecondaryButton type="button" class="mt-1 shrink-0 text-xs" @click="buscarTercero(form.cuit, form)">Buscar</SecondaryButton>
                            <SecondaryButton type="button" class="mt-1 shrink-0 text-xs" :disabled="buscandoArca" @click="buscarEnArca(form.cuit, form)">{{ buscandoArca ? '...' : 'ARCA' }}</SecondaryButton>
                        </div>
                        <InputError class="mt-1" :message="form.errors.cuit" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Razon social" />
                        <TextInput v-model="form.razon_social" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.razon_social" />
                    </div>

                    <div>
                        <InputLabel value="Condicion IVA" />
                        <select v-model="form.condicion_iva_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">(seleccionar)</option>
                            <option v-for="c in condicionesIva" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.condicion_iva_id" />
                    </div>

                    <div class="sm:col-span-3">
                        <InputLabel value="Nombre cuenta (opcional)" />
                        <TextInput v-model="form.nombre_cuenta" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.nombre_cuenta" />
                    </div>

                    <div>
                        <InputLabel value="Provincia" />
                        <select v-model="form.provincia_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">(seleccionar)</option>
                            <option v-for="p in provincias" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.provincia_id" />
                    </div>

                    <div>
                        <InputLabel value="Ciudad" />
                        <select v-model="form.localidad_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">(seleccionar)</option>
                            <option v-for="l in localidades" :key="l.id" :value="l.id">{{ l.nombre }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.localidad_id" />
                    </div>

                    <div>
                        <InputLabel value="Email comprobantes" />
                        <TextInput v-model="form.email" type="email" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <div v-if="cobradores?.length">
                        <InputLabel value="Cobrador" />
                        <select v-model="form.cobrador_user_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">(sin asignar)</option>
                            <option v-for="c in cobradores" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.cobrador_user_id" />
                    </div>

                    <div class="sm:col-span-6 flex items-center gap-4 pt-2">
                        <label class="flex items-center gap-1 text-xs text-gray-700">
                            <Checkbox v-model:checked="form.es_cliente" />
                            Cliente
                        </label>
                        <label class="flex items-center gap-1 text-xs text-gray-700">
                            <Checkbox v-model:checked="form.es_proveedor" />
                            Proveedor
                        </label>
                        <label class="flex items-center gap-1 text-xs text-gray-700">
                            <Checkbox v-model:checked="form.enviar_comprobantes_por_email" />
                            Enviar email
                        </label>
                        <PrimaryButton class="ms-auto" :disabled="form.processing">Crear</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <h3 class="text-sm font-semibold text-gray-900">Cuentas</h3>
                            <select v-model="empresaFiltroId" class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1" @change="filtrarEmpresa">
                                <option value="">Todas las empresas</option>
                                <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-xs text-gray-700 shrink-0">
                            <Checkbox :checked="mostrarCompartidos" @click="mostrarCompartidos = !mostrarCompartidos; toggleCompartidos()" />
                            Comparten clientes
                        </label>
                    </div>
                </div>

                <div class="space-y-2 p-3 sm:hidden">
                    <div v-for="c in cuentas" :key="c.id" class="rounded-lg border border-gray-200 bg-white p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-xs font-semibold text-gray-900">{{ c.tercero?.razon_social || '-' }}</div>
                                <div class="text-xs text-gray-500">{{ c.empresa?.razon_social || '-' }} · Nro {{ c.numero_cliente }}</div>
                                <div v-if="mostrarCompartidos && c.shared_empresas?.length" class="mt-1 flex flex-wrap gap-1">
                                    <span v-for="(name, i) in c.shared_empresas" :key="i" class="inline-flex items-center rounded-full bg-purple-100 px-1.5 py-0.5 text-xs font-medium text-purple-800">{{ name }}</span>
                                </div>
                            </div>
                            <SecondaryButton class="text-xs" @click.prevent="openEdit(c)">Editar</SecondaryButton>
                        </div>
                        <div class="mt-2 grid grid-cols-1 gap-2 text-xs">
                            <div>
                                <span class="text-xs uppercase tracking-wider text-gray-500">CUIT / IVA: </span>
                                <span class="font-medium text-gray-900">{{ c.tercero?.cuit || '-' }} · {{ c.tercero?.condicion_iva || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs uppercase tracking-wider text-gray-500">Cuenta: </span>
                                <span class="font-medium text-gray-900">{{ c.nombre_cuenta || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs uppercase tracking-wider text-gray-500">Prov/Ciudad: </span>
                                <span class="font-medium text-gray-900">{{ provinciaNombre(c.provincia_id) || '-' }} / {{ localidadNombre(c) }}</span>
                            </div>
                            <div>
                                <span class="text-xs uppercase tracking-wider text-gray-500">Barrio: </span>
                                <span class="font-medium text-gray-900">{{ c.barrio || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-xs uppercase tracking-wider text-gray-500">Email: </span>
                                <span class="font-medium text-gray-900">{{ c.email || '-' }}</span>
                            </div>
                            <div v-if="c.cobrador_user">
                                <span class="text-xs uppercase tracking-wider text-gray-500">Cobrador: </span>
                                <span class="font-medium text-gray-900">{{ c.cobrador_user.name }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1 text-xs">
                                <span v-if="c.es_cliente" class="inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 font-medium text-green-800">Cliente</span>
                                <span v-if="c.es_proveedor" class="inline-flex items-center rounded-full bg-blue-100 px-1.5 py-0.5 font-medium text-blue-800">Prov</span>
                                <span v-if="c.enviar_comprobantes_por_email" class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 font-medium text-amber-800">Mail</span>
                                <span v-if="!c.es_cliente && !c.es_proveedor && !c.enviar_comprobantes_por_email" class="inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 font-medium text-gray-800">-</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="!cuentas.length" class="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-xs text-gray-500">Sin cuentas.</div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-[1400px] w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th v-if="mostrarCompartidos" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Compartida con</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nro</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CUIT</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Razon social</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IVA</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prov</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ciudad</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barrio</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cobrador</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flags</th>
                                <th class="sticky right-0 bg-gray-50 px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in cuentas" :key="c.id">
                                <td class="px-3 py-2 text-xs text-gray-700">{{ c.empresa?.razon_social || '-' }}</td>
                                <td v-if="mostrarCompartidos" class="px-3 py-2 text-xs text-gray-700">
                                    <template v-if="c.shared_empresas?.length">
                                        <span v-for="(name, i) in c.shared_empresas" :key="i" class="inline-flex items-center rounded-full bg-purple-100 px-1.5 py-0.5 text-xs font-medium text-purple-800 me-1">{{ name }}</span>
                                    </template>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-900 font-mono">{{ c.numero_cliente }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700 font-mono">{{ c.tercero?.cuit || '-' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-900">{{ c.tercero?.razon_social || '-' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700">{{ c.tercero?.condicion_iva || '-' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700">{{ c.nombre_cuenta || '-' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700">{{ provinciaNombre(c.provincia_id) || '-' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700">{{ localidadNombre(c) }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700">{{ c.barrio || '-' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700">{{ c.email || '-' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700">{{ c.cobrador_user?.name || '-' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-700">
                                    <span v-if="c.es_cliente" class="inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-800">Cliente</span>
                                    <span v-if="c.es_proveedor" class="ms-1 inline-flex items-center rounded-full bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-800">Prov</span>
                                    <span v-if="c.enviar_comprobantes_por_email" class="ms-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">Mail</span>
                                    <span v-if="!c.es_cliente && !c.es_proveedor && !c.enviar_comprobantes_por_email" class="inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-800">-</span>
                                </td>
                                <td class="sticky right-0 bg-white px-3 py-2 text-right text-xs">
                                    <SecondaryButton class="text-xs" @click.prevent="openEdit(c)">Editar</SecondaryButton>
                                </td>
                            </tr>
                            <tr v-if="!cuentas.length">
                                <td :colspan="mostrarCompartidos ? 14 : 13" class="px-3 py-6 text-center text-xs text-gray-500">Sin cuentas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <DialogModal :show="editing" @close="editing = false">
                <template #title>Editar cuenta</template>
                <template #content>
                    <form class="grid grid-cols-1 sm:grid-cols-2 gap-3" @submit.prevent="submitEdit">
                        <div class="sm:col-span-2">
                            <InputLabel value="CUIT" />
                            <div class="flex gap-1">
                                <TextInput v-model="editForm.cuit" type="text" class="mt-1 block w-full" @blur="buscarTercero(editForm.cuit, editForm)" />
                                <SecondaryButton type="button" class="mt-1 shrink-0 text-xs" @click="buscarTercero(editForm.cuit, editForm)">Buscar</SecondaryButton>
                                <SecondaryButton type="button" class="mt-1 shrink-0 text-xs" :disabled="buscandoArca" @click="buscarEnArca(editForm.cuit, editForm)">{{ buscandoArca ? '...' : 'ARCA' }}</SecondaryButton>
                            </div>
                            <InputError class="mt-1" :message="editForm.errors.cuit" />
                        </div>
                        <div>
                            <InputLabel value="Razon social" />
                            <TextInput v-model="editForm.razon_social" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="editForm.errors.razon_social" />
                        </div>
                        <div>
                            <InputLabel value="Condicion IVA" />
                            <select v-model="editForm.condicion_iva_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">(seleccionar)</option>
                                <option v-for="c in condicionesIva" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                            <InputError class="mt-1" :message="editForm.errors.condicion_iva_id" />
                        </div>
                        <div>
                            <InputLabel value="Nombre cuenta" />
                            <TextInput v-model="editForm.nombre_cuenta" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="editForm.errors.nombre_cuenta" />
                        </div>
                        <div>
                            <InputLabel value="Provincia" />
                            <select v-model="editForm.provincia_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">(seleccionar)</option>
                                <option v-for="p in provincias" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                            </select>
                            <InputError class="mt-1" :message="editForm.errors.provincia_id" />
                        </div>
                        <div>
                            <InputLabel value="Ciudad" />
                            <select v-model="editForm.localidad_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">(seleccionar)</option>
                                <option v-for="l in editLocalidades" :key="l.id" :value="l.id">{{ l.nombre }}</option>
                            </select>
                            <InputError class="mt-1" :message="editForm.errors.localidad_id" />
                        </div>
                        <div>
                            <InputLabel value="Barrio" />
                            <TextInput v-model="editForm.barrio" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="editForm.errors.barrio" />
                        </div>
                        <div>
                            <InputLabel value="Email comprobantes" />
                            <TextInput v-model="editForm.email" type="email" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="editForm.errors.email" />
                        </div>
                        <div v-if="cobradores?.length">
                            <InputLabel value="Cobrador" />
                            <select v-model="editForm.cobrador_user_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">(sin asignar)</option>
                                <option v-for="c in cobradores" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <InputError class="mt-1" :message="editForm.errors.cobrador_user_id" />
                        </div>
                        <div class="sm:col-span-2 flex flex-wrap items-center gap-4 pt-2">
                            <label class="flex items-center gap-1 text-xs text-gray-700">
                                <Checkbox v-model:checked="editForm.es_cliente" />
                                Cliente
                            </label>
                            <label class="flex items-center gap-1 text-xs text-gray-700">
                                <Checkbox v-model:checked="editForm.es_proveedor" />
                                Proveedor
                            </label>
                            <label class="flex items-center gap-1 text-xs text-gray-700">
                                <Checkbox v-model:checked="editForm.enviar_comprobantes_por_email" />
                                Enviar email
                            </label>
                        </div>
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
