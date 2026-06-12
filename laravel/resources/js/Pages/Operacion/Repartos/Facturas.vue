<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { ref } from 'vue';

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
    empresa_id: props.filters?.empresa_id || '',
    comprobante_ids: [],
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
        { zona_id: filterForm.zona_id || null, localidad: filterForm.localidad || null, fecha: filterForm.fecha || null, tipo: filterForm.tipo || 'todos', empresa_id: filterForm.empresa_id || null },
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

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">Zona</div>
                        <select v-model="filterForm.zona_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todos</option>
                            <option v-for="z in zonas" :key="z.id" :value="z.id">{{ z.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Ciudad</div>
                        <select v-model="filterForm.localidad" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todas</option>
                            <option v-for="loc in localidades" :key="loc" :value="loc">{{ loc }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Fecha</div>
                        <input v-model="filterForm.fecha" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Tipo</div>
                        <select v-model="filterForm.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="todos">Todos</option>
                            <option value="factura_interna">Facturas</option>
                            <option value="guia_envio">Guias</option>
                        </select>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Empresa</div>
                        <select v-model="filterForm.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todas</option>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                    </div>
                    <div class="flex items-end justify-end">
                        <SecondaryButton type="button" @click="applyFilters">Aplicar</SecondaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Comprobantes emitidos (listas)</h3>
                        <p class="mt-1 text-sm text-gray-600">Selecciona facturas o guias para armar hoja de ruta.</p>
                    </div>
                    <PrimaryButton :disabled="filterForm.processing || !filterForm.comprobante_ids.length" @click.prevent="openCreateModal">
                        Crear hoja
                    </PrimaryButton>
                </div>

                <div class="space-y-4 p-4 sm:hidden">
                    <div v-for="f in facturas" :key="f.id" class="rounded-lg border border-gray-200 bg-white p-4">
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
                    <div v-if="!facturas.length" class="rounded-lg border border-gray-200 bg-white px-6 py-10 text-center text-sm text-gray-500">No hay comprobantes para los filtros seleccionados.</div>
                </div>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-[1200px] w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" :checked="filterForm.comprobante_ids.length === facturas.length && facturas.length" @change="toggleAll($event.target.checked)" />
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cotizacion</th>
                                <th class="sticky right-0 bg-gray-50 px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="f in facturas" :key="f.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Checkbox v-model:checked="filterForm.comprobante_ids" :value="f.id" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ f.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ tipoLabel(f.tipo) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ f.empresa?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ f.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">CUIT {{ f.entrega_cuenta?.tercero?.cuit || '-' }} · Nro {{ f.entrega_cuenta?.numero_cliente || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ f.entrega_cuenta?.direccion || '' }} {{ f.entrega_cuenta?.localidad ? '· ' + f.entrega_cuenta.localidad : '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ f.moneda }} {{ f.total }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ f.moneda === 'ARS' ? '-' : (f.detalle_facturacion?.calculo?.cotizacion?.tasa_ars || f.detalle_facturacion?.cotizacion?.tasa_ars || '-') }}</td>
                                <td class="sticky right-0 bg-white px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">Seleccionar</td>
                            </tr>
                            <tr v-if="!facturas.length">
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">No hay comprobantes para los filtros seleccionados.</td>
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
