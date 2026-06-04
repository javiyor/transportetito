<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const TIPOS = [
    { value: 'gasoil_grado_2', label: 'Gasoil Grado 2' },
    { value: 'gasoil_grado_3', label: 'Gasoil Grado 3' },
    { value: 'nafta_super', label: 'Nafta Súper' },
    { value: 'nafta_premium', label: 'Nafta Premium' },
    { value: 'kerosene', label: 'Kerosene' },
    { value: 'fuel_oil', label: 'Fuel Oil' },
];

const props = defineProps({
    tasasPorMes: Object,
});

const form = useForm({
    combustible_tipo: 'gasoil_grado_2',
    mes: new Date().toISOString().slice(0, 7),
    monto_por_litro: '',
});

const submit = () => {
    form.post(route('compras.combustibles.tasas.store'), {
        preserveScroll: true,
        onSuccess: () => { form.monto_por_litro = ''; },
    });
};

const eliminar = async (id) => {
    if (!confirm('Eliminar esta tasa?')) return;
    await fetch(route('compras.combustibles.tasas.destroy', id), { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    window.location.reload();
};

const tipoLabel = (value) => TIPOS.find((t) => t.value === value)?.label || value;
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
        <div class="max-w-5xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nueva tasa</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Combustible" />
                        <select v-model="form.combustible_tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option v-for="t in TIPOS" :key="t.value" :value="t.value">{{ t.label }}</option>
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
