<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    defaults: Object,
    empresa: Object,
    depositos: Array,
});

const page = usePage();

const form = useForm({
    since: props.defaults?.since || '',
    deposito_id: props.depositos?.[0]?.id || '',
});

const importResult = computed(() => page.props.tt?.flash?.importResult || null);
const importError = computed(() => page.props.tt?.flash?.importError || null);

const submit = () => {
    form.post(route('operacion.import.carga.store'));
};
</script>

<template>
    <AppLayout title="Operacion / Importar">
        <Head title="Operacion / Importar" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Importar manifiestos</h2>
                    <div class="mt-1 text-sm text-gray-600">Trae carga desde MySQL externo y crea manifiestos + pedidos.</div>
                </div>
                <Link :href="route('operacion.manifiestos.index')">
                    <SecondaryButton>Volver</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div v-if="importError" class="bg-red-50 border border-red-200 text-red-900 rounded-lg p-4">
                <div class="font-medium">No se pudo importar</div>
                <div class="mt-1 text-sm font-mono break-all">{{ importError }}</div>
            </div>

            <div v-if="importResult" class="bg-green-50 border border-green-200 text-green-900 rounded-lg p-4">
                <div class="font-medium">Importacion finalizada</div>
                <div class="mt-1 text-sm">
                    Desde {{ importResult.since }}: {{ importResult.created }} creados, {{ importResult.skipped }} salteados ({{ importResult.total }} leidos).
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <form class="grid grid-cols-1 sm:grid-cols-3 gap-4" @submit.prevent="submit">
                    <div class="sm:col-span-3">
                        <InputLabel value="Empresa seleccionada" />
                        <div class="mt-1 text-sm text-gray-700">{{ empresa?.razon_social || '-' }}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="since" value="Importar desde (fecha)" />
                        <TextInput id="since" v-model="form.since" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.since" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel for="deposito_id" value="Deposito de origen" />
                        <select id="deposito_id" v-model="form.deposito_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="" disabled>(seleccionar)</option>
                            <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nombre }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.deposito_id" />
                    </div>

                    <div class="flex items-end justify-end">
                        <PrimaryButton :disabled="form.processing">Importar</PrimaryButton>
                    </div>
                </form>

                <div class="mt-4 text-sm text-gray-600">
                    No duplica: usa <span class="font-mono">carga.id</span> como <span class="font-mono">external_carga_id</span>.
                </div>
                <div class="mt-2 text-sm text-gray-600">
                    El deposito seleccionado se usa como deposito de origen. El deposito destino se asigna automaticamente al deposito central de la empresa.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
