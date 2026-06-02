<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    empresas: Array,
    empresaId: [Number, null],
    fecha: String,
    monedas: Array,
    resueltas: Array,
    cotizaciones: Array,
    overrides: Array,
});

const filtros = useForm({
    empresa_id: props.empresaId || props.empresas?.[0]?.id || null,
    fecha: props.fecha,
});

const aplicarFiltros = () => {
    router.get(route('admin.cotizaciones.index'), filtros.data(), { preserveState: true, preserveScroll: true, replace: true });
};

const oficialForm = useForm({ fecha: props.fecha, moneda: 'USD', tasa_ars: '', fuente: 'manual' });
const overrideForm = useForm({ empresa_id: props.empresaId || props.empresas?.[0]?.id || null, fecha: props.fecha, moneda: 'USD', tasa_ars: '', motivo: '' });
</script>

<template>
    <AppLayout title="Admin / Cotizaciones">
        <Head title="Admin / Cotizaciones" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Cotizaciones</h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <InputLabel value="Empresa" />
                    <select v-model="filtros.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Fecha" />
                    <TextInput v-model="filtros.fecha" type="date" class="mt-1 block w-full" />
                </div>
                <div class="flex items-end">
                    <PrimaryButton @click="aplicarFiltros">Consultar</PrimaryButton>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Cotizacion efectiva</h3>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div v-for="r in resueltas" :key="r.moneda" class="rounded-lg border border-gray-200 p-4">
                        <div class="text-xs text-gray-500 uppercase">{{ r.moneda }}</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">{{ r.tasa_ars ?? '-' }}</div>
                        <div class="text-xs text-gray-500">{{ r.fuente }} · {{ r.fecha }}</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Cargar cotizacion oficial/manual</h3>
                    <form class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="oficialForm.post(route('admin.cotizaciones.oficial.store'), { preserveScroll: true })">
                        <div><InputLabel value="Fecha" /><TextInput v-model="oficialForm.fecha" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="oficialForm.errors.fecha" /></div>
                        <div><InputLabel value="Moneda" /><select v-model="oficialForm.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option v-for="m in monedas" :key="m" :value="m">{{ m }}</option></select><InputError class="mt-2" :message="oficialForm.errors.moneda" /></div>
                        <div><InputLabel value="Tasa en ARS" /><TextInput v-model="oficialForm.tasa_ars" type="number" min="0.000001" step="0.000001" class="mt-1 block w-full" /><InputError class="mt-2" :message="oficialForm.errors.tasa_ars" /></div>
                        <div><InputLabel value="Fuente" /><TextInput v-model="oficialForm.fuente" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="oficialForm.errors.fuente" /></div>
                        <div class="sm:col-span-2 flex justify-end"><PrimaryButton :disabled="oficialForm.processing">Guardar</PrimaryButton></div>
                    </form>
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Override por empresa</h3>
                    <form class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="overrideForm.post(route('admin.cotizaciones.override.store'), { preserveScroll: true })">
                        <div><InputLabel value="Empresa" /><select v-model="overrideForm.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option></select><InputError class="mt-2" :message="overrideForm.errors.empresa_id" /></div>
                        <div><InputLabel value="Fecha" /><TextInput v-model="overrideForm.fecha" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="overrideForm.errors.fecha" /></div>
                        <div><InputLabel value="Moneda" /><select v-model="overrideForm.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option v-for="m in monedas" :key="m" :value="m">{{ m }}</option></select><InputError class="mt-2" :message="overrideForm.errors.moneda" /></div>
                        <div><InputLabel value="Tasa en ARS" /><TextInput v-model="overrideForm.tasa_ars" type="number" min="0.000001" step="0.000001" class="mt-1 block w-full" /><InputError class="mt-2" :message="overrideForm.errors.tasa_ars" /></div>
                        <div class="sm:col-span-2"><InputLabel value="Motivo" /><TextInput v-model="overrideForm.motivo" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="overrideForm.errors.motivo" /></div>
                        <div class="sm:col-span-2 flex justify-end"><PrimaryButton :disabled="overrideForm.processing">Guardar</PrimaryButton></div>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Ultimas cotizaciones</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tasa</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuente</th></tr></thead>
                            <tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in cotizaciones" :key="`${c.id}`"><td class="px-6 py-4 text-sm text-gray-700">{{ String(c.fecha).slice(0,10) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.tasa_ars }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.fuente }}</td></tr></tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Overrides por empresa</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tasa</th></tr></thead>
                            <tbody class="bg-white divide-y divide-gray-200"><tr v-for="o in overrides" :key="`${o.id}`"><td class="px-6 py-4 text-sm text-gray-700">{{ o.empresa?.razon_social || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ String(o.fecha).slice(0,10) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ o.moneda }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ o.tasa_ars }}</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
