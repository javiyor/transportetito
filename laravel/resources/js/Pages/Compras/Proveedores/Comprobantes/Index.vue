<script setup>
import { Head, useForm, usePage, Link, router } from '@inertiajs/vue3';
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
        'FACTURA_A': 'Factura A', 'FACTURA_B': 'Factura B', 'FACTURA_C': 'Factura C',
        'FACTURA_E': 'Factura E', 'FACTURA_M': 'Factura M',
        'FACTURA_CREDITO_A': 'Factura Crédito A', 'FACTURA_CREDITO_B': 'Factura Crédito B', 'FACTURA_CREDITO_C': 'Factura Crédito C',
        'NOTA_DEBITO_A': 'ND A', 'NOTA_DEBITO_B': 'ND B', 'NOTA_DEBITO_C': 'ND C',
        'NOTA_DEBITO_E': 'ND E', 'NOTA_DEBITO_M': 'ND M',
        'NOTA_CREDITO_A': 'NC A', 'NOTA_CREDITO_B': 'NC B', 'NOTA_CREDITO_C': 'NC C',
        'NOTA_CREDITO_E': 'NC E', 'NOTA_CREDITO_M': 'NC M',
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
const page = usePage();

const props = defineProps({
    proveedores: Array,
    comprobantes: Object,
    catalogos: Object,
    resumen: Object,
    cuentasContables: Array,
    filtros: Object,
});

const filtroFechaDesde = ref(props.filtros?.fecha_desde || '');
const filtroFechaHasta = ref(props.filtros?.fecha_hasta || '');
const filtroProveedorId = ref(props.filtros?.proveedor_id || '');

