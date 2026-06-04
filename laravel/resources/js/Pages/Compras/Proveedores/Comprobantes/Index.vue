<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    proveedores: Array,
    comprobantes: Object,
    catalogos: Object,
    resumen: Object,
});

const form = useForm({
    tercero_cuenta_id: '',
    proveedor_cuit_busqueda: '',
    tipo: '',
    numero: '',
    moneda: 'ARS',
    iva_items: [
        { alicuota: 21, base_imponible: '' },
    ],
    percepciones: [],
    retenciones: [],
    impuestos_combustible: '',
    pago_cuenta_combustible: '',
    fecha_emision: new Date().toISOString().slice(0, 10),
    fecha_vencimiento: '',
    observacion: '',
});

const submit = () => form.post(route('compras.proveedores.comprobantes.store'), { preserveScroll: true });

const proveedorDialog = ref(false);
const editingProveedorId = ref(null);
const proveedorLookupInfo = ref('');
const editComprobanteDialog = ref(false);
const editComprobanteId = ref(null);

const proveedorForm = useForm({
    cuit: '',
    razon_social: '',
    condicion_iva: '',
    nombre_cuenta: '',
    email: '',
    localidad: '',
    barrio: '',
});

const editComprobanteForm = useForm({
    tipo: '',
    numero: '',
    moneda: 'ARS',
    iva_items: [{ alicuota: 21, base_imponible: '' }],
    percepciones: [],
    retenciones: [],
    impuestos_combustible: '',
    pago_cuenta_combustible: '',
    fecha_emision: '',
    fecha_vencimiento: '',
    observacion: '',
});

const fiscalSummary = (target) => computed(() => {
    const ivaItems = (target.iva_items || []).map((item) => {
        const base = Number(item.base_imponible || 0);
        const alicuota = Number(item.alicuota || 0);
        return { base, importe: Math.round((base * (alicuota / 100) + Number.EPSILON) * 100) / 100 };
    });
    const subtotal = ivaItems.reduce((acc, x) => acc + x.base, 0);
    const iva = ivaItems.reduce((acc, x) => acc + x.importe, 0);
    const percepciones = (target.percepciones || []).reduce((acc, x) => acc + Number(x.importe || 0), 0);
    const retenciones = (target.retenciones || []).reduce((acc, x) => acc + Number(x.importe || 0), 0);
    const impComb = Number(target.impuestos_combustible || 0);
    const pagoCuentaComb = Number(target.pago_cuenta_combustible || 0);
    const tributos = percepciones + impComb;
    const total = subtotal + iva + tributos - retenciones - pagoCuentaComb;
    return {
        subtotal: subtotal.toFixed(2),
        iva: iva.toFixed(2),
        tributos: tributos.toFixed(2),
        retenciones: (retenciones + pagoCuentaComb).toFixed(2),
        total: total.toFixed(2),
    };
});

const summary = fiscalSummary(form);
const editSummary = fiscalSummary(editComprobanteForm);

const addIvaItem = (target) => target.iva_items.push({ alicuota: 21, base_imponible: '' });
const addPercepcion = (target) => target.percepciones.push({ concepto: '', importe: '' });
const addRetencion = (target) => target.retenciones.push({ concepto: '', importe: '' });
const removeAt = (arr, index) => arr.splice(index, 1);

const tiposArca = ref([]);

const fetchTiposArca = async (terceroCuentaId) => {
    if (!terceroCuentaId) { tiposArca.value = []; return; }
    try {
        const url = route('compras.proveedores.tipos-arca', { tercero_cuenta_id: terceroCuentaId });
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        tiposArca.value = await res.json();
    } catch { tiposArca.value = []; }
};

watch(() => form.tercero_cuenta_id, (val) => {
    if (editComprobanteDialog.value) return;
    fetchTiposArca(val);
    form.tipo = '';
});

