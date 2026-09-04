<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    empresa: Object,
    topVisits: Array,
    alertas: Object,
});

const formatCuit = (value) => {
    if (!value) return '-';
    const s = String(value);
    if (s.includes('-')) return s;
    if (s.length === 11) return `${s.slice(0, 2)}-${s.slice(2, 10)}-${s.slice(10)}`;
    return s;
};
</script>

<template>
    <AppLayout title="Inicio">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Inicio / Empresa</h2>
        </template>

        <div class="py-4">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-3">
                <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                    <div class="p-6 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-4">
                            <img src="/brand/logo.jpeg" alt="TransporteTito" class="h-12 w-auto" />
                            <div>
                                <div class="text-xl font-semibold text-gray-900">
                                    {{ empresa?.razon_social || 'Sin empresa configurada' }}
                                </div>
                                <div class="mt-1 text-sm text-gray-600">
                                    CUIT {{ formatCuit(empresa?.cuit) }}
                                    <span v-if="empresa?.condicion_iva?.nombre">· {{ empresa.condicion_iva.nombre }}</span><span v-else-if="empresa?.condicion_iva">· {{ empresa.condicion_iva }}</span>
                                    <span v-if="empresa?.arca_pv_default">· PV {{ empresa.arca_pv_default }}</span>
                                    <span v-if="empresa?.arca_env">· ARCA {{ empresa.arca_env }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:flex items-center gap-2">
                            <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('operacion.manifiestos.index')">Operacion</a>
                            <span class="text-gray-300">|</span>
                            <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('admin.users.index')" v-if="($page.props.tt?.roles || []).includes('admin')">Admin</a>
                        </div>
                    </div>
                </div>

                <div v-if="topVisits?.length" class="bg-white shadow sm:rounded-lg p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Accesos frecuentes (Top 10)</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                        <Link v-for="v in topVisits" :key="v.route" :href="'/' + v.path" class="block rounded-lg border p-3 hover:bg-gray-50 transition-colors" :class="(alertas?.cotizaciones_pendientes > 0 && v.route === 'facturacion.cotizaciones.pendientes') || (alertas?.vehiculos_vencimientos > 0 && v.route === 'admin.vehiculos.index') ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white'">
                            <div class="text-sm font-medium text-gray-900 truncate">{{ v.title }}</div>
                            <div class="text-xs text-gray-500">{{ v.visits }} visitas</div>
                            <div v-if="v.route === 'facturacion.cotizaciones.pendientes' && alertas?.cotizaciones_pendientes > 0" class="mt-1 text-xs font-bold text-amber-700">{{ alertas.cotizaciones_pendientes }} pendientes</div>
                            <div v-if="v.route === 'admin.vehiculos.index' && alertas?.vehiculos_vencimientos > 0" class="mt-1 text-xs font-bold text-amber-700">{{ alertas.vehiculos_vencimientos }} vencimientos</div>
                        </Link>
                    </div>
                </div>

                <div v-if="alertas && (alertas.cotizaciones_pendientes > 0 || alertas.vehiculos_vencimientos > 0)" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div v-if="alertas.cotizaciones_pendientes > 0" class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <div class="text-sm font-semibold text-amber-800">Cotizaciones pendientes</div>
                        <div class="text-2xl font-bold text-amber-900">{{ alertas.cotizaciones_pendientes }}</div>
                        <Link :href="route('facturacion.cotizaciones.pendientes')" class="text-xs text-amber-700 hover:text-amber-900 underline">Ver pendientes</Link>
                    </div>
                    <div v-if="alertas.vehiculos_vencimientos > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="text-sm font-semibold text-red-800">Vencimientos vehículos</div>
                        <div class="text-2xl font-bold text-red-900">{{ alertas.vehiculos_vencimientos }}</div>
                        <Link :href="route('admin.vehiculos.index')" class="text-xs text-red-700 hover:text-red-900 underline">Ver vehículos</Link>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-base font-semibold text-gray-900">Depositos</h3>
                            <p class="mt-1 text-sm text-gray-600">Puntos operativos y PV asociado.</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Direccion</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PV</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="d in (empresa?.depositos || [])" :key="d.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ d.nombre }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ d.direccion || '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ d.punto_venta_numero ?? '-' }}</td>
                                    </tr>
                                    <tr v-if="!(empresa?.depositos || []).length">
                                        <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">Sin depositos cargados.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
