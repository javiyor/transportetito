<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    zonas: Array,
    localidades: Array,
    empresas: Array,
    filters: Object,
    facturas: Array,
    vehiculos: Array,
    choferes: Array,
    depositos: Array,
});

const filterForm = useForm({
    zona_id: props.filters?.zona_id || '',
    localidad: props.filters?.localidad || '',
    fecha: props.filters?.fecha || '',
    tipo: props.filters?.tipo || 'todos',
    comprobante_ids: [],
});

const entregaSearch = ref('');

const filteredFacturas = computed(() => {
    if (!entregaSearch.value) return props.facturas;
    const q = entregaSearch.value.toLowerCase();
    return props.facturas.filter(f => {
        const rs = (f.entrega_cuenta?.tercero?.razon_social || '').toLowerCase();
        const cuit = (f.entrega_cuenta?.tercero?.cuit || '').toLowerCase();
        const id = String(f.id);
        return rs.includes(q) || cuit.includes(q) || id.includes(q);
    });
});

const createForm = useForm({
    deposito_id: '',
    fecha: '',
    vehiculo_id: '',
    zona_id: '',
    chofer_user_id: '',
    comprobante_ids: [],
});

const showCreateModal = ref(false);

const openCreateModal = () => {
    createForm.deposito_id = '';
    createForm.fecha = filterForm.fecha || new Date().toISOString().slice(0, 10);
    createForm.vehiculo_id = '';
    createForm.zona_id = '';
    createForm.chofer_user_id = '';
    createForm.comprobante_ids = [...filterForm.comprobante_ids];
    createForm.clearErrors();
    showCreateModal.value = true;
};

const submitCreate = () => {
    createForm.post(route('operacion.repartos.hojas.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
        },
    });
};

