<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref, onMounted } from 'vue';

const eliminar = (m) => {
    if (confirm('¿Eliminar manifiesto? Los pedidos se desasignarán.')) {
        router.delete(route('operacion.manifiestos.destroy', m.id), { preserveScroll: true });
    }
};

const props = defineProps({
    manifiestos: Object,
    orden: { type: String, default: 'desc' },
});

const toggleOrden = () => {
    router.get(route('operacion.manifiestos.index'), {
        orden: props.orden === 'asc' ? 'desc' : 'asc',
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const goToPage = (page) => {
    if (!page || page < 1 || page > props.manifiestos.last_page) return;
    router.get(route('operacion.manifiestos.index'), {
        orden: props.orden || 'desc',
        page,
    }, { preserveState: true, preserveScroll: true });
};

const traducirLabel = (label) => {
    if (!label) return '';
    let l = label.replace(/&laquo;/g, '').replace(/&raquo;/g, '').trim();
    if (l.toLowerCase() === 'previous') return 'Anterior';
    if (l.toLowerCase() === 'next') return 'Siguiente';
    return l;
};

const formatFecha = (value) => {
    if (!value) return '-';
    const d = new Date(String(value).slice(0, 10));
    return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
};

const autoImportando = ref(false);
onMounted(() => {
    if (props.compartidos === '1' && props.orden === 'desc' && props.manifiestos.current_page === 1) {
        autoImportando.value = true;
        router.post(route('operacion.manifiestos.import-auto'), {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                router.reload({ only: ['manifiestos'] });
            },
            onFinish: () => { autoImportando.value = false; },
        });
    }
});
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

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-4">
            <div v-if="autoImportando" class="bg-blue-50 border border-blue-200 text-blue-800 px-3 py-2 rounded text-xs text-center">
                Importando nuevos manifiestos de todos los depósitos...
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500">Mostrando todos los manifiestos de todas las empresas</span>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <p class="text-xs text-gray-600">Ingreso de camion completo + pedidos por destinatario.</p>
                </div>

                <div v-if="!manifiestos.data.length" class="px-6 py-4 text-center text-sm text-gray-500">
                    Sin manifiestos todavia.
                </div>

                <div class="sm:hidden space-y-3 p-3">
                    <div v-for="m in manifiestos.data" :key="m.id" class="rounded-lg border p-3" :class="(m.pedidos_count !== undefined ? m.pedidos_count : 0) > 0 && (m.pedidos_con_error_count === 0) ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ formatFecha(m.fecha) }}</div>
                                <div class="text-xs text-gray-500">{{ m.empresa?.razon_social || '-' }} · {{ m.chofer || '-' }}</div>
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
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleOrden" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Fecha
                                        <span class="text-[10px]">{{ orden === 'asc' ? '▲' : '▼' }}</span>
                                    </button>
                                </th>
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chofer</th>
                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deposito</th>
                                <th class="px-3 py-1.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="m in manifiestos.data" :key="m.id" :class="(m.pedidos_count !== undefined ? m.pedidos_count : 0) > 0 && (m.pedidos_con_error_count === 0) ? 'bg-green-50 hover:bg-green-100' : 'hover:bg-gray-50'">
                                <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-900">{{ formatFecha(m.fecha) }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-700">{{ m.empresa?.razon_social || '-' }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-700">{{ m.chofer || '-' }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-xs text-gray-700">{{ m.deposito?.nombre || '-' }}</td>
                                <td class="px-3 py-1.5 whitespace-nowrap text-right text-xs space-x-2">
                                    <Link class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.manifiestos.show', m.id)">Ver</Link>
                                    <button class="text-red-500 hover:text-red-700" @click="eliminar(m)">Eliminar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="manifiestos.last_page > 1" class="p-3 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                    <span class="text-gray-500">Pág. {{ manifiestos.current_page }} de {{ manifiestos.last_page }} ({{ manifiestos.total }} manifiestos)</span>
                    <div class="flex items-center gap-1 flex-wrap">
                        <button :disabled="manifiestos.current_page <= 1" @click="goToPage(manifiestos.current_page - 1)" class="px-3 py-1 bg-white border rounded-md hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Anterior</button>
                        <button
                            v-for="p in manifiestos.last_page"
                            :key="p"
                            @click="goToPage(p)"
                            :class="p === manifiestos.current_page ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'"
                            class="px-3 py-1 border rounded-md min-w-[36px]"
                        >{{ p }}</button>
                        <button :disabled="manifiestos.current_page >= manifiestos.last_page" @click="goToPage(manifiestos.current_page + 1)" class="px-3 py-1 bg-white border rounded-md hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Siguiente</button>
                    </div>
                </div>
                <div v-else-if="manifiestos.links?.length" class="p-3 border-t border-gray-200 flex flex-wrap gap-2">
                    <span
                        v-for="link in manifiestos.links"
                        :key="link.label"
                        class="px-2 py-1 text-xs rounded border"
                        :class="link.active ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-700 border-gray-200'"
                        v-html="traducirLabel(link.label)"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
