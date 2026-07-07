<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import PdfImportDialog from '@/Components/PdfImportDialog.vue';
import { computed, ref, watch } from 'vue';
import { formatNum } from '@/Utils/format.js';

const tipoLabel = (t) => {
    if (!t) return '-';
    const map = {
        '1': 'Factura A', '2': 'ND A', '3': 'NC A',
        '6': 'Factura B', '7': 'ND B', '8': 'NC B',
        '11': 'Factura C', '12': 'ND C', '13': 'NC C',
        '51': 'Factura M', '52': 'ND M', '53': 'NC M',
        'FA': 'Factura A', 'FB': 'Factura B', 'FC': 'Factura C',
        'FCA': 'Factura Crédito A', 'FCB': 'Factura Crédito B', 'FCC': 'Factura Crédito C',
        'NDA': 'ND A', 'NDB': 'ND B', 'NDC': 'ND C',
        'NCA': 'NC A', 'NCB': 'NC B', 'NCC': 'NC C',
        'FM': 'Factura M', 'NDM': 'ND M', 'NCM': 'NC M',
    };
    return map[String(t).trim().toUpperCase()] || t;
};

const parsePv = (num) => {
    if (!num) return '-';
    const parts = String(num).split('-');
    return parts[0] ? String(parseInt(parts[0], 10)) : '-';
};
const parseNro = (num) => {
    if (!num) return '-';
    const parts = String(num).split('-');
    return parts[1] ? parts[1] : num;
};

const tasaActualCombustible = ref(0);

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
    subtotal: '',
    iva_items: [
        { alicuota: 21, base_imponible: '' },
    ],
    percepciones: [],
    retenciones: [],
    combustible_tipo: '',
    litros_combustible: '',
    impuestos_combustible: '',
    pago_cuenta_combustible: '',
    fecha_emision: new Date().toISOString().slice(0, 10),
    fecha_vencimiento: '',
    observacion: '',
});

const submit = () => form.post(route('compras.proveedores.comprobantes.store'), { preserveScroll: true });

const editComprobanteDialog = ref(false);
const editComprobanteId = ref(null);

const editComprobanteForm = useForm({
    tipo: '',
    numero: '',
    moneda: 'ARS',
    subtotal: '',
    iva_items: [{ alicuota: 21, base_imponible: '' }],
    percepciones: [],
    retenciones: [],
    combustible_tipo: '',
    litros_combustible: '',
    impuestos_combustible: '',
    pago_cuenta_combustible: '',
    fecha_emision: '',
    fecha_vencimiento: '',
    observacion: '',
});