const buscarProveedorPorCuit = async () => {
    proveedorLookupInfo.value = '';
    proveedorForm.clearErrors();
    const cuit = String(form.proveedor_cuit_busqueda || '').trim();
    if (!cuit) return;

    const url = route('compras.proveedores.lookup-cuit', { cuit });
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const data = await res.json();

    if (!data.found) {
        editingProveedorId.value = null;
        proveedorForm.cuit = cuit;
        proveedorForm.razon_social = '';
        proveedorForm.condicion_iva = '';
        proveedorForm.nombre_cuenta = '';
        proveedorForm.email = '';
        proveedorForm.localidad = '';
        proveedorForm.barrio = '';
        proveedorLookupInfo.value = 'No existe un tercero con ese CUIT. Puedes crearlo como proveedor.';
        proveedorDialog.value = true;
        return;
    }

    proveedorForm.cuit = data.tercero?.cuit || cuit;
    proveedorForm.razon_social = data.tercero?.razon_social || '';
    proveedorForm.condicion_iva = data.tercero?.condicion_iva || '';
    proveedorForm.nombre_cuenta = data.cuenta?.nombre_cuenta || '';
    proveedorForm.email = data.cuenta?.email || '';
    proveedorForm.localidad = data.cuenta?.localidad || '';
    proveedorForm.barrio = data.cuenta?.barrio || '';

    if (data.cuenta?.id) {
        form.tercero_cuenta_id = data.cuenta.id;
        editingProveedorId.value = data.cuenta.id;
        proveedorLookupInfo.value = 'Proveedor existente encontrado y seleccionado. Puedes editarlo si hace falta.';
    } else {
        editingProveedorId.value = null;
        proveedorLookupInfo.value = 'El CUIT existe, pero no tiene cuenta proveedor en esta empresa. Puedes crearla.';
    }

    proveedorDialog.value = true;
};

const submitProveedor = () => {
    if (editingProveedorId.value) {
        proveedorForm.put(route('compras.proveedores.update', editingProveedorId.value), {
            preserveScroll: true,
            onSuccess: () => { proveedorDialog.value = false; },
        });
        return;
    }

    proveedorForm.post(route('compras.proveedores.store'), {
        preserveScroll: true,
        onSuccess: () => { proveedorDialog.value = false; },
    });
};

const openEditComprobante = (c) => {
    editComprobanteId.value = c.id;
    fetchTiposArca(c.tercero_cuenta_id);
    editComprobanteForm.tipo = c.tipo || '';
    editComprobanteForm.numero = c.numero || '';
    editComprobanteForm.moneda = c.moneda || 'ARS';
    editComprobanteForm.iva_items = c.detalle?.iva_items?.length ? c.detalle.iva_items.map((x) => ({ alicuota: x.alicuota, base_imponible: x.base_imponible })) : [{ alicuota: 21, base_imponible: '' }];
    editComprobanteForm.percepciones = c.detalle?.percepciones?.length ? c.detalle.percepciones.map((x) => ({ concepto: x.concepto, importe: x.importe })) : [];
    editComprobanteForm.retenciones = c.detalle?.retenciones?.length ? c.detalle.retenciones.map((x) => ({ concepto: x.concepto, importe: x.importe })) : [];
    editComprobanteForm.impuestos_combustible = c.detalle?.combustible?.impuestos_combustible || '';
    editComprobanteForm.pago_cuenta_combustible = c.detalle?.combustible?.pago_cuenta_combustible || '';
    editComprobanteForm.fecha_emision = String(c.fecha_emision || '').slice(0, 10);
    editComprobanteForm.fecha_vencimiento = c.fecha_vencimiento ? String(c.fecha_vencimiento).slice(0, 10) : '';
    editComprobanteForm.observacion = c.observacion || '';
    editComprobanteForm.clearErrors();
    editComprobanteDialog.value = true;
};

const submitEditComprobante = () => {
    editComprobanteForm.put(route('compras.proveedores.comprobantes.update', editComprobanteId.value), {
        preserveScroll: true,
        onSuccess: () => { editComprobanteDialog.value = false; },
    });
};
</script>