const applyFilters = () => {
    router.get(
        route('operacion.repartos.facturas'),
        { zona_id: filterForm.zona_id || null, localidad: filterForm.localidad || null, fecha: filterForm.fecha || null, tipo: filterForm.tipo || 'todos' },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const tipoLabel = (tipo) => {
    if (tipo === 'guia_envio') return 'Guia';
    if (tipo === 'factura_interna') return 'Factura';
    return tipo || '-';
};

const toggleAll = (checked) => {
    filterForm.comprobante_ids = checked ? props.facturas.map((f) => f.id) : [];
};

const ordenDe = (id) => {
    const idx = filterForm.comprobante_ids.indexOf(id);
    return idx >= 0 ? idx + 1 : null;
};
</script>

<template>
    <AppLayout title="Operacion / Repartos">
        <Head title="Operacion / Repartos" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Repartos</h2>
                <Link :href="route('operacion.manifiestos.index')">
                    <SecondaryButton>Volver</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-2">
            <div class="bg-white shadow sm:rounded-lg p-2">
                <div class="grid grid-cols-2 sm:grid-cols-6 gap-2">
                    <div>
                        <div class="text-[11px] font-medium text-gray-700 mb-0.5">Zona</div>
                        <select v-model="filterForm.zona_id" class="block w-full border-gray-300 rounded-md shadow-sm text-xs py-1">
                            <option value="">Todos</option>
                            <option v-for="z in zonas" :key="z.id" :value="z.id">{{ z.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-gray-700 mb-0.5">Ciudad</div>
                        <select v-model="filterForm.localidad" class="block w-full border-gray-300 rounded-md shadow-sm text-xs py-1">
                            <option value="">Todas</option>
                            <option v-for="loc in localidades" :key="loc" :value="loc">{{ loc }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-gray-700 mb-0.5">Fecha</div>
                        <input v-model="filterForm.fecha" type="date" class="block w-full border-gray-300 rounded-md shadow-sm text-xs py-1" />
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-gray-700 mb-0.5">Tipo</div>
                        <select v-model="filterForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-xs py-1">
                            <option value="todos">Todos</option>
                            <option value="factura_interna">Facturas</option>
                            <option value="guia_envio">Guias</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-gray-700 mb-0.5">Entrega</div>
                        <input v-model="entregaSearch" type="text" placeholder="CUIT/nombre/ID" class="block w-full border-gray-300 rounded-md shadow-sm text-xs py-1" />
                    </div>
                    <div class="flex items-end justify-end">
                        <SecondaryButton type="button" class="!text-xs !py-1" @click="applyFilters">Aplicar</SecondaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-2 border-b border-gray-200 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wider">Comprobantes emitidos (listas) — {{ filteredFacturas.length }} de {{ facturas.length }}</h3>
                        <p class="text-[11px] text-gray-500">Selecciona para armar hoja de ruta.</p>
                    </div>
                    <PrimaryButton class="!text-xs !py-1" :disabled="filterForm.processing || !filterForm.comprobante_ids.length" @click.prevent="openCreateModal">
                        Crear hoja
                    </PrimaryButton>
                </div>

                <div class="space-y-1 p-1 sm:hidden">
                    <div v-for="f in filteredFacturas" :key="f.id" class="rounded-lg border border-gray-200 bg-white p-1.5 relative">
                        <div v-if="ordenDe(f.id)" class="absolute -top-1 -left-1 bg-indigo-600 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center">{{ ordenDe(f.id) }}</div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">#{{ f.id }}</div>
                                <div class="text-xs text-gray-500">{{ tipoLabel(f.tipo) }}</div>
                                <div v-if="f.empresa" class="text-xs text-gray-400">{{ f.empresa.razon_social }}</div>
                            </div>
                            <Checkbox v-model:checked="filterForm.comprobante_ids" :value="f.id" />
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Entrega</div>
                                <div class="font-medium text-gray-900">{{ f.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                                <div class="text-xs text-gray-500">{{ f.entrega_cuenta?.localidad ? f.entrega_cuenta.localidad : '' }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Total</div>
                                    <div class="font-medium text-gray-900">{{ f.moneda }} {{ f.total }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Cotizacion</div>
                                    <div class="font-medium text-gray-900">{{ f.moneda === 'ARS' ? '-' : (f.detalle_facturacion?.calculo?.cotizacion?.tasa_ars || f.detalle_facturacion?.cotizacion?.tasa_ars || '-') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!facturas.length" class="rounded-lg border border-gray-200 bg-white px-6 py-4 text-center text-sm text-gray-500">No hay comprobantes para los filtros seleccionados.</div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full w-full divide-y divide-gray-200 text-[10px] leading-none">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" :checked="filterForm.comprobante_ids.length === filteredFacturas.length && filteredFacturas.length" @change="toggleAll($event.target.checked)" />
                                </th>
                                <th class="px-1 py-1 text-center text-[10px] font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                <th class="px-1 py-1 text-left text-[10px] font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="sticky right-0 bg-gray-50 px-1 py-1 text-right text-[10px] font-medium text-gray-500 uppercase tracking-wider">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="f in filteredFacturas" :key="f.id" class="hover:bg-gray-50 leading-none">
                                <td class="px-1 py-0.5 whitespace-nowrap">
                                    <Checkbox v-model:checked="filterForm.comprobante_ids" :value="f.id" />
                                </td>
                                <td class="px-1 py-0.5 whitespace-nowrap text-[10px] font-bold text-center" :class="ordenDe(f.id) ? 'text-indigo-700' : 'text-gray-300'">{{ ordenDe(f.id) || '-' }}</td>
                                <td class="px-1 py-0.5 whitespace-nowrap text-[10px] font-mono text-gray-900">{{ f.id }}</td>
                                <td class="px-1 py-0.5 whitespace-nowrap text-[10px] text-gray-700">{{ tipoLabel(f.tipo) }}</td>
                                <td class="px-1 py-0.5 text-[10px] text-gray-700">
                                    <div class="font-medium text-gray-900 truncate max-w-[200px] leading-none">{{ f.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                                    <div class="text-[9px] text-gray-500 leading-none">CUIT {{ f.entrega_cuenta?.tercero?.cuit || '-' }}</div>
                                </td>
                                <td class="px-1 py-0.5 whitespace-nowrap text-[10px] text-gray-700 font-mono">{{ f.moneda }} {{ f.total }}</td>
                                <td class="sticky right-0 bg-white px-1 py-0.5 whitespace-nowrap text-right text-[10px] text-gray-500">{{ ordenDe(f.id) ? '#' + ordenDe(f.id) : 'Seleccionar' }}</td>
                            </tr>
                            <tr v-if="!filteredFacturas.length">
                                <td colspan="7" class="px-2 py-4 text-center text-xs text-gray-500">No hay comprobantes para los filtros seleccionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <DialogModal :show="showCreateModal" @close="showCreateModal = false">
            <template #title>Crear hoja de ruta</template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Deposito" />
                        <select v-model="createForm.deposito_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option value="">Seleccionar...</option>
                            <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nombre }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.deposito_id" />
                    </div>
                    <div>
                        <InputLabel value="Fecha" />
                        <input v-model="createForm.fecha" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required />
                        <InputError class="mt-2" :message="createForm.errors.fecha" />
                    </div>
                    <div>
                        <InputLabel value="Vehiculo (opcional)" />
                        <select v-model="createForm.vehiculo_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Seleccionar...</option>
                            <option v-for="v in vehiculos" :key="v.id" :value="v.id">{{ v.patente }} {{ v.marca ? '- ' + v.marca : '' }} {{ v.modelo ? v.modelo : '' }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.vehiculo_id" />
                    </div>
                    <div>
                        <InputLabel value="Zona (opcional)" />
                        <select v-model="createForm.zona_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Seleccionar...</option>
                            <option v-for="z in zonas" :key="z.id" :value="z.id">{{ z.nombre }}</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.zona_id" />
                    </div>
                    <div>
                        <InputLabel value="Chofer (opcional)" />
                        <select v-model="createForm.chofer_user_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Seleccionar...</option>
                            <option v-for="c in choferes" :key="c.id" :value="c.id">{{ c.name }} ({{ c.email }})</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.chofer_user_id" />
                    </div>
                    <div class="text-sm text-gray-600">
                        Comprobantes seleccionados: {{ createForm.comprobante_ids.length }}
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showCreateModal = false">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="createForm.processing" @click="submitCreate">Crear hoja</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