const fiscalSummary = (target) => computed(() => {
    const ivaDesglosado = (target.tipo || '').endsWith('A');
    let subtotal = 0;
    let iva = 0;
    if (ivaDesglosado) {
        const ivaItems = (target.iva_items || []).map((item) => {
            const base = Number(item.base_imponible || 0);
            const alicuota = Number(item.alicuota || 0);
            return { base, importe: Math.round((base * (alicuota / 100) + Number.EPSILON) * 100) / 100 };
        });
        subtotal = ivaItems.reduce((acc, x) => acc + x.base, 0);
        iva = ivaItems.reduce((acc, x) => acc + x.importe, 0);
    } else {
        subtotal = Number(target.subtotal || 0);
    }
    const percepciones = (target.percepciones || []).reduce((acc, x) => acc + Number(x.importe || 0), 0);
    const retenciones = (target.retenciones || []).reduce((acc, x) => acc + Number(x.importe || 0), 0);
    const impComb = Number(target.impuestos_combustible || 0);
    const pagoCuentaComb = Number(target.pago_cuenta_combustible || 0);
    const tributos = percepciones + impComb;
    const total = ivaDesglosado
        ? subtotal + iva + tributos - retenciones - pagoCuentaComb
        : subtotal + tributos - retenciones - pagoCuentaComb;
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
const catalogosImpuestos = ref(null);

const fetchTiposArca = async (terceroCuentaId, todos = false) => {
    if (!terceroCuentaId) { tiposArca.value = []; catalogosImpuestos.value = null; return; }
    try {
        const url = route('compras.proveedores.tipos-arca', { tercero_cuenta_id: terceroCuentaId, ...(todos ? { todos: 1 } : {}) });
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json();
        tiposArca.value = data.tipos || [];
        catalogosImpuestos.value = data.catalogos_impuestos || null;
    } catch { tiposArca.value = []; catalogosImpuestos.value = null; }
};

watch(() => form.tercero_cuenta_id, (val) => {
    if (editComprobanteDialog.value) return;
    fetchTiposArca(val);
    form.tipo = '';
});

watch(() => form.tipo, (tipo) => {
    if (!tipo) return;
    if (tipo.endsWith('A')) {
        form.subtotal = '';
    } else {
        form.iva_items = [{ alicuota: 21, base_imponible: '' }];
    }
});

const TIPOS_COMBUSTIBLE = [
    { value: 'gasoil_grado_2', label: 'Gasoil Grado 2' },
    { value: 'gasoil_grado_3', label: 'Gasoil Grado 3' },
    { value: 'nafta_super', label: 'Nafta Superior' },
    { value: 'nafta_premium', label: 'Nafta Premium' },
    { value: 'kerosene', label: 'Kerosene' },
    { value: 'fuel_oil', label: 'Fuel Oil' },
];

const fetchTasaCombustible = async (tipo, fecha) => {
    if (!tipo) { tasaActualCombustible.value = 0; return 0; }
    try {
        const url = route('compras.combustibles.tasa-actual', { combustible_tipo: tipo, fecha: fecha || new Date().toISOString().slice(0, 10) });
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json();
        tasaActualCombustible.value = data.monto_por_litro || 0;
        return tasaActualCombustible.value;
    } catch {
        tasaActualCombustible.value = 0;
        return 0;
    }
};

const actualizarPagoCuenta = async (target) => {
    const tipo = target.combustible_tipo;
    const litros = Number(target.litros_combustible || 0);
    const tasa = await fetchTasaCombustible(tipo, target.fecha_emision);
    if (!tipo || litros <= 0) {
        target.pago_cuenta_combustible = '';
        return;
    }
    target.pago_cuenta_combustible = (litros * tasa).toFixed(2);
};

const guardarTasaCombustible = async (target) => {
    const tipo = target.combustible_tipo;
    const litros = Number(target.litros_combustible || 0);
    const impuestos = Number(target.impuestos_combustible || 0);
    if (!tipo || litros <= 0 || impuestos <= 0) return;
    const tasaCalculada = Math.round((impuestos / litros) * 10000) / 10000;
    const mes = (target.fecha_emision || new Date().toISOString().slice(0, 10)).slice(0, 7);
    try {
        await fetch(route('compras.combustibles.tasas.store'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ combustible_tipo: tipo, mes, monto_por_litro: tasaCalculada }),
        });
        tasaActualCombustible.value = tasaCalculada;
        target.pago_cuenta_combustible = (litros * tasaCalculada).toFixed(2);
    } catch { /* silencioso */ }
};

const debounceTimer = ref(null);
const programarGuardarTasa = (target) => {
    if (debounceTimer.value) clearTimeout(debounceTimer.value);
    debounceTimer.value = setTimeout(() => guardarTasaCombustible(target), 800);
};

watch([() => form.combustible_tipo, () => form.litros_combustible, () => form.fecha_emision], () => { actualizarPagoCuenta(form); });
watch([() => form.combustible_tipo, () => form.litros_combustible, () => form.impuestos_combustible, () => form.fecha_emision], () => { programarGuardarTasa(form); });
watch([() => editComprobanteForm.combustible_tipo, () => editComprobanteForm.litros_combustible, () => editComprobanteForm.fecha_emision], () => { actualizarPagoCuenta(editComprobanteForm); });
watch([() => editComprobanteForm.combustible_tipo, () => editComprobanteForm.litros_combustible, () => editComprobanteForm.impuestos_combustible, () => editComprobanteForm.fecha_emision], () => { programarGuardarTasa(editComprobanteForm); });

const pdfImportDialog = ref(false);