<template>
    <AppLayout title="Compras / Proveedores / Comprobantes">
        <Head title="Compras / Proveedores / Comprobantes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compras / Proveedores / Comprobantes</h2>
                <div class="flex items-center gap-3">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.export')">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.ctacte.index')">Cta. cte. proveedores</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-5 gap-4">
                <div><div class="text-xs text-gray-500">Subtotal</div><div class="text-sm font-medium text-gray-900">{{ resumen?.subtotal || 0 }}</div></div>
                <div><div class="text-xs text-gray-500">IVA</div><div class="text-sm font-medium text-gray-900">{{ resumen?.iva_total || 0 }}</div></div>
                <div><div class="text-xs text-gray-500">Tributos</div><div class="text-sm font-medium text-gray-900">{{ resumen?.tributos_total || 0 }}</div></div>
                <div><div class="text-xs text-gray-500">Retenciones</div><div class="text-sm font-medium text-gray-900">{{ resumen?.retenciones_total || 0 }}</div></div>
                <div><div class="text-xs text-gray-500">Total</div><div class="text-sm font-medium text-gray-900">{{ resumen?.total || 0 }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nuevo comprobante proveedor</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submit">
                    <div class="sm:col-span-4 grid grid-cols-1 sm:grid-cols-4 gap-4 items-end rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="sm:col-span-2">
                            <InputLabel value="Buscar proveedor por CUIT" />
                            <TextInput v-model="form.proveedor_cuit_busqueda" type="text" class="mt-1 block w-full" placeholder="CUIT" />
                        </div>
                        <div class="flex gap-2 sm:col-span-2">
                            <SecondaryButton type="button" @click="buscarProveedorPorCuit">Buscar / crear</SecondaryButton>
                            <SecondaryButton type="button" @click="proveedorDialog = true">Nuevo proveedor</SecondaryButton>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Proveedor" />
                        <select v-model="form.tercero_cuenta_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">(seleccionar)</option>
                            <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.tercero?.razon_social || p.nombre_cuenta || ('#' + p.id) }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.tercero_cuenta_id" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="form.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">(seleccionar tipo)</option>
                            <option v-for="t in tiposArca" :key="t.code" :value="t.code">{{ t.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.tipo" />
                    </div>
                    <div><InputLabel value="Numero" /><TextInput v-model="form.numero" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.numero" /></div>
                    <div><InputLabel value="Moneda" /><select v-model="form.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-2" :message="form.errors.moneda" /></div>
                    <div class="sm:col-span-4 rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <h4 class="text-sm font-semibold text-gray-900">IVA</h4>
                            <SecondaryButton type="button" @click="addIvaItem(form)">Agregar IVA</SecondaryButton>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(item, index) in form.iva_items" :key="index" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                                <div><InputLabel value="Alicuota" /><select v-model="item.alicuota" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option :value="27">27%</option><option :value="21">21%</option><option :value="10.5">10.5%</option><option :value="5">5%</option><option :value="2.5">2.5%</option><option :value="0">0%</option></select></div>
                                <div><InputLabel value="Base imponible" /><TextInput v-model="item.base_imponible" type="number" min="0" step="0.01" class="mt-1 block w-full" /></div>
                                <div class="flex items-end gap-2"><div class="text-sm text-gray-700">IVA {{ (Number(item.base_imponible || 0) * Number(item.alicuota || 0) / 100).toFixed(2) }}</div><button v-if="form.iva_items.length > 1" type="button" class="text-sm text-red-600" @click="removeAt(form.iva_items, index)">Quitar</button></div>
                            </div>
                        </div>
                    </div>
                    <div class="sm:col-span-2 rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-4"><h4 class="text-sm font-semibold text-gray-900">Percepciones</h4><SecondaryButton type="button" @click="addPercepcion(form)">Agregar</SecondaryButton></div>
                        <div class="mt-3 space-y-3"><div v-for="(item, index) in form.percepciones" :key="index" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"><div class="sm:col-span-2"><select v-model="item.concepto" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">(concepto)</option><option v-for="c in (catalogos?.percepciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select></div><div class="flex items-end gap-2"><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="block w-full" placeholder="Importe" /><button type="button" class="text-sm text-red-600" @click="removeAt(form.percepciones, index)">Quitar</button></div></div></div>
                    </div>
                    <div class="sm:col-span-2 rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-4"><h4 class="text-sm font-semibold text-gray-900">Retenciones</h4><SecondaryButton type="button" @click="addRetencion(form)">Agregar</SecondaryButton></div>
                        <div class="mt-3 space-y-3"><div v-for="(item, index) in form.retenciones" :key="index" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"><div class="sm:col-span-2"><select v-model="item.concepto" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">(concepto)</option><option v-for="c in (catalogos?.retenciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select></div><div class="flex items-end gap-2"><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="block w-full" placeholder="Importe" /><button type="button" class="text-sm text-red-600" @click="removeAt(form.retenciones, index)">Quitar</button></div></div></div>
                    </div>
                    <div><InputLabel value="Impuestos combustible" /><TextInput v-model="form.impuestos_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Pago a cuenta combustible" /><TextInput v-model="form.pago_cuenta_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Fecha emision" /><TextInput v-model="form.fecha_emision" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.fecha_emision" /></div>
                    <div><InputLabel value="Fecha vencimiento" /><TextInput v-model="form.fecha_vencimiento" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.fecha_vencimiento" /></div>
                    <div class="sm:col-span-3"><InputLabel value="Observacion" /><TextInput v-model="form.observacion" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.observacion" /></div>
                    <div class="sm:col-span-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                        Subtotal {{ summary.subtotal }} · IVA {{ summary.iva }} · Tributos {{ summary.tributos }} · Retenciones {{ summary.retenciones }} · Total {{ summary.total }}
                    </div>
                    <div class="sm:col-span-4 flex justify-end"><PrimaryButton :disabled="form.processing">Guardar</PrimaryButton></div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Comprobantes cargados</h3></div>
                <div class="space-y-4 p-4 sm:hidden">
                    <div v-for="c in comprobantes.data" :key="c.id" class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ c.cuenta?.tercero?.razon_social || '-' }}</div>
                                <div class="text-xs text-gray-500">{{ String(c.fecha_emision || '').slice(0,10) }} · {{ c.tipo }}</div>
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ c.moneda }} {{ c.total }}</div>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Numero</div>
                                <div class="font-medium text-gray-900">{{ c.numero || '-' }}</div>
                            </div>
                            <div class="flex gap-3">
                                <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link>
                                <button type="button" class="text-sm text-gray-700 hover:text-gray-900" @click.prevent="openEditComprobante(c)">Editar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numero</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes.data" :key="c.id"><td class="px-6 py-4 text-sm text-gray-700">{{ String(c.fecha_emision || '').slice(0,10) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.cuenta?.tercero?.razon_social || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.tipo }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.numero || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ c.total }}</td><td class="px-6 py-4 text-right text-sm"><Link class="text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link><button type="button" class="ms-3 text-gray-700 hover:text-gray-900" @click.prevent="openEditComprobante(c)">Editar</button></td></tr></tbody>
                    </table>
                </div>
            </div>

            <DialogModal :show="proveedorDialog" @close="proveedorDialog = false">
                <template #title>{{ editingProveedorId ? 'Editar proveedor' : 'Nuevo proveedor' }}</template>
                <template #content>
                    <div v-if="proveedorLookupInfo" class="mb-4 text-sm text-gray-600">{{ proveedorLookupInfo }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><InputLabel value="CUIT" /><TextInput v-model="proveedorForm.cuit" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="proveedorForm.errors.cuit" /></div>
                        <div><InputLabel value="Razon social" /><TextInput v-model="proveedorForm.razon_social" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="proveedorForm.errors.razon_social" /></div>
                        <div><InputLabel value="Condicion IVA" /><TextInput v-model="proveedorForm.condicion_iva" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="proveedorForm.errors.condicion_iva" /></div>
                        <div><InputLabel value="Nombre cuenta" /><TextInput v-model="proveedorForm.nombre_cuenta" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="proveedorForm.errors.nombre_cuenta" /></div>
                        <div><InputLabel value="Email" /><TextInput v-model="proveedorForm.email" type="email" class="mt-1 block w-full" /><InputError class="mt-2" :message="proveedorForm.errors.email" /></div>
                        <div><InputLabel value="Ciudad" /><TextInput v-model="proveedorForm.localidad" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="proveedorForm.errors.localidad" /></div>
                        <div><InputLabel value="Barrio" /><TextInput v-model="proveedorForm.barrio" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="proveedorForm.errors.barrio" /></div>
                    </div>
                </template>
                <template #footer>
                    <SecondaryButton @click="proveedorDialog = false">Cancelar</SecondaryButton>
                    <PrimaryButton class="ms-3" :disabled="proveedorForm.processing" @click="submitProveedor">Guardar proveedor</PrimaryButton>
                </template>
            </DialogModal>

            <DialogModal :show="editComprobanteDialog" @close="editComprobanteDialog = false">
                <template #title>Editar comprobante proveedor</template>
                <template #content>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div><InputLabel value="Tipo" /><select v-model="editComprobanteForm.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option value="">(seleccionar tipo)</option><option v-for="t in tiposArca" :key="t.code" :value="t.code">{{ t.label }}</option></select><InputError class="mt-2" :message="editComprobanteForm.errors.tipo" /></div>
                            <div><InputLabel value="Numero" /><TextInput v-model="editComprobanteForm.numero" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="editComprobanteForm.errors.numero" /></div>
                            <div><InputLabel value="Moneda" /><select v-model="editComprobanteForm.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-2" :message="editComprobanteForm.errors.moneda" /></div>
                            <div class="sm:col-span-2 rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between gap-4"><h4 class="text-sm font-semibold text-gray-900">IVA</h4><SecondaryButton type="button" @click="addIvaItem(editComprobanteForm)">Agregar IVA</SecondaryButton></div>
                                <div class="mt-3 space-y-3"><div v-for="(item, index) in editComprobanteForm.iva_items" :key="index" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"><div><InputLabel value="Alicuota" /><select v-model="item.alicuota" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option :value="27">27%</option><option :value="21">21%</option><option :value="10.5">10.5%</option><option :value="5">5%</option><option :value="2.5">2.5%</option><option :value="0">0%</option></select></div><div><InputLabel value="Base imponible" /><TextInput v-model="item.base_imponible" type="number" min="0" step="0.01" class="mt-1 block w-full" /></div><div class="flex items-end gap-2"><div class="text-sm text-gray-700">IVA {{ (Number(item.base_imponible || 0) * Number(item.alicuota || 0) / 100).toFixed(2) }}</div><button v-if="editComprobanteForm.iva_items.length > 1" type="button" class="text-sm text-red-600" @click="removeAt(editComprobanteForm.iva_items, index)">Quitar</button></div></div></div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between gap-4"><h4 class="text-sm font-semibold text-gray-900">Percepciones</h4><SecondaryButton type="button" @click="addPercepcion(editComprobanteForm)">Agregar</SecondaryButton></div>
                                <div class="mt-3 space-y-3"><div v-for="(item, index) in editComprobanteForm.percepciones" :key="index" class="grid grid-cols-1 gap-2"><select v-model="item.concepto" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">(concepto)</option><option v-for="c in (catalogos?.percepciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select><div class="flex items-end gap-2"><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="block w-full" placeholder="Importe" /><button type="button" class="text-sm text-red-600" @click="removeAt(editComprobanteForm.percepciones, index)">Quitar</button></div></div></div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between gap-4"><h4 class="text-sm font-semibold text-gray-900">Retenciones</h4><SecondaryButton type="button" @click="addRetencion(editComprobanteForm)">Agregar</SecondaryButton></div>
                                <div class="mt-3 space-y-3"><div v-for="(item, index) in editComprobanteForm.retenciones" :key="index" class="grid grid-cols-1 gap-2"><select v-model="item.concepto" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">(concepto)</option><option v-for="c in (catalogos?.retenciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select><div class="flex items-end gap-2"><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="block w-full" placeholder="Importe" /><button type="button" class="text-sm text-red-600" @click="removeAt(editComprobanteForm.retenciones, index)">Quitar</button></div></div></div>
                            </div>
                            <div><InputLabel value="Impuestos combustible" /><TextInput v-model="editComprobanteForm.impuestos_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full" /></div>
                            <div><InputLabel value="Pago a cuenta combustible" /><TextInput v-model="editComprobanteForm.pago_cuenta_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full" /></div>
                            <div><InputLabel value="Fecha emision" /><TextInput v-model="editComprobanteForm.fecha_emision" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="editComprobanteForm.errors.fecha_emision" /></div>
                            <div><InputLabel value="Fecha vencimiento" /><TextInput v-model="editComprobanteForm.fecha_vencimiento" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="editComprobanteForm.errors.fecha_vencimiento" /></div>
                            <div class="sm:col-span-2"><InputLabel value="Observacion" /><TextInput v-model="editComprobanteForm.observacion" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="editComprobanteForm.errors.observacion" /></div>
                            <div class="sm:col-span-2 rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
                                Subtotal {{ editSummary.subtotal }} · IVA {{ editSummary.iva }} · Tributos {{ editSummary.tributos }} · Retenciones {{ editSummary.retenciones }} · Total {{ editSummary.total }}
                            </div>
                        </div>
                </template>
                <template #footer>
                    <SecondaryButton @click="editComprobanteDialog = false">Cancelar</SecondaryButton>
                    <PrimaryButton class="ms-3" :disabled="editComprobanteForm.processing" @click="submitEditComprobante">Guardar cambios</PrimaryButton>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>
