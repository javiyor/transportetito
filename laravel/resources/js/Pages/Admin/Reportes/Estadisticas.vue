<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout title="Estadisticas">
        <Head title="Estadisticas" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Estadisticas</h2>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">Proximamente: graficos y balance.</p>
            </div>
        </div>
    </AppLayout>
</template>