const aplicarFiltros = () => {
    router.get(route('compras.proveedores.comprobantes.index'), {
        fecha_desde: filtroFechaDesde.value || null,
        fecha_hasta: filtroFechaHasta.value || null,
        proveedor_id: filtroProveedorId.value || null,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const limpiarFiltros = () => {
    filtroFechaDesde.value = '';
    filtroFechaHasta.value = '';
    filtroProveedorId.value = '';
    aplicarFiltros();
};

const searchCuentaContable = ref('');
const searchCuentaContableEdit = ref('');
const filtrarCuentasContables = (q) => {
    const s = String(q || '').toLowerCase().trim();
    if (!s) return props.cuentasContables || [];
    return (props.cuentasContables || []).filter((c) => `${c.codigo || ''} ${c.nombre || ''}`.toLowerCase().includes(s));
};
const cuentasContablesFiltradas = computed(() => filtrarCuentasContables(searchCuentaContable.value));
const cuentasContablesFiltradasEdit = computed(() => filtrarCuentasContables(searchCuentaContableEdit.value));

const form = useForm({
    tercero_cuenta_id: '',
    proveedor_cuit_busqueda: '',
    receptor_cuit: '',
    tipo: '',
    numero: '',
    moneda: 'ARS',
    cuenta_contable_id: '',
    subtotal: '',
    iva_detalle: [
        { concepto: 'neto_21', importe: '' },
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
    tercero_cuenta_id: '',
    proveedor_cuit_busqueda: '',
    tipo: '',
    numero: '',
    moneda: 'ARS',
    cuenta_contable_id: '',
    subtotal: '',
    iva_detalle: [{ concepto: 'neto_21', importe: '' }],
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
        for (const item of (target.iva_detalle || [])) {
            const imp = Number(item.importe || 0);
            subtotal += imp;
            const tasa = IVA_DETALLE_TASAS[item.concepto];
            if (tasa !== undefined) {
                iva += Math.round((imp * (tasa / 100) + Number.EPSILON) * 100) / 100;
            }
        }
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

const IVA_DETALLE_TASAS = { neto_27: 27, neto_21: 21, neto_105: 10.5, neto_5: 5, neto_25: 2.5, neto_0: 0 };
const ivaDetalleOpciones = computed(() => {
    const fromSrv = catalogosImpuestos.value?.iva_detalle?.length
        ? catalogosImpuestos.value.iva_detalle
        : (props.catalogos?.iva_detalle?.length ? props.catalogos.iva_detalle : null);
    if (fromSrv) return fromSrv;
    return [
        { value: 'neto_21', label: 'Neto 21%' },
        { value: 'neto_105', label: 'Neto 10,5%' },
        { value: 'neto_27', label: 'Neto 27%' },
        { value: 'neto_5', label: 'Neto 5%' },
        { value: 'neto_25', label: 'Neto 2,5%' },
        { value: 'neto_0', label: 'Neto 0%' },
        { value: 'no_gravado', label: 'Neto no gravado' },
        { value: 'exento', label: 'Op. exentas' },
    ];
});
const conceptoDeAlicuota = (a) => {
    const n = Number(a);
    if (n === 27) return 'neto_27';
    if (n === 21) return 'neto_21';
    if (n === 10.5) return 'neto_105';
    if (n === 5) return 'neto_5';
    if (n === 2.5) return 'neto_25';
    return 'neto_0';
};

const addIvaItem = (target) => target.iva_detalle.push({ concepto: 'neto_21', importe: '' });
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

watch(() => editComprobanteForm.tercero_cuenta_id, (val) => {
    if (!editComprobanteDialog.value) return;
    fetchTiposArca(val, true);
});

watch(() => form.tipo, (tipo) => {
    if (!tipo) return;
    if (tipo.endsWith('A')) {
        form.subtotal = '';
    } else {
        form.iva_detalle = [{ concepto: 'neto_21', importe: '' }];
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

const currentEmpresaCuit = computed(() => page.props.tt?.currentEmpresa?.cuit || '');

const onPdfImported = (datos) => {
    if (datos.cuit_receptor && currentEmpresaCuit.value) {
        const rec = String(datos.cuit_receptor).replace(/\D/g, '');
        const emp = String(currentEmpresaCuit.value).replace(/\D/g, '');
        if (rec && rec !== emp) {
            alert('El CUIT receptor del PDF no coincide con la empresa activa. No se cargarán los datos.');
            return;
        }
    }

    form.receptor_cuit = datos.cuit_receptor || '';

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
        form.iva_detalle = datos.iva_items.map((item) => ({
            concepto: conceptoDeAlicuota(item.alicuota),
            importe: String(item.base_imponible || item.importe * 100 / item.alicuota),
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

const buscarProveedorPorCuitEdit = async () => {
    const cuit = String(editComprobanteForm.proveedor_cuit_busqueda || '').trim();
    if (!cuit) return;

    const url = route('compras.proveedores.lookup-cuit', { cuit });
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const data = await res.json();

    if (data.cuenta?.id) {
        editComprobanteForm.tercero_cuenta_id = data.cuenta.id;
        fetchTiposArca(data.cuenta.id, true);
    } else {
        window.location.href = route('admin.terceros.index', { cuit, tipo: 'proveedor' });
    }
};

const openEditComprobante = (c) => {
    editComprobanteId.value = c.id;
    editComprobanteForm.tercero_cuenta_id = c.tercero_cuenta_id || '';
    editComprobanteForm.proveedor_cuit_busqueda = c.cuenta?.tercero?.cuit || '';
    fetchTiposArca(c.tercero_cuenta_id, true);
    editComprobanteForm.tipo = c.tipo || '';
    editComprobanteForm.numero = c.numero || '';
    editComprobanteForm.moneda = c.moneda || 'ARS';
    editComprobanteForm.cuenta_contable_id = c.cuenta_contable_id || '';
    editComprobanteForm.subtotal = c.subtotal || '';
    if (c.detalle?.iva_detalle?.length) {
        editComprobanteForm.iva_detalle = c.detalle.iva_detalle.map((x) => ({ concepto: x.concepto, importe: x.importe }));
    } else {
        const rows = (c.detalle?.iva_items || []).map((x) => ({ concepto: conceptoDeAlicuota(x.alicuota), importe: x.base_imponible }));
        if (Number(c.detalle?.neto_no_gravado || 0) > 0) rows.push({ concepto: 'no_gravado', importe: c.detalle.neto_no_gravado });
        if (Number(c.detalle?.op_exentas || 0) > 0) rows.push({ concepto: 'exento', importe: c.detalle.op_exentas });
        editComprobanteForm.iva_detalle = rows.length ? rows : [{ concepto: 'neto_21', importe: '' }];
    }
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
    searchCuentaContableEdit.value = '';
    editComprobanteDialog.value = true;
};

const submitEditComprobante = () => {
    editComprobanteForm.put(route('compras.proveedores.comprobantes.update', editComprobanteId.value), {
        preserveScroll: true,
        onSuccess: () => { editComprobanteDialog.value = false; },
    });
};

const confirmDeleteId = ref(null);
const deleteForm = useForm({ password: '' });

const openDeleteConfirm = (id) => {
    confirmDeleteId.value = id;
    deleteForm.password = '';
    deleteForm.clearErrors();
};

const submitDelete = () => {
    deleteForm.delete(route('compras.proveedores.comprobantes.destroy', confirmDeleteId.value), {
        preserveScroll: true,
        onSuccess: () => { confirmDeleteId.value = null; },
        onError: () => { /* keep modal open */ },
    });
};
</script>

<template>
    <AppLayout title="Compras / Proveedores / Comprobantes">
        <Head title="Compras / Proveedores / Comprobantes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-lg text-gray-800 leading-tight">Compras / Proveedores / Comprobantes</h2>
                <div class="flex items-center gap-3">
                    <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800" @click.prevent="pdfImportDialog = true">Importar PDF</button>
                    <a class="text-xs text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.export')">Exportar CSV</a>
                    <Link class="text-xs text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.ctacte.index')">Cta. cte. proveedores</Link>
                    <Link class="text-xs text-indigo-600 hover:text-indigo-800" :href="route('compras.combustibles.index')">Combustibles</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-2 sm:px-6 lg:px-8 space-y-2">
            <div class="bg-white shadow sm:rounded-lg p-2">
                <h3 class="text-xs font-semibold text-gray-900 mb-2">Nuevo comprobante proveedor</h3>
                <form class="grid grid-cols-1 sm:grid-cols-4 gap-2 grilla-campos" @submit.prevent="submit">
                    <div class="sm:col-span-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end rounded-lg border border-gray-200 bg-gray-50 p-2">
                        <div class="sm:col-span-2">
                            <InputLabel value="Buscar proveedor por CUIT" />
                            <TextInput v-model="form.proveedor_cuit_busqueda" type="text" class="mt-1 block w-full text-xs" placeholder="CUIT" />
                        </div>
                        <div class="flex gap-2 sm:col-span-2">
                            <SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="buscarProveedorPorCuit">Buscar CUIT</SecondaryButton>
                            <Link :href="route('admin.terceros.index', { tipo: 'proveedor' })" class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">Nuevo proveedor</Link>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Proveedor" />
                        <select v-model="form.tercero_cuenta_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs">
                            <option value="">(seleccionar)</option>
                            <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.tercero?.razon_social || p.nombre_cuenta || ('#' + p.id) }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.tercero_cuenta_id" />
                    </div>
                    <div>
                        <InputLabel value="Tipo" />
                        <select v-model="form.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs">
                            <option value="">(seleccionar tipo)</option>
                            <option v-for="t in tiposArca" :key="t.code" :value="t.code">{{ t.label }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.tipo" />
                    </div>
                    <div><InputLabel value="Numero" /><TextInput v-model="form.numero" type="text" class="mt-1 block w-full text-xs" /><InputError class="mt-1" :message="form.errors.numero" /></div>
                    <div><InputLabel value="Moneda" /><select v-model="form.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-1" :message="form.errors.moneda" /></div>
                    <div>
                        <InputLabel value="Cuenta contable" />
                        <TextInput v-model="searchCuentaContable" type="text" class="mt-1 block w-full text-xs" placeholder="Buscar por código o nombre..." />
                        <select v-model="form.cuenta_contable_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs">
                            <option value="">(predeterminada)</option>
                            <option v-for="c in cuentasContablesFiltradas" :key="c.id" :value="c.id">{{ c.codigo }} - {{ c.nombre }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.cuenta_contable_id" />
                    </div>
                    <div v-if="!form.tipo || form.tipo.endsWith('A')" class="sm:col-span-4 rounded-lg border border-gray-200 p-2">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="text-xs font-semibold text-gray-900">IVA / Netos</h4>
                            <SecondaryButton type="button" class="!text-xs !px-2 !py-1" @click="addIvaItem(form)">+ Fila</SecondaryButton>
                        </div>
                        <div class="mt-1 grid grid-cols-12 gap-1 text-xs font-medium text-gray-500 uppercase"><div class="col-span-5">Concepto</div><div class="col-span-3">Importe</div><div class="col-span-3">IVA</div><div></div></div>
                        <div class="mt-1 space-y-1">
                            <div v-for="(item, index) in form.iva_detalle" :key="index" class="grid grid-cols-12 gap-1 items-center">
                                <select v-model="item.concepto" class="col-span-5 block w-full border-gray-300 rounded-md shadow-sm text-xs py-1"><option v-for="c in ivaDetalleOpciones" :key="c.value" :value="c.value">{{ c.label }}</option></select>
                                <TextInput v-model="item.importe" type="number" min="0" step="0.01" class="col-span-3 block w-full text-xs !py-1" />
                                <div class="col-span-3 text-xs text-gray-700 text-right">{{ IVA_DETALLE_TASAS[item.concepto] !== undefined ? (Number(item.importe || 0) * IVA_DETALLE_TASAS[item.concepto] / 100).toFixed(2) : '-' }}</div>
                                <button v-if="form.iva_detalle.length > 1" type="button" class="text-xs text-red-600 font-bold" @click="removeAt(form.iva_detalle, index)">X</button><span v-else></span>
                            </div>
                        </div>
                    </div>
                    <div v-if="form.tipo && !form.tipo.endsWith('A')" class="sm:col-span-4 rounded-lg border border-gray-200 p-2">
                        <InputLabel value="Subtotal / Importe (IVA incluido)" />
                        <TextInput v-model="form.subtotal" type="number" min="0" step="0.01" class="mt-1 block w-full text-xs" />
                    </div>
                    <div class="sm:col-span-2 rounded-lg border border-gray-200 p-2">
                        <div class="flex items-center justify-between gap-2 mb-1"><h4 class="text-xs font-semibold text-gray-900">Percepciones</h4><SecondaryButton type="button" class="!text-xs !px-2 !py-1" @click="addPercepcion(form)">+ Fila</SecondaryButton></div>
                        <div class="grid grid-cols-12 gap-1 text-xs font-medium text-gray-500 uppercase"><div class="col-span-6">Concepto</div><div class="col-span-4">Importe</div><div></div></div>
                        <div class="mt-1 space-y-1"><div v-for="(item, index) in form.percepciones" :key="index" class="grid grid-cols-12 gap-1 items-center"><select v-model="item.concepto" class="col-span-6 block w-full border-gray-300 rounded-md shadow-sm text-xs py-1"><option value="">(concepto)</option><option v-for="c in (catalogosImpuestos?.percepciones || catalogos?.percepciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="col-span-4 block w-full text-xs !py-1" placeholder="Importe" /><button type="button" class="col-span-2 text-xs text-red-600 font-bold text-left" @click="removeAt(form.percepciones, index)">X</button></div></div>
                    </div>
                    <div class="sm:col-span-2 rounded-lg border border-gray-200 p-2">
                        <div class="flex items-center justify-between gap-2 mb-1"><h4 class="text-xs font-semibold text-gray-900">Retenciones</h4><SecondaryButton type="button" class="!text-xs !px-2 !py-1" @click="addRetencion(form)">+ Fila</SecondaryButton></div>
                        <div class="grid grid-cols-12 gap-1 text-xs font-medium text-gray-500 uppercase"><div class="col-span-6">Concepto</div><div class="col-span-4">Importe</div><div></div></div>
                        <div class="mt-1 space-y-1"><div v-for="(item, index) in form.retenciones" :key="index" class="grid grid-cols-12 gap-1 items-center"><select v-model="item.concepto" class="col-span-6 block w-full border-gray-300 rounded-md shadow-sm text-xs py-1"><option value="">(concepto)</option><option v-for="c in (catalogosImpuestos?.retenciones || catalogos?.retenciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="col-span-4 block w-full text-xs !py-1" placeholder="Importe" /><button type="button" class="col-span-2 text-xs text-red-600 font-bold text-left" @click="removeAt(form.retenciones, index)">X</button></div></div>
                    </div>
                    <div class="sm:col-span-4 rounded-lg border border-gray-200 p-2">
                        <h4 class="text-xs font-semibold text-gray-900 mb-2">Combustible</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div>
                                <InputLabel value="Tipo combustible" />
                                <select v-model="form.combustible_tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs">
                                    <option value="">(seleccionar)</option>
                                    <option v-for="t in TIPOS_COMBUSTIBLE" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Litros" />
                                <TextInput v-model="form.litros_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full text-xs" />
                            </div>
                            <div>
                                <InputLabel value="Tasa x litro ($)" />
                                <div class="mt-1 text-xs font-medium" :class="tasaActualCombustible > 0 ? 'text-gray-700' : 'text-yellow-700'">{{ form.combustible_tipo && Number(form.litros_combustible || 0) > 0 ? (tasaActualCombustible > 0 ? `$${tasaActualCombustible.toFixed(4)}` : 'Sin tasa configurada') : '-' }}</div>
                            </div>
                            <div>
                                <InputLabel value="Pago a cuenta" />
                                <div class="mt-1 text-xs font-semibold text-gray-900">{{ form.pago_cuenta_combustible ? `$${form.pago_cuenta_combustible}` : '-' }}</div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <InputLabel value="Impuestos combustible (adicional)" />
                            <TextInput v-model="form.impuestos_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full text-xs" />
                        </div>
                    </div>
                    <div><InputLabel value="Fecha emision" /><TextInput v-model="form.fecha_emision" type="date" class="mt-1 block w-full text-xs" /><InputError class="mt-1" :message="form.errors.fecha_emision" /></div>
                    <div><InputLabel value="Fecha vencimiento" /><TextInput v-model="form.fecha_vencimiento" type="date" class="mt-1 block w-full text-xs" /><InputError class="mt-1" :message="form.errors.fecha_vencimiento" /></div>
                    <div class="sm:col-span-3"><InputLabel value="Observacion" /><TextInput v-model="form.observacion" type="text" class="mt-1 block w-full text-xs" /><InputError class="mt-1" :message="form.errors.observacion" /></div>
                    <div class="sm:col-span-4 rounded-lg border border-indigo-200 bg-indigo-50 p-2 text-xs text-indigo-900 resumen-total">
                        Subtotal {{ summary.subtotal }} · IVA {{ summary.iva }} · Tributos {{ summary.tributos }} · Retenciones {{ summary.retenciones }} · Total {{ summary.total }}
                    </div>
                    <div class="sm:col-span-4 flex justify-end"><PrimaryButton :disabled="form.processing">Guardar</PrimaryButton></div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-2 border-b border-gray-200">
                    <div class="flex flex-wrap items-end gap-2">
                        <h3 class="text-xs font-semibold text-gray-900 me-2">Comprobantes cargados</h3>
                        <div>
                            <InputLabel value="Proveedor" />
                            <select v-model="filtroProveedorId" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs">
                                <option value="">Todos</option>
                                <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.tercero?.razon_social || p.nombre_cuenta || ('#' + p.id) }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Desde" />
                            <TextInput v-model="filtroFechaDesde" type="date" class="mt-1 block w-full text-xs" />
                        </div>
                        <div>
                            <InputLabel value="Hasta" />
                            <TextInput v-model="filtroFechaHasta" type="date" class="mt-1 block w-full text-xs" />
                        </div>
                        <div class="flex gap-2">
                            <SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="aplicarFiltros">Filtrar</SecondaryButton>
                            <button v-if="filtros?.fecha_desde || filtros?.fecha_hasta || filtros?.proveedor_id" type="button" class="text-xs text-gray-500 hover:text-gray-800 underline" @click="limpiarFiltros">Limpiar</button>
                        </div>
                    </div>
                </div>
                <div class="px-2 py-1.5 grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs bg-gray-50 border-b border-gray-200">
                    <div><span class="text-gray-500">Subtotal $ </span><span class="font-medium text-gray-900">{{ formatNum(resumen?.subtotal || 0) }}</span></div>
                    <div><span class="text-gray-500">IVA $ </span><span class="font-medium text-gray-900">{{ formatNum(resumen?.iva_total || 0) }}</span></div>
                    <div><span class="text-gray-500">Tributos $ </span><span class="font-medium text-gray-900">{{ formatNum(resumen?.tributos_total || 0) }}</span></div>
                    <div><span class="text-gray-500">Retenciones $ </span><span class="font-medium text-gray-900">{{ formatNum(resumen?.retenciones_total || 0) }}</span></div>
                    <div><span class="text-gray-500">Total $ </span><span class="font-semibold text-gray-900">{{ formatNum(resumen?.total || 0) }}</span></div>
                </div>
                <div class="space-y-2 p-2 sm:hidden">
                    <div v-for="c in comprobantes.data" :key="c.id" class="rounded-lg border border-gray-200 bg-white p-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-xs font-semibold text-gray-900">{{ c.cuenta?.tercero?.razon_social || '-' }}</div>
                                <div class="text-xs text-gray-500">{{ String(c.fecha_emision || '').slice(0,10) }} · {{ tipoLabel(c.tipo) }}</div>
                            </div>
                            <div class="text-xs font-medium text-gray-900">{{ c.moneda }} {{ c.total }}</div>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
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
                            <Link class="text-xs text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link>
                            <button type="button" class="text-xs text-gray-700 hover:text-gray-900" @click.prevent="openEditComprobante(c)">Editar</button>
                            <button type="button" class="text-xs text-red-600 hover:text-red-800" @click.prevent="openDeleteConfirm(c.id)">Eliminar</button>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th><th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PV</th><th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nro</th><th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th><th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">IVA</th><th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tributos</th><th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes.data" :key="c.id"><td class="px-2 py-1 text-xs text-gray-700 whitespace-nowrap">{{ String(c.fecha_emision || '').slice(0,10) }}</td><td class="px-2 py-1 text-xs text-gray-700">{{ c.cuenta?.tercero?.razon_social || '-' }}</td><td class="px-2 py-1 text-xs text-gray-700">{{ tipoLabel(c.tipo) }}</td><td class="px-2 py-1 text-xs text-gray-700">{{ parsePv(c.numero) }}</td><td class="px-2 py-1 text-xs text-gray-700 font-mono">{{ parseNro(c.numero) }}</td><td class="px-2 py-1 text-xs text-gray-700 text-right">$ {{ formatNum(c.subtotal) }}</td><td class="px-2 py-1 text-xs text-green-700 text-right">$ {{ formatNum(c.iva_total) }}</td><td class="px-2 py-1 text-xs text-gray-700 text-right">$ {{ formatNum(c.tributos_total) }}</td><td class="px-2 py-1 text-xs text-gray-900 font-semibold text-right">$ {{ formatNum(c.total) }}</td><td class="px-2 py-1 text-right text-xs"><Link class="text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.show', c.id)">Ver</Link><button type="button" class="ms-2 text-gray-700 hover:text-gray-900" @click.prevent="openEditComprobante(c)">Editar</button><button type="button" class="ms-2 text-red-600 hover:text-red-800" @click.prevent="openDeleteConfirm(c.id)">Eliminar</button></td></tr></tbody>
                    </table>
                </div>
            </div>

            <DialogModal :show="editComprobanteDialog" @close="editComprobanteDialog = false">
                <template #title>Editar comprobante proveedor</template>
                <template #content>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 grilla-campos">
                            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3 items-end rounded-lg border border-gray-200 bg-gray-50 p-2">
                                <div>
                                    <InputLabel value="Buscar proveedor por CUIT" />
                                    <TextInput v-model="editComprobanteForm.proveedor_cuit_busqueda" type="text" class="mt-1 block w-full text-xs" placeholder="CUIT" />
                                </div>
                                <div>
                                    <SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="buscarProveedorPorCuitEdit">Buscar CUIT</SecondaryButton>
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Proveedor" />
                                <select v-model="editComprobanteForm.tercero_cuenta_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs">
                                    <option value="">(seleccionar)</option>
                                    <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.tercero?.razon_social || p.nombre_cuenta || ('#' + p.id) }}</option>
                                </select>
                                <InputError class="mt-1" :message="editComprobanteForm.errors.tercero_cuenta_id" />
                            </div>
                            <div><InputLabel value="Tipo" /><select v-model="editComprobanteForm.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs"><option value="">(seleccionar tipo)</option><option v-for="t in tiposArca" :key="t.code" :value="t.code">{{ t.label }}</option></select><InputError class="mt-1" :message="editComprobanteForm.errors.tipo" /></div>
                            <div><InputLabel value="Numero" /><TextInput v-model="editComprobanteForm.numero" type="text" class="mt-1 block w-full text-xs" /><InputError class="mt-1" :message="editComprobanteForm.errors.numero" /></div>
                            <div><InputLabel value="Moneda" /><select v-model="editComprobanteForm.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-1" :message="editComprobanteForm.errors.moneda" /></div>
                            <div>
                                <InputLabel value="Cuenta contable" />
                                <TextInput v-model="searchCuentaContableEdit" type="text" class="mt-1 block w-full text-xs" placeholder="Buscar por código o nombre..." />
                                <select v-model="editComprobanteForm.cuenta_contable_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs">
                                    <option value="">(predeterminada)</option>
                                    <option v-for="c in cuentasContablesFiltradasEdit" :key="c.id" :value="c.id">{{ c.codigo }} - {{ c.nombre }}</option>
                                </select>
                                <InputError class="mt-1" :message="editComprobanteForm.errors.cuenta_contable_id" />
                            </div>
                            <div v-if="!editComprobanteForm.tipo || editComprobanteForm.tipo.endsWith('A')" class="sm:col-span-2 rounded-lg border border-gray-200 p-2">
                                <div class="flex items-center justify-between gap-2"><h4 class="text-xs font-semibold text-gray-900">IVA / Netos</h4><SecondaryButton type="button" class="!text-xs !px-2 !py-1" @click="addIvaItem(editComprobanteForm)">+ Fila</SecondaryButton></div>
                                <div class="mt-1 grid grid-cols-12 gap-1 text-xs font-medium text-gray-500 uppercase"><div class="col-span-5">Concepto</div><div class="col-span-3">Importe</div><div class="col-span-3">IVA</div><div></div></div>
                                <div class="mt-1 space-y-1"><div v-for="(item, index) in editComprobanteForm.iva_detalle" :key="index" class="grid grid-cols-12 gap-1 items-center"><select v-model="item.concepto" class="col-span-5 block w-full border-gray-300 rounded-md shadow-sm text-xs py-1"><option v-for="c in ivaDetalleOpciones" :key="c.value" :value="c.value">{{ c.label }}</option></select><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="col-span-3 block w-full text-xs !py-1" /><div class="col-span-3 text-xs text-gray-700 text-right">{{ IVA_DETALLE_TASAS[item.concepto] !== undefined ? (Number(item.importe || 0) * IVA_DETALLE_TASAS[item.concepto] / 100).toFixed(2) : '-' }}</div><button v-if="editComprobanteForm.iva_detalle.length > 1" type="button" class="text-xs text-red-600 font-bold" @click="removeAt(editComprobanteForm.iva_detalle, index)">X</button><span v-else></span></div></div>
                            </div>
                            <div v-if="editComprobanteForm.tipo && !editComprobanteForm.tipo.endsWith('A')" class="sm:col-span-2 rounded-lg border border-gray-200 p-2">
                                <InputLabel value="Subtotal / Importe (IVA incluido)" />
                                <TextInput v-model="editComprobanteForm.subtotal" type="number" min="0" step="0.01" class="mt-2 block w-full text-xs" />
                            </div>
                            <div class="rounded-lg border border-gray-200 p-2">
                                <div class="flex items-center justify-between gap-2 mb-1"><h4 class="text-xs font-semibold text-gray-900">Percepciones</h4><SecondaryButton type="button" class="!text-xs !px-2 !py-1" @click="addPercepcion(editComprobanteForm)">+ Fila</SecondaryButton></div>
                                <div class="grid grid-cols-12 gap-1 text-xs font-medium text-gray-500 uppercase"><div class="col-span-6">Concepto</div><div class="col-span-4">Importe</div><div></div></div>
                                <div class="mt-1 space-y-1"><div v-for="(item, index) in editComprobanteForm.percepciones" :key="index" class="grid grid-cols-12 gap-1 items-center"><select v-model="item.concepto" class="col-span-6 block w-full border-gray-300 rounded-md shadow-sm text-xs py-1"><option value="">(concepto)</option><option v-for="c in (catalogosImpuestos?.percepciones || catalogos?.percepciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="col-span-4 block w-full text-xs !py-1" placeholder="Importe" /><button type="button" class="col-span-2 text-xs text-red-600 font-bold text-left" @click="removeAt(editComprobanteForm.percepciones, index)">X</button></div></div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-2">
                                <div class="flex items-center justify-between gap-2 mb-1"><h4 class="text-xs font-semibold text-gray-900">Retenciones</h4><SecondaryButton type="button" class="!text-xs !px-2 !py-1" @click="addRetencion(editComprobanteForm)">+ Fila</SecondaryButton></div>
                                <div class="grid grid-cols-12 gap-1 text-xs font-medium text-gray-500 uppercase"><div class="col-span-6">Concepto</div><div class="col-span-4">Importe</div><div></div></div>
                                <div class="mt-1 space-y-1"><div v-for="(item, index) in editComprobanteForm.retenciones" :key="index" class="grid grid-cols-12 gap-1 items-center"><select v-model="item.concepto" class="col-span-6 block w-full border-gray-300 rounded-md shadow-sm text-xs py-1"><option value="">(concepto)</option><option v-for="c in (catalogosImpuestos?.retenciones || catalogos?.retenciones || [])" :key="c.value" :value="c.label">{{ c.label }}</option></select><TextInput v-model="item.importe" type="number" min="0" step="0.01" class="col-span-4 block w-full text-xs !py-1" placeholder="Importe" /><button type="button" class="col-span-2 text-xs text-red-600 font-bold text-left" @click="removeAt(editComprobanteForm.retenciones, index)">X</button></div></div>
                            </div>
                            <div class="sm:col-span-2 rounded-lg border border-gray-200 p-2">
                                <h4 class="text-xs font-semibold text-gray-900 mb-2">Combustible</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel value="Tipo combustible" />
                                        <select v-model="editComprobanteForm.combustible_tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-xs">
                                            <option value="">(seleccionar)</option>
                                            <option v-for="t in TIPOS_COMBUSTIBLE" :key="t.value" :value="t.value">{{ t.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="Litros" />
                                        <TextInput v-model="editComprobanteForm.litros_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full text-xs" />
                                    </div>
                                    <div>
                                        <InputLabel value="Tasa x litro" />
                                        <div class="mt-1 text-xs font-medium" :class="tasaActualCombustible > 0 ? 'text-gray-700' : 'text-yellow-700'">{{ editComprobanteForm.combustible_tipo && Number(editComprobanteForm.litros_combustible || 0) > 0 ? (tasaActualCombustible > 0 ? `$${tasaActualCombustible.toFixed(4)}` : 'Sin tasa configurada') : '-' }}</div>
                                    </div>
                                    <div>
                                        <InputLabel value="Pago a cuenta" />
                                        <div class="mt-1 text-xs font-semibold text-gray-900">{{ editComprobanteForm.pago_cuenta_combustible ? `$${editComprobanteForm.pago_cuenta_combustible}` : '-' }}</div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <InputLabel value="Impuestos combustible" />
                                    <TextInput v-model="editComprobanteForm.impuestos_combustible" type="number" min="0" step="0.01" class="mt-1 block w-full text-xs" />
                                </div>
                            </div>
                            <div><InputLabel value="Fecha emision" /><TextInput v-model="editComprobanteForm.fecha_emision" type="date" class="mt-1 block w-full text-xs" /><InputError class="mt-1" :message="editComprobanteForm.errors.fecha_emision" /></div>
                            <div><InputLabel value="Fecha vencimiento" /><TextInput v-model="editComprobanteForm.fecha_vencimiento" type="date" class="mt-1 block w-full text-xs" /><InputError class="mt-1" :message="editComprobanteForm.errors.fecha_vencimiento" /></div>
                            <div class="sm:col-span-2"><InputLabel value="Observacion" /><TextInput v-model="editComprobanteForm.observacion" type="text" class="mt-1 block w-full text-xs" /><InputError class="mt-1" :message="editComprobanteForm.errors.observacion" /></div>
                            <div class="sm:col-span-2 rounded-lg border border-indigo-200 bg-indigo-50 p-2 text-xs text-indigo-900 resumen-total">
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

            <DialogModal :show="!!confirmDeleteId" @close="confirmDeleteId = null">
                <template #title>Eliminar comprobante</template>
                <template #content>
                    <p class="text-xs text-gray-700 mb-4">Ingrese su clave de administrador para confirmar la eliminacion.</p>
                    <InputLabel value="Clave" />
                    <TextInput v-model="deleteForm.password" type="password" class="mt-1 block w-full text-xs" />
                    <InputError class="mt-2" :message="deleteForm.errors.password" />
                </template>
                <template #footer>
                    <SecondaryButton class="!text-xs !px-3 !py-1.5" @click="confirmDeleteId = null">Cancelar</SecondaryButton>
                    <button type="button" class="ms-3 inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2" :disabled="deleteForm.processing" @click="submitDelete">Eliminar</button>
                </template>
            </DialogModal>
        </div>
    </AppLayout>
</template>

<style scoped>
.grilla-campos > div:not(.resumen-total) {
    background-color: rgb(249 250 251 / 0.7);
    border: 1px solid rgb(229 231 235);
    border-radius: 0.375rem;
    padding: 0.375rem 0.5rem;
}
</style>
