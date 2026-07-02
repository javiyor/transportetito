<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    manifiestos: Object,
    compartidos: { type: String, default: '1' },
});

const toggleCompartidos = () => {
    router.get(route('operacion.manifiestos.index'), {
        compartidos: props.compartidos === '1' ? '0' : '1',
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};
</script>

<template>
    <AppLayout title="Control de pedidos / Manifiestos">
        <Head title="Control de pedidos / Manifiestos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control de pedidos / Manifiestos</h2>
                <div class="flex flex-wrap items-center gap-2 justify-end">
                    <Link :href="route('operacion.import.carga.index')">
                        <SecondaryButton>Importar</SecondaryButton>
                    </Link>
                    <Link :href="route('operacion.manifiestos.create')">
                        <PrimaryButton>Nuevo manifiesto</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-4">
            <div class="flex items-center gap-2">
                <button @click="toggleCompartidos"
                    class="text-xs px-3 py-1.5 rounded border font-medium transition-colors"
                    :class="compartidos === '1' ? 'bg-indigo-100 text-indigo-700 border-indigo-300' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
                    {{ compartidos === '1' ? 'Mostrando datos compartidos' : 'Solo esta empresa' }}
                </button>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <p class="text-xs text-gray-600">Ingreso de camion completo + pedidos por destinatario.</p>
                </div>

                <div v-if="!manifiestos.data.length" class="px-6 py-10 text-center text-sm text-gray-500">
                    Sin manifiestos todavia.
                </div>

                <div class="sm:hidden space-y-3 p-3">
                    <div v-for="m in manifiestos.data" :key="m.id" class="rounded-lg border border-gray-200 bg-white p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ formatFecha(m.fecha) }}</div>
                                <div class="text-xs text-gray-500">{{ m.chofer || '-' }}</div>
                                <div class="text-xs text-gray-500">{{ m.deposito?.nombre || '-' }}</div>
                            </div>
                            <Link class="text-xs text-indigo-600 hover:text-indigo-800" :href="route('operacion.manifiestos.show', m.id)">Ver</Link>
                        </div>
                    </div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chofer</th>
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deposito</th>
                                <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="m in manifiestos.data" :key="m.id" class="hover:bg-gray-50">
                                <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-900">{{ formatFecha(m.fecha) }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-700">{{ m.chofer || '-' }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-700">{{ m.deposito?.nombre || '-' }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-right text-xs">
                                    <Link class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.manifiestos.show', m.id)">
                                        Ver
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="manifiestos.links?.length" class="p-3 border-t border-gray-200 flex flex-wrap gap-2">
                    <Link
                        v-for="link in manifiestos.links"
                        :key="link.label"
                        class="px-2 py-1 text-xs rounded border"
                        :class="link.active ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'"
                        :href="link.url || ''"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
