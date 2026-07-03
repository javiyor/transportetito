<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
    tipo: String,
    titulo: String,
    descripcion: String,
    tablas: Array,
    empresas: Array,
});

const empresaId = ref('');
const confirmando = ref(false);
const procesando = ref(false);

const ejecutar = () => {
    procesando.value = true;
    router.post(route('admin.blanqueo.ejecutar'), { tipo: props.tipo, empresa_id: empresaId.value }, {
        preserveScroll: true,
        onFinish: () => {
            procesando.value = false;
            confirmando.value = false;
        },
    });
};
</script>

<template>
    <AppLayout :title="titulo">
        <Head :title="titulo" />

        <template #header>
            <h2 class="font-semibold text-lg text-gray-800 leading-tight">{{ titulo }}</h2>
        </template>

        <div class="max-w-3xl mx-auto py-6 sm:px-4 lg:px-6 space-y-4">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <p class="text-sm text-gray-600 mb-4">{{ descripcion }}</p>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                    <select v-model="empresaId" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                        <option value="">Seleccionar empresa...</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                    </select>
                </div>

                <div class="mb-4">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Tablas afectadas:</h4>
                    <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                        <li v-for="t in tablas" :key="t">{{ t }}</li>
                    </ul>
                </div>

                <div v-if="confirmando" class="border border-red-300 bg-red-50 rounded-lg p-4 mb-4">
                    <p class="text-sm font-medium text-red-800 mb-3">Esta accion es irreversible. Confirma que deseas eliminar todos los datos de la empresa seleccionada?</p>
                    <div class="flex gap-2">
                        <PrimaryButton class="!bg-red-600 hover:!bg-red-700" :disabled="procesando" @click="ejecutar">{{ procesando ? 'Blanqueando...' : 'Confirmar blanqueo' }}</PrimaryButton>
                        <SecondaryButton :disabled="procesando" @click="confirmando = false">Cancelar</SecondaryButton>
                    </div>
                </div>

                <div v-else>
                    <PrimaryButton class="!bg-red-600 hover:!bg-red-700" :disabled="!empresaId" @click="confirmando = true">Blanquear</PrimaryButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
