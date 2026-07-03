<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
    tipo: String,
    titulo: String,
    descripcion: String,
    tablas: Array,
});

const confirmando = ref(false);
const procesando = ref(false);
const flash = usePage().props.flash;

const ejecutar = () => {
    procesando.value = true;
    router.post(route('admin.blanqueo.ejecutar'), { tipo: props.tipo }, {
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
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Tablas afectadas:</h4>
                    <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                        <li v-for="t in tablas" :key="t">{{ t }}</li>
                    </ul>
                </div>

                <div v-if="confirmando" class="border border-red-300 bg-red-50 rounded-lg p-4 mb-4">
                    <p class="text-sm font-medium text-red-800 mb-3">Esta accion es irreversible. Confirma que deseas eliminar todos los datos?</p>
                    <div class="flex gap-2">
                        <PrimaryButton class="!bg-red-600 hover:!bg-red-700" :disabled="procesando" @click="ejecutar">{{ procesando ? 'Blanqueando...' : 'Confirmar blanqueo' }}</PrimaryButton>
                        <SecondaryButton :disabled="procesando" @click="confirmando = false">Cancelar</SecondaryButton>
                    </div>
                </div>

                <div v-else>
                    <PrimaryButton class="!bg-red-600 hover:!bg-red-700" @click="confirmando = true">Blanquear</PrimaryButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