const onPdfImported = (datos) => {
    if (datos.cuit) {
        form.proveedor_cuit_busqueda = datos.cuit;
        fetchTiposArcaPorCuit(datos.cuit).then((cuenta) => {
            if (cuenta) {
                form.tercero_cuenta_id = cuenta.id;
            }
        });
    }
    if (datos.tipo && tiposArca.value.some((t) => t.code === datos.tipo)) {
        form.tipo = datos.tipo;
    }
    if (datos.numero) form.numero = datos.numero;
    if (datos.fecha_emision) form.fecha_emision = datos.fecha_emision;
    if (datos.subtotal && form.tipo && !form.tipo.endsWith('A')) {
        form.subtotal = String(datos.subtotal);
    }
    if (datos.iva_items?.length && form.tipo?.endsWith('A')) {
        form.iva_items = datos.iva_items.map((item) => ({
            alicuota: item.alicuota,
            base_imponible: String(item.base_imponible || item.importe * 100 / item.alicuota),
        }));
    }
    if (datos.percepciones?.length) {
        form.percepciones = datos.percepciones.map((p) => ({ concepto: p.concepto, importe: String(p.importe) }));
    }
    if (datos.retenciones?.length) {
        form.retenciones = datos.retenciones.map((r) => ({ concepto: r.concepto, importe: String(r.importe) }));
    }
};

const fetchTiposArcaPorCuit = async (cuit) => {
    try {
        const url = route('compras.proveedores.lookup-cuit', { cuit });
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json();
        if (data.cuenta?.id) {
            await fetchTiposArca(data.cuenta.id);
            return data.cuenta;
        }
    } catch { /* ignore */ }
    return null;
};

const buscarProveedorPorCuit = async () => {
    const cuit = String(form.proveedor_cuit_busqueda || '').trim();
    if (!cuit) return;

    const url = route('compras.proveedores.lookup-cuit', { cuit });
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const data = await res.json();

    if (data.cuenta?.id) {
        form.tercero_cuenta_id = data.cuenta.id;
    } else {
        window.location.href = route('admin.terceros.index', { cuit, tipo: 'proveedor' });
    }
};

