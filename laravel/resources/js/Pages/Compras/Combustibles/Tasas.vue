<script setup>
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    tasasPorMes: Object,
    tiposCombustible: Array,
});

const form = useForm({
    combustible_tipo: 'gasoil_grado_2',
    mes: new Date().toISOString().slice(0, 7),
    monto_por_litro: '',
});

const tipoForm = useForm({ nombre: '' });
const tipoEditForm = useForm({ nombre: '' });
const editandoTipoId = ref(null);

const submit = () => {
    form.post(route('compras.combustibles.tasas.store'), {
        preserveScroll: true,
        onSuccess: () => { form.monto_por_litro = ''; },
    });
};

const submitTipo = () => tipoForm.post(route('compras.combustibles.tipos.store'), {
    preserveScroll: true,
    onSuccess: () => tipoForm.reset(),
});

const editarTipo = (tipo) => {
    editandoTipoId.value = tipo.id;
    tipoEditForm.nombre = tipo.nombre;
    tipoEditForm.clearErrors();
};

const guardarTipo = () => tipoEditForm.put(route('compras.combustibles.tipos.update', editandoTipoId.value), {
    preserveScroll: true,
    onSuccess: () => { editandoTipoId.value = null; },
});

const eliminarTipo = (tipo) => {
    if (!confirm(`¿Eliminar el tipo de combustible "${tipo.nombre}"?`)) return;
    router.delete(route('compras.combustibles.tipos.destroy', tipo.id), { preserveScroll: true });
};

const eliminar = async (id) => {
    if (!confirm('Eliminar esta tasa?')) return;
    await fetch(route('compras.combustibles.tasas.destroy', id), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    window.location.reload();
};

const tipoLabel = (value) => props.tiposCombustible?.find((tipo) => tipo.codigo === value)?.nombre || value;
</script>

<template>
    <AppLayout title="Combustibles / Tasas">
        <Head title="Combustibles / Tasas" />
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Combustibles / Tasas por litro</h2>
                <Link :href="route('compras.combustibles.index')" class="text-sm text-indigo-600 hover:text-indigo-800">Volver a pagos a cuenta</Link>
            </div>
        </template>
        <div class="max-w-5xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-base font-semibold text-gray-900">Tipos de combustible</h3>
                <form class="mt-3 flex flex-col gap-2 sm:flex-row" @submit.prevent="submitTipo">
                    <div class="flex-1">
                        <TextInput v-model="tipoForm.nombre" type="text" maxlength="120" class="block w-full" placeholder="Nuevo tipo de combustible" />
                        <p v-if="tipoForm.errors.nombre" class="mt-1 text-sm text-red-600">{{ tipoForm.errors.nombre }}</p>
                    </div>
                    <PrimaryButton :disabled="tipoForm.processing">Agregar tipo</PrimaryButton>
                </form>
                <div class="mt-3 divide-y divide-gray-100">
                    <div v-for="tipo in tiposCombustible" :key="tipo.id" class="flex items-center justify-between gap-3 py-2">
                        <form v-if="editandoTipoId === tipo.id" class="flex flex-1 items-start gap-2" @submit.prevent="guardarTipo">
                            <div class="flex-1">
                                <TextInput v-model="tipoEditForm.nombre" type="text" maxlength="120" class="block w-full" />
                                <p v-if="tipoEditForm.errors.nombre" class="mt-1 text-sm text-red-600">{{ tipoEditForm.errors.nombre }}</p>
                            </div>
                            <PrimaryButton :disabled="tipoEditForm.processing">Guardar</PrimaryButton>
                            <SecondaryButton type="button" @click="editandoTipoId = null">Cancelar</SecondaryButton>
                        </form>
                        <template v-else>
                            <span class="text-sm text-gray-800">{{ tipo.nombre }}</span>
                            <div class="flex gap-2">
                                <button type="button" class="text-sm text-indigo-600 hover:text-indigo-800" @click="editarTipo(tipo)">Editar</button>
                                <button type="button" class="text-sm text-red-600 hover:text-red-800" @click="eliminarTipo(tipo)">Eliminar</button>
                            </div>
                        </template>
                    </div>
                    <p v-if="!tiposCombustible?.length" class="py-3 text-sm text-gray-500">Todavía no hay tipos cargados.</p>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-base font-semibold text-gray-900">Nueva tasa</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Combustible" />
                        <select v-model="form.combustible_tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option v-for="t in tiposCombustible" :key="t.codigo" :value="t.codigo">{{ t.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Mes" />
                        <TextInput v-model="form.mes" type="month" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Monto por litro ($)" />
                        <TextInput v-model="form.monto_por_litro" type="number" min="0" step="0.0001" class="mt-1 block w-full" />
                    </div>
                    <div class="flex items-end">
                        <PrimaryButton :disabled="form.processing">Guardar</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Tasas cargadas</h3></div>
                <div v-for="(tasas, mes) in tasasPorMes" :key="mes" class="border-b border-gray-100 last:border-b-0">
                    <div class="px-6 py-3 bg-gray-50 text-sm font-semibold text-gray-700">{{ mes }}</div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th><th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Monto x litro</th><th class="px-6 py-2 text-right text-xs font-medium text-gray-500 uppercase">Accion</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="t in tasas" :key="t.id">
                                <td class="px-6 py-3 text-sm text-gray-700">{{ tipoLabel(t.combustible_tipo) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-700">$ {{ t.monto_por_litro }}</td>
                                <td class="px-6 py-3 text-right"><button type="button" class="text-sm text-red-600 hover:text-red-800" @click="eliminar(t.id)">Eliminar</button></td>
                            </tr>
                            <tr v-if="!tasas.length"><td colspan="3" class="px-6 py-4 text-sm text-gray-500 text-center">Sin tasas cargadas</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!Object.keys(tasasPorMes).length" class="p-6 text-sm text-gray-500 text-center">No hay tasas cargadas todavia.</div>
            </div>
        </div>
    </AppLayout>
</template>
