<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    manifiestos: Object,
});
</script>

<template>
    <AppLayout title="Operacion / Manifiestos">
        <Head title="Operacion / Manifiestos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Manifiestos</h2>
                <Link :href="route('operacion.manifiestos.create')">
                    <PrimaryButton>Nuevo manifiesto</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <p class="text-sm text-gray-600">Ingreso de camion completo + pedidos por destinatario.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deposito</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transporte</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="m in manifiestos.data" :key="m.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ m.fecha }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ m.empresa?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ m.deposito?.nombre || '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ m.transporte || '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <Link class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.manifiestos.show', m.id)">
                                        Ver
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="!manifiestos.data.length">
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">Sin manifiestos todavia.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="manifiestos.links?.length" class="p-6 border-t border-gray-200 flex flex-wrap gap-2">
                    <Link
                        v-for="link in manifiestos.links"
                        :key="link.label"
                        class="px-3 py-2 text-sm rounded border"
                        :class="link.active ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'"
                        :href="link.url || ''"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
