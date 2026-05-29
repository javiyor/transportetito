<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    empresa: Object,
    contacts: Array,
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

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                    <div class="p-6 flex items-center justify-between gap-6">
                        <div class="flex items-center gap-4">
                            <img src="/brand/logo.png" alt="TransporteTito" class="h-12 w-auto" />
                            <div>
                                <div class="text-xl font-semibold text-gray-900">
                                    {{ empresa?.razon_social || 'Sin empresa configurada' }}
                                </div>
                                <div class="mt-1 text-sm text-gray-600">
                                    CUIT {{ formatCuit(empresa?.cuit) }}
                                    <span v-if="empresa?.condicion_iva">· {{ empresa.condicion_iva }}</span>
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

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white shadow sm:rounded-lg overflow-hidden">
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
                                        <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">Sin depositos cargados.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-base font-semibold text-gray-900">Contactos</h3>
                            <p class="mt-1 text-sm text-gray-600">Usuarios habilitados en el sistema.</p>
                        </div>

                        <div class="divide-y divide-gray-200">
                            <div v-for="c in (contacts || [])" :key="c.id" class="p-6">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ c.name }}</div>
                                        <div class="mt-1 text-sm text-gray-600">{{ c.email }}</div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span v-for="r in (c.roles || [])" :key="r" class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">{{ r }}</span>
                                            <span v-if="c.blocked_at" class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Bloqueado</span>
                                            <span v-if="c.has_2fa" class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">2FA</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="!(contacts || []).length" class="p-6 text-center text-sm text-gray-500">
                                Sin contactos cargados.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
