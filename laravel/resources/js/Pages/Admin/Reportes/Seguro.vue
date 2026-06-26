<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    rows: Array,
    totalGeneral: Number,
    mes: Number,
    anio: Number,
    mesNombre: String,
});

const formatNum = (n) => {
    const val = Number(n || 0);
    return '$ ' + val.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const cambiarMes = (dir) => {
    const m = props.mes + dir;
    let anio = props.anio;
    let mes = m;
    if (mes < 1) { mes = 12; anio--; }
    if (mes > 12) { mes = 1; anio++; }
    router.get(route('admin.reportes.seguro', { mes, anio }));
};
</script>

<template>
    <AppLayout title="Informe seguro">
        <Head title="Informe seguro" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Informe para seguro</h2>
                <div class="flex items-center gap-2">
                    <button @click="cambiarMes(-1)" class="px-3 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50">&larr; Mes anterior</button>
                    <span class="text-sm font-medium">{{ mesNombre }} {{ anio }}</span>
                    <button @click="cambiarMes(1)" class="px-3 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50">Mes siguiente &rarr;</button>
                    <button @click="window.print()" class="px-3 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">Imprimir</button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div v-if="!rows.length" class="px-6 py-10 text-center text-sm text-gray-500">
                    No hay movimientos en {{ mesNombre }} {{ anio }}.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Móvil</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Patente</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Acoplado</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Chofer</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Depósitos origen</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider">Viajes</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider">Cargas</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor declarado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="r in rows" :key="r.nummovil" class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-900">{{ r.nummovil }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ r.desmovil || '-' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">{{ r.patmovil || '-' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">{{ r.pacmovil || '-' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ r.nomchof || '-' }}</td>
                                <td class="px-3 py-2 text-gray-700 max-w-[200px]">{{ r.depositos_origen || '-' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-center text-gray-700">{{ r.total_viajes }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-center text-gray-700">{{ r.total_cargas }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-right font-mono text-gray-700">{{ formatNum(r.total_valor_declarado) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td colspan="8" class="px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Total general</td>
                                <td class="px-3 py-2 text-right text-xs font-mono font-semibold text-gray-900">{{ formatNum(totalGeneral) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="mt-4 text-xs text-gray-500 text-center print:hidden">
                Los datos corresponden al período {{ mesNombre }} {{ anio }}. Fuente: base de datos externa.
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    body { font-size: 10px; }
    .sm\:px-6 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
}
</style>
