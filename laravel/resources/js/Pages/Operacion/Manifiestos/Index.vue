<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    manifiestos: Object,
});

const page = usePage();
const canRepartos = computed(() => (page.props.tt?.roles || []).includes('operaciones'));

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
                    <Link v-if="canRepartos" :href="route('operacion.repartos.facturas')">
                        <SecondaryButton>Repartos</SecondaryButton>
                    </Link>
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
                <div class="p-6 border-b border-gray-200">
                    <p class="text-sm text-gray-600">Ingreso de camion completo + pedidos por destinatario.</p>
                </div>

                <div class="space-y-4 p-4 sm:hidden">
                    <div v-for="m in manifiestos.data" :key="m.id" class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ formatFecha(m.fecha) }}</div>
                                <div class="text-xs text-gray-500">{{ m.empresa?.razon_social || '-' }}</div>
                            </div>
                            <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('operacion.manifiestos.show', m.id)">Ver</Link>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 text-sm">
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
                    <div v-if="!manifiestos.data.length" class="rounded-lg border border-gray-200 bg-white px-6 py-10 text-center text-sm text-gray-500">Sin manifiestos todavia.</div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatFecha(m.fecha) }}</td>
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
