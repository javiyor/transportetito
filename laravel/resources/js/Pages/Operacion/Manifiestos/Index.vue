<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    manifiestos: Object,
});

const depositoGroups = computed(() => {
    const groups = {};
    for (const m of props.manifiestos.data) {
        const key = m.deposito?.nombre || 'Sin depósito';
        if (!groups[key]) groups[key] = [];
        groups[key].push(m);
    }
    return Object.entries(groups);
});

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

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <p class="text-xs text-gray-600">Ingreso de camion completo + pedidos por destinatario.</p>
                </div>

                <div v-if="!manifiestos.data.length" class="px-6 py-10 text-center text-sm text-gray-500">
                    Sin manifiestos todavia.
                </div>

                <template v-for="[depositoNombre, items] in depositoGroups" :key="depositoNombre">
                    <div class="sm:hidden">
                        <div class="px-4 py-2 bg-gray-100 border-b border-gray-200 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            {{ depositoNombre }}
                        </div>
                        <div class="space-y-3 p-3">
                            <div v-for="m in items" :key="m.id" class="rounded-lg border border-gray-200 bg-white p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">{{ formatFecha(m.fecha) }}</div>
                                        <div class="text-xs text-gray-500">{{ m.chofer || '-' }}</div>
                                    </div>
                                    <Link class="text-xs text-indigo-600 hover:text-indigo-800" :href="route('operacion.manifiestos.show', m.id)">Ver</Link>
                                </div>
                                <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <div class="text-xs uppercase tracking-wider text-gray-500">Deposito</div>
                                        <div class="font-medium text-gray-900">{{ m.deposito?.nombre || '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase tracking-wider text-gray-500">Transporte</div>
                                        <div class="font-medium text-gray-900">{{ m.transporte || '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hidden sm:block">
                        <div class="px-4 py-1.5 bg-gray-100 border-b border-gray-200 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            {{ depositoNombre }}
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chofer</th>
                                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deposito</th>
                                    <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transporte</th>
                                    <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="m in items" :key="m.id" class="hover:bg-gray-50">
                                    <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-900">{{ formatFecha(m.fecha) }}</td>
                                    <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-700">{{ m.chofer || '-' }}</td>
                                    <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-700">{{ m.deposito?.nombre || '-' }}</td>
                                    <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-700">{{ m.transporte || '-' }}</td>
                                    <td class="px-3 py-1.5 whitespace-nowrap text-right text-xs">
                                        <Link class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.manifiestos.show', m.id)">
                                            Ver
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

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