const openEditComprobante = (c) => {
    editComprobanteId.value = c.id;
    fetchTiposArca(c.tercero_cuenta_id, true);
    editComprobanteForm.tipo = c.tipo || '';
    editComprobanteForm.numero = c.numero || '';
    editComprobanteForm.moneda = c.moneda || 'ARS';
    editComprobanteForm.subtotal = c.subtotal || '';
    editComprobanteForm.iva_items = c.detalle?.iva_items?.length ? c.detalle.iva_items.map((x) => ({ alicuota: x.alicuota, base_imponible: x.base_imponible })) : [{ alicuota: 21, base_imponible: '' }];
    editComprobanteForm.percepciones = c.detalle?.percepciones?.length ? c.detalle.percepciones.map((x) => ({ concepto: x.concepto, importe: x.importe })) : [];
    editComprobanteForm.retenciones = c.detalle?.retenciones?.length ? c.detalle.retenciones.map((x) => ({ concepto: x.concepto, importe: x.importe })) : [];
    editComprobanteForm.combustible_tipo = c.detalle?.combustible?.tipo || '';
    editComprobanteForm.litros_combustible = c.detalle?.combustible?.litros || '';
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
                    <button type="button" class="text-sm text-indigo-600 hover:text-indigo-800" @click.prevent="pdfImportDialog = true">Importar PDF</button>
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.export')">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.ctacte.index')">Cta. cte. proveedores</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.combustibles.index')">Combustibles</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow sm:rounded-lg p-3 grid grid-cols-2 sm:grid-cols-5 gap-2 text-sm">
                <div><div class="text-xs text-gray-500">Subtotal</div><div class="font-medium text-gray-900">$ {{ formatNum(resumen?.subtotal || 0) }}</div></div>
                <div><div class="text-xs text-gray-500">IVA</div><div class="font-medium text-gray-900">$ {{ formatNum(resumen?.iva_total || 0) }}</div></div>
                <div><div class="text-xs text-gray-500">Tributos</div><div class="font-medium text-gray-900">$ {{ formatNum(resumen?.tributos_total || 0) }}</div></div>
                <div><div class="text-xs text-gray-500">Retenciones</div><div class="font-medium text-gray-900">$ {{ formatNum(resumen?.retenciones_total || 0) }}</div></div>
                <div><div class="text-xs text-gray-500">Total</div><div class="font-medium text-gray-900">$ {{ formatNum(resumen?.total || 0) }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Nuevo comprobante proveedor</h3>
                <form class="grid grid-cols-1 sm:grid-cols-4 gap-3" @submit.prevent="submit">
                    <div class="sm:col-span-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end rounded-lg border border-gray-200 bg-gray-50 p-2">
                        <div class="sm:col-span-2">
                            <InputLabel value="Buscar proveedor por CUIT" />
                            <TextInput v-model="form.proveedor_cuit_busqueda" type="text" class="mt-1 block w-full text-sm" placeholder="CUIT" />
                        </div>
                        <div class="flex gap-2 sm:col-span-2">
                            <SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="buscarProveedorPorCuit">Buscar CUIT</SecondaryButton>
                            <Link :href="route('admin.terceros.index', { tipo: 'proveedor' })" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Nuevo proveedor</Link>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Proveedor" />
                        <select v-model="form.tercero_cuenta_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">(seleccionar)</option>
                            <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.tercero?.razon_social || p.nombre_cuenta || ('#' + p.id) }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.tercero_cuenta_id" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="form.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">(seleccionar tipo)</option>
                            <option v-for="t in tiposArca" :key="t.code" :value="t.code">{{ t.label }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.tipo" />
                    </div>
                    <div><InputLabel value="Numero" /><TextInput v-model="form.numero" type="text" class="mt-1 block w-full text-sm" /><InputError class="mt-1" :message="form.errors.numero" /></div>
                    <div><InputLabel value="Moneda" /><select v-model="form.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-1" :message="form.errors.moneda" /></div>
                    <div v-if="form.tipo && form.tipo.endsWith('A')" class="sm:col-span-4 rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center justify-between gap-4">
                            <h4 class="text-sm font-semibold text-gray-900">IVA</h4>
                            <SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="addIvaItem(form)">Agregar IVA</SecondaryButton>
                        </div>
                        <div class="mt-2 space-y-2">
                            <div v-for="(item, index) in form.iva_items" :key="index" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                                <div><InputLabel value="Alicuota" /><select v-model="item.alicuota" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option :value="27">27%</option><option :value="21">21%</option><option :value="10.5">10.5%</option><option :value="5">5%</option><option :value="2.5">2.5%</option><option :value="0">0%</option></select></div>
                                <div><InputLabel value="Base imponible" /><TextInput v-model="item.base_imponible" type="number" min="0" step="0.01" class="mt-1 block w-full text-sm" /></div>
                                <div class="flex items-end gap-2"><div class="text-sm text-gray-700">IVA {{ (Number(item.base_imponible || 0) * Number(item.alicuota || 0) / 100).toFixed(2) }}</div><button v-if="form.iva_items.length > 1" type="button" class="text-sm text-red-600" @click="removeAt(form.iva_items, index)">Quitar</button></div>
                            </div>
                        </div>
                    </div>
                    <div v-if="form.tipo && !form.tipo.endsWith('A')" class="sm:col-span-4 rounded-lg border border-gray-200 p-3">
                        <InputLabel value="Subtotal / Importe (IVA incluido)" />
                        <TextInput v-model="form.subtotal" type="number" min="0" step="0.01" class="mt-1 block w-full text-sm" />
                    </div>
                    <div class="sm:col-span-2 rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center justify-between gap-4 mb-2"><h4 class="text-sm font-semibold text-gray-900">Percepciones</h4><SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="addPercepcion(form)">Agregar</SecondaryButton></div>
                        <div class="space-y-2"><div v-for="(item, index) in form.percepciones" :key="index" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end"><div class="sm:col-span-2"><select v-model="item.concepto" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">(concepto)</option><option v-for="c in (catalogosImpuestos?.percepciones || catalogos?.percepciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select></div><div class="flex items-end gap-2"><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="block w-full text-sm" placeholder="Importe" /><button type="button" class="text-sm text-red-600" @click="removeAt(form.percepciones, index)">Quitar</button></div></div></div>
                    </div>
                    <div class="sm:col-span-2 rounded-lg border border-gray-200 p-3">
                        <div class="flex items-center justify-between gap-4 mb-2"><h4 class="text-sm font-semibold text-gray-900">Retenciones</h4><SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="addRetencion(form)">Agregar</SecondaryButton></div>
                        <div class="space-y-2"><div v-for="(item, index) in form.retenciones" :key="index" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end"><div class="sm:col-span-2"><select v-model="item.concepto" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">(concepto)</option><option v-for="c in (catalogosImpuestos?.retenciones || catalogos?.retenciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select></div><div class="flex items-end gap-2"><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="block w-full text-sm" placeholder="Importe" /><button type="button" class="text-sm text-red-600" @click="removeAt(form.retenciones, index)">Quitar</button></div></div></div>
                    </div>
                    <div class="sm:col-span-4 rounded-lg border border-gray-200 p-3">
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">Combustible</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div>
                                <InputLabel value="Tipo combustible" />
                                <select v-model="form.combustible_tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="">(seleccionar)</option>
                                    <option v-for="t in TIPOS_COMBUSTIBLE" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Litros" />
                                <TextInput v-model="form.litros_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full text-sm" />
                            </div>
                            <div>
                                <InputLabel value="Tasa x litro ($)" />
                                <div class="mt-1 text-sm font-medium" :class="tasaActualCombustible > 0 ? 'text-gray-700' : 'text-yellow-700'">{{ form.combustible_tipo && Number(form.litros_combustible || 0) > 0 ? (tasaActualCombustible > 0 ? `$${tasaActualCombustible.toFixed(4)}` : 'Sin tasa configurada') : '-' }}</div>
                            </div>
                            <div>
                                <InputLabel value="Pago a cuenta" />
                                <div class="mt-1 text-sm font-semibold text-gray-900">{{ form.pago_cuenta_combustible ? `$${form.pago_cuenta_combustible}` : '-' }}</div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <InputLabel value="Impuestos combustible (adicional)" />
                            <TextInput v-model="form.impuestos_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full text-sm" />
                        </div>
                    </div>
                    <div><InputLabel value="Fecha emision" /><TextInput v-model="form.fecha_emision" type="date" class="mt-1 block w-full text-sm" /><InputError class="mt-1" :message="form.errors.fecha_emision" /></div>
                    <div><InputLabel value="Fecha vencimiento" /><TextInput v-model="form.fecha_vencimiento" type="date" class="mt-1 block w-full text-sm" /><InputError class="mt-1" :message="form.errors.fecha_vencimiento" /></div>
                    <div class="sm:col-span-3"><InputLabel value="Observacion" /><TextInput v-model="form.observacion" type="text" class="mt-1 block w-full text-sm" /><InputError class="mt-1" :message="form.errors.observacion" /></div>
                    <div class="sm:col-span-4 rounded-lg border border-indigo-200 bg-indigo-50 p-2 text-sm text-indigo-900">
                        Subtotal {{ summary.subtotal }} · IVA {{ summary.iva }} · Tributos {{ summary.tributos }} · Retenciones {{ summary.retenciones }} · Total {{ summary.total }}
                    </div>
                    <div class="sm:col-span-4 flex justify-end"><PrimaryButton :disabled="form.processing">Guardar</PrimaryButton></div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-3 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Comprobantes cargados</h3></div>
                <div class="space-y-3 p-3 sm:hidden">
                    <div v-for="c in comprobantes.data" :key="c.id" class="rounded-lg border border-gray-200 bg-white p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ c.cuenta?.tercero?.razon_social || '-' }}</div>
                                <div class="text-xs text-gray-500">{{ String(c.fecha_emision || '').slice(0,10) }} · {{ tipoLabel(c.tipo) }}</div>
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ c.moneda }} {{ c.total }}</div>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">PV</div>
                                <div class="font-medium text-gray-900">{{ parsePv(c.numero) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Nro</div>
                                <div class="font-medium text-gray-900 font-mono">{{ parseNro(c.numero) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Subtotal</div>
                                <div class="font-medium text-gray-900">$ {{ formatNum(c.subtotal) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">IVA</div>
                                <div class="font-medium text-green-700">$ {{ formatNum(c.iva_total) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Tributos</div>
                                <div class="font-medium text-gray-900">$ {{ formatNum(c.tributos_total) }}</div>
                            </div>
                        </div>
                        <div class="mt-2 flex gap-3">
                            <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link>
                            <button type="button" class="text-sm text-gray-700 hover:text-gray-900" @click.prevent="openEditComprobante(c)">Editar</button>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PV</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nro</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">IVA</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tributos</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes.data" :key="c.id"><td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">{{ String(c.fecha_emision || '').slice(0,10) }}</td><td class="px-4 py-2 text-sm text-gray-700">{{ c.cuenta?.tercero?.razon_social || '-' }}</td><td class="px-4 py-2 text-sm text-gray-700">{{ tipoLabel(c.tipo) }}</td><td class="px-4 py-2 text-sm text-gray-700">{{ parsePv(c.numero) }}</td><td class="px-4 py-2 text-sm text-gray-700 font-mono">{{ parseNro(c.numero) }}</td><td class="px-4 py-2 text-sm text-gray-700 text-right">$ {{ formatNum(c.subtotal) }}</td><td class="px-4 py-2 text-sm text-green-700 text-right">$ {{ formatNum(c.iva_total) }}</td><td class="px-4 py-2 text-sm text-gray-700 text-right">$ {{ formatNum(c.tributos_total) }}</td><td class="px-4 py-2 text-sm text-gray-900 font-semibold text-right">$ {{ formatNum(c.total) }}</td><td class="px-4 py-2 text-right text-sm"><Link class="text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link><button type="button" class="ms-2 text-gray-700 hover:text-gray-900" @click.prevent="openEditComprobante(c)">Editar</button></td></tr></tbody>
                    </table>
                </div>
            </div>

            <DialogModal :show="editComprobanteDialog" @close="editComprobanteDialog = false">
                <template #title>Editar comprobante proveedor</template>
                <template #content>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><InputLabel value="Tipo" /><select v-model="editComprobanteForm.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">(seleccionar tipo)</option><option v-for="t in tiposArca" :key="t.code" :value="t.code">{{ t.label }}</option></select><InputError class="mt-1" :message="editComprobanteForm.errors.tipo" /></div>
                            <div><InputLabel value="Numero" /><TextInput v-model="editComprobanteForm.numero" type="text" class="mt-1 block w-full text-sm" /><InputError class="mt-1" :message="editComprobanteForm.errors.numero" /></div>
                            <div><InputLabel value="Moneda" /><select v-model="editComprobanteForm.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-1" :message="editComprobanteForm.errors.moneda" /></div>
                            <div v-if="editComprobanteForm.tipo && editComprobanteForm.tipo.endsWith('A')" class="sm:col-span-2 rounded-lg border border-gray-200 p-2">
                                <div class="flex items-center justify-between gap-4"><h4 class="text-sm font-semibold text-gray-900">IVA</h4><SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="addIvaItem(editComprobanteForm)">Agregar IVA</SecondaryButton></div>
                                <div class="mt-2 space-y-2"><div v-for="(item, index) in editComprobanteForm.iva_items" :key="index" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end"><div><InputLabel value="Alicuota" /><select v-model="item.alicuota" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option :value="27">27%</option><option :value="21">21%</option><option :value="10.5">10.5%</option><option :value="5">5%</option><option :value="2.5">2.5%</option><option :value="0">0%</option></select></div><div><InputLabel value="Base imponible" /><TextInput v-model="item.base_imponible" type="number" min="0" step="0.01" class="mt-1 block w-full text-sm" /></div><div class="flex items-end gap-2"><div class="text-sm text-gray-700">IVA {{ (Number(item.base_imponible || 0) * Number(item.alicuota || 0) / 100).toFixed(2) }}</div><button v-if="editComprobanteForm.iva_items.length > 1" type="button" class="text-sm text-red-600" @click="removeAt(editComprobanteForm.iva_items, index)">Quitar</button></div></div></div>
                            </div>
                            <div v-if="editComprobanteForm.tipo && !editComprobanteForm.tipo.endsWith('A')" class="sm:col-span-2 rounded-lg border border-gray-200 p-3">
                                <InputLabel value="Subtotal / Importe (IVA incluido)" />
                                <TextInput v-model="editComprobanteForm.subtotal" type="number" min="0" step="0.01" class="mt-2 block w-full text-sm" />
                            </div>
                            <div class="rounded-lg border border-gray-200 p-2">
                                <div class="flex items-center justify-between gap-4 mb-2"><h4 class="text-sm font-semibold text-gray-900">Percepciones</h4><SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="addPercepcion(editComprobanteForm)">Agregar</SecondaryButton></div>
                                <div class="space-y-2"><div v-for="(item, index) in editComprobanteForm.percepciones" :key="index" class="grid grid-cols-1 gap-2"><select v-model="item.concepto" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">(concepto)</option><option v-for="c in (catalogosImpuestos?.percepciones || catalogos?.percepciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select><div class="flex items-end gap-2"><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="block w-full text-sm" placeholder="Importe" /><button type="button" class="text-sm text-red-600" @click="removeAt(editComprobanteForm.percepciones, index)">Quitar</button></div></div></div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-2">
                                <div class="flex items-center justify-between gap-4 mb-2"><h4 class="text-sm font-semibold text-gray-900">Retenciones</h4><SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="addRetencion(editComprobanteForm)">Agregar</SecondaryButton></div>
                                <div class="space-y-2"><div v-for="(item, index) in editComprobanteForm.retenciones" :key="index" class="grid grid-cols-1 gap-2"><select v-model="item.concepto" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">(concepto)</option><option v-for="c in (catalogosImpuestos?.retenciones || catalogos?.retenciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select><div class="flex items-end gap-2"><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="block w-full text-sm" placeholder="Importe" /><button type="button" class="text-sm text-red-600" @click="removeAt(editComprobanteForm.retenciones, index)">Quitar</button></div></div></div>
                            </div>
                            <div class="sm:col-span-2 rounded-lg border border-gray-200 p-2">
                                <h4 class="text-sm font-semibold text-gray-900 mb-2">Combustible</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel value="Tipo combustible" />
                                        <select v-model="editComprobanteForm.combustible_tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                            <option value="">(seleccionar)</option>
                                            <option v-for="t in TIPOS_COMBUSTIBLE" :key="t.value" :value="t.value">{{ t.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="Litros" />
                                        <TextInput v-model="editComprobanteForm.litros_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full text-sm" />
                                    </div>
                                    <div>
                                        <InputLabel value="Tasa x litro" />
                                        <div class="mt-1 text-sm font-medium" :class="tasaActualCombustible > 0 ? 'text-gray-700' : 'text-yellow-700'">{{ editComprobanteForm.combustible_tipo && Number(editComprobanteForm.litros_combustible || 0) > 0 ? (tasaActualCombustible > 0 ? `$${tasaActualCombustible.toFixed(4)}` : 'Sin tasa configurada') : '-' }}</div>
                                    </div>
                                    <div>
                                        <InputLabel value="Pago a cuenta" />
                                        <div class="mt-1 text-sm font-semibold text-gray-900">{{ editComprobanteForm.pago_cuenta_combustible ? `$${editComprobanteForm.pago_cuenta_combustible}` : '-' }}</div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <InputLabel value="Impuestos combustible" />
                                    <TextInput v-model="editComprobanteForm.impuestos_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full text-sm" />
                                </div>
                            </div>
                            <div><InputLabel value="Fecha emision" /><TextInput v-model="editComprobanteForm.fecha_emision" type="date" class="mt-1 block w-full text-sm" /><InputError class="mt-1" :message="editComprobanteForm.errors.fecha_emision" /></div>
                            <div><InputLabel value="Fecha vencimiento" /><TextInput v-model="editComprobanteForm.fecha_vencimiento" type="date" class="mt-1 block w-full text-sm" /><InputError class="mt-1" :message="editComprobanteForm.errors.fecha_vencimiento" /></div>
                            <div class="sm:col-span-2"><InputLabel value="Observacion" /><TextInput v-model="editComprobanteForm.observacion" type="text" class="mt-1 block w-full text-sm" /><InputError class="mt-1" :message="editComprobanteForm.errors.observacion" /></div>
                            <div class="sm:col-span-2 rounded-lg border border-indigo-200 bg-indigo-50 p-2 text-sm text-indigo-900">
                                Subtotal {{ editSummary.subtotal }} · IVA {{ editSummary.iva }} · Tributos {{ editSummary.tributos }} · Retenciones {{ editSummary.retenciones }} · Total {{ editSummary.total }}
                            </div>
                        </div>
                </template>
                <template #footer>
                    <SecondaryButton class="!text-xs !px-3 !py-1.5" @click="editComprobanteDialog = false">Cancelar</SecondaryButton>
                    <PrimaryButton class="ms-3 !text-xs !px-3 !py-1.5" :disabled="editComprobanteForm.processing" @click="submitEditComprobante">Guardar cambios</PrimaryButton>
                </template>
            </DialogModal>
            <PdfImportDialog v-model:show="pdfImportDialog" @imported="onPdfImported" />
        </div>
    </AppLayout>
</template>
