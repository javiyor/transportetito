<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    gastos: Object,
    totales: Object,
});

const form = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    categoria: '',
    moneda: 'ARS',
    importe: '',
    referencia: '',
    observacion: '',
});

const submit = () => form.post(route('compras.gastos.store'), { preserveScroll: true });
</script>

<template>
    <AppLayout title="Compras / Gastos sin proveedor">
        <Head title="Compras / Gastos sin proveedor" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compras / Gastos sin proveedor</h2>
                <div class="flex items-center gap-3">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.gastos.export')">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.combustibles.index')">Combustibles</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.index')">Volver a compras</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><div class="text-xs text-gray-500">Registros</div><div class="text-sm font-medium text-gray-900">{{ totales?.cantidad || 0 }}</div></div>
                <div><div class="text-xs text-gray-500">Total estimado en ARS</div><div class="text-sm font-medium text-gray-900">{{ totales?.importe_total_ars || 0 }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nuevo gasto</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4" @submit.prevent="submit">
                    <div><InputLabel value="Fecha" /><TextInput v-model="form.fecha" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.fecha" /></div>
                    <div><InputLabel value="Categoria" /><TextInput v-model="form.categoria" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.categoria" /></div>
                    <div><InputLabel value="Moneda" /><select v-model="form.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-2" :message="form.errors.moneda" /></div>
                    <div><InputLabel value="Importe" /><TextInput v-model="form.importe" type="number" min="0.01" step="0.01" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.importe" /></div>
                    <div><InputLabel value="Referencia" /><TextInput v-model="form.referencia" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.referencia" /></div>
                    <div class="sm:col-span-3"><InputLabel value="Observacion" /><TextInput v-model="form.observacion" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.observacion" /></div>
                    <div class="sm:col-span-3 flex justify-end"><PrimaryButton :disabled="form.processing">Guardar</PrimaryButton></div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Gastos registrados</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referencia</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="g in gastos.data" :key="g.id"><td class="px-6 py-4 text-sm text-gray-700">{{ String(g.fecha || '').slice(0,10) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.categoria }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.referencia || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.moneda }} {{ g.importe }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.observacion || '-' }}</td></tr></tbody></table></div>
            </div>
        </div>
    </AppLayout>
</template>
