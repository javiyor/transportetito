<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    empresa: Object,
    cuentas: Array,
    condicionesIva: Array,
    tarifaDefaults: Object,
});
const tarifaActiva = ref({ ...(props.tarifaDefaults || {}) });

const page = usePage();
const flashSuccess = computed(() => page.props.tt?.flash?.success || page.props.flash?.success || null);
const flashError = computed(() => page.props.tt?.flash?.error || page.props.flash?.error || null);

const defaultPct = Number(props.tarifaDefaults?.tarifa_valor_declarado_pct) || 0.03;
const ivaPct = props.empresa?.factura_sin_iva ? 0 : (Number(props.tarifaDefaults?.iva_pct) || 0.21);

const form = useForm({
    origen_cuenta_id: '',
    destino_cuenta_id: '',
    facturar_a_destino: true,
    enviar_email: false,
    fecha_emision: new Date().toISOString().slice(0, 10),
    tipo_arca: '',
    observacion: '',
    servicio_minimo: '',
    servicio_retiro: '',
    items: [],
});

const searchOrigen = ref('');
const showOrigenDropdown = ref(false);
const selectedOrigen = ref('');

const searchDestino = ref('');
const showDestinoDropdown = ref(false);
const selectedDestino = ref('');

const filtered = (list) => {
    return (q) => {
        if (!q) return list;
        return list.filter(c => {
            const nom = (c.nombre_cuenta || c.tercero?.razon_social || '').toLowerCase();
            const cui = (c.tercero?.cuit || '').toLowerCase();
            return nom.includes(q) || cui.includes(q);
        });
    };
};

const filteredOrigen = computed(() => filtered(props.cuentas)(searchOrigen.value.toLowerCase().trim()));
const filteredDestino = computed(() => filtered(props.cuentas)(searchDestino.value.toLowerCase().trim()));
const cuentasAValidar = computed(() => {
    const ids = [form.origen_cuenta_id, form.destino_cuenta_id].filter(Boolean);
    return [...new Set(ids)].map((id) => (props.cuentas || []).find((cuenta) => String(cuenta.id) === String(id))).filter(Boolean);
});
const cuentaFacturada = computed(() => {
    const id = form.facturar_a_destino ? form.destino_cuenta_id : form.origen_cuenta_id;
    return (props.cuentas || []).find((cuenta) => String(cuenta.id) === String(id)) || null;
});
const cuentaOrigenSeleccionada = computed(() => (props.cuentas || []).find((cuenta) => String(cuenta.id) === String(form.origen_cuenta_id)) || null);
const cuentaDestinoSeleccionada = computed(() => (props.cuentas || []).find((cuenta) => String(cuenta.id) === String(form.destino_cuenta_id)) || null);
const cuitCheckModal = ref({
    show: false,
    loading: false,
    updating: false,
    error: '',
    aviso: '',
    cuentaIds: [],
    key: '',
    checks: [],
});
const cuitVerificadoKey = ref('');
const cuentaEditandoId = ref(null);
const arcaActualizandoId = ref(null);
const cuentaEditando = ref(false);
const edicionError = ref('');
const cuentaEditForm = useForm({
    razon_social: '',
    condicion_iva_id: '',
    nombre_cuenta: '',
    direccion: '',
    localidad: '',
    barrio: '',
    cp: '',
    email: '',
});

const abrirEdicionCuenta = (cuenta) => {
    cuentaEditandoId.value = cuenta.id;
    edicionError.value = '';
    cuentaEditForm.razon_social = cuenta.tercero?.razon_social || '';
    cuentaEditForm.condicion_iva_id = cuenta.tercero?.condicion_iva_id
        || props.condicionesIva?.find((condicion) => condicion.nombre === cuenta.tercero?.condicion_iva)?.id
        || '';
    cuentaEditForm.nombre_cuenta = cuenta.nombre_cuenta || '';
    cuentaEditForm.direccion = cuenta.direccion || cuenta.tercero?.domicilio_fiscal?.calle || '';
    cuentaEditForm.localidad = cuenta.localidad || cuenta.tercero?.domicilio_fiscal?.ciudad || '';
    cuentaEditForm.barrio = cuenta.barrio || '';
    cuentaEditForm.cp = cuenta.cp || cuenta.tercero?.domicilio_fiscal?.codigo_postal || '';
    cuentaEditForm.email = cuenta.email || '';
    cuentaEditForm.clearErrors();
    cuentaEditando.value = true;
};

const guardarEdicionCuenta = () => {
    if (!cuentaEditandoId.value) return;
    edicionError.value = '';
    cuentaEditForm.put(route('facturacion.carga-directa.cuentas.update', cuentaEditandoId.value), {
        preserveScroll: true,
        onSuccess: async () => {
            cuentaEditando.value = false;
            cuitVerificadoKey.value = '';
            await buscarCotizaciones();
        },
        onError: () => {
            edicionError.value = 'Revisá los datos ingresados e intentá nuevamente.';
        },
    });
};

const actualizarCuentaDesdeArca = (cuenta) => {
    if (!cuenta?.id || arcaActualizandoId.value) return;
    if (!window.confirm(`¿Actualizar los datos de ${cuenta.nombre_cuenta || cuenta.tercero?.razon_social || 'este cliente'} según el padrón de ARCA?`)) return;

    edicionError.value = '';
    arcaActualizandoId.value = cuenta.id;
    router.post(route('facturacion.carga-directa.actualizar-datos-arca'), { cuenta_id: cuenta.id }, {
        preserveScroll: true,
        onSuccess: () => {
            cuitVerificadoKey.value = '';
            buscarCotizaciones();
        },
        onError: (errors) => {
            edicionError.value = errors.cuit_arca || 'No se pudieron actualizar los datos desde ARCA.';
        },
        onFinish: () => { arcaActualizandoId.value = null; },
    });
};

watch(() => props.cuentas, () => {
    const actualizarSeleccion = (id, search, selected) => {
        const cuenta = (props.cuentas || []).find((item) => String(item.id) === String(id));
        if (!cuenta) return;
        const label = cuenta.nombre_cuenta || cuenta.tercero?.razon_social || '';
        selected.value = label;
        search.value = label;
    };
    actualizarSeleccion(form.origen_cuenta_id, searchOrigen, selectedOrigen);
    actualizarSeleccion(form.destino_cuenta_id, searchDestino, selectedDestino);
}, { deep: true });

watch([() => form.origen_cuenta_id, () => form.destino_cuenta_id, () => form.facturar_a_destino], () => {
    cuitVerificadoKey.value = '';
    form.enviar_email = !!cuentaFacturada.value?.email;
});

const selectCuenta = (field, searchRef, selectedRef, showRef, cuenta) => {
    form[field] = cuenta.id;
    selectedRef.value = cuenta.nombre_cuenta || cuenta.tercero?.razon_social || '';
    searchRef.value = selectedRef.value;
    showRef.value = false;
};

const selectOrigen = (cuenta) => selectCuenta('origen_cuenta_id', searchOrigen, selectedOrigen, showOrigenDropdown, cuenta);
const selectDestino = (cuenta) => selectCuenta('destino_cuenta_id', searchDestino, selectedDestino, showDestinoDropdown, cuenta);

const formatNum = (v) => {
    const n = Number(v) || 0;
    return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
const formatFecha = (v) => v ? String(v).slice(0, 10) : '-';
const parseNum = (v) => {
    const cleaned = String(v).replace(/\./g, '').replace(',', '.');
    return Number(cleaned) || 0;
};

const recalcularImporte = (it) => {
    const vd = Number(it.valor_declarado) || 0;
    it.importe = Math.round(vd * defaultPct * 100) / 100;
    recalcularSeguro(it);
};

const calcularSeguro = (valorDeclarado) => {
    const tarifa = tarifaActiva.value || {};
    if (tarifa.usar_seguro === false) return 0;

    let seguro = (Number(valorDeclarado) || 0) * (Number(tarifa.seguro_pct) || 0);
    if (tarifa.seguro_minimo !== null && tarifa.seguro_minimo !== undefined && tarifa.seguro_minimo !== '') {
        seguro = Math.max(Number(tarifa.seguro_minimo) || 0, seguro);
    }
    if (tarifa.seguro_tope !== null && tarifa.seguro_tope !== undefined && tarifa.seguro_tope !== '') {
        seguro = Math.min(Number(tarifa.seguro_tope) || 0, seguro);
    }

    return Math.round((seguro + Number.EPSILON) * 100) / 100;
};

const recalcularSeguro = (it) => {
    it.seguro = calcularSeguro(it.valor_declarado);
};

let nextKey = 1;
const items = ref([]);

const agregarItem = () => {
    items.value.push({
        key: nextKey++,
        descripcion: '',
        cantidad: 1,
        tipo: 'bultos',
        valor_declarado: 0,
        importe: 0,
        seguro: calcularSeguro(0),
        cr: 0,
        remito: '',
    });
};

agregarItem();

const cotizaciones = ref([]);
const cotizacionesLoading = ref(false);
const arcaTipoOpciones = ref([]);
let pairLookupId = 0;

const buscarCotizaciones = async () => {
    const lookupId = ++pairLookupId;
    if (!form.origen_cuenta_id || !form.destino_cuenta_id) {
        cotizaciones.value = [];
        arcaTipoOpciones.value = [];
        form.tipo_arca = '';
        tarifaActiva.value = { ...(props.tarifaDefaults || {}) };
        items.value.forEach(recalcularSeguro);
        cotizacionesLoading.value = false;
        return;
    }
    cotizacionesLoading.value = true;
    arcaTipoOpciones.value = [];
    form.tipo_arca = '';
    tarifaActiva.value = { ...(props.tarifaDefaults || {}) };
    items.value.forEach(recalcularSeguro);
    try {
        const query = '?origen_cuenta_id=' + encodeURIComponent(form.origen_cuenta_id)
            + '&destino_cuenta_id=' + encodeURIComponent(form.destino_cuenta_id)
            + '&facturar_a_destino=' + (form.facturar_a_destino ? '1' : '0');
        const [cotizacionesResult, tarifaResult] = await Promise.allSettled([
            fetch(route('facturacion.carga-directa.cotizaciones') + query),
            fetch(route('facturacion.carga-directa.tarifa') + query),
        ]);
        if (lookupId !== pairLookupId) return;

        cotizaciones.value = cotizacionesResult.status === 'fulfilled' && cotizacionesResult.value.ok
            ? await cotizacionesResult.value.json()
            : [];

        if (tarifaResult.status === 'fulfilled' && tarifaResult.value.ok) {
            const dataTarifa = await tarifaResult.value.json();
            tarifaActiva.value = { ...(props.tarifaDefaults || {}), ...dataTarifa };
            arcaTipoOpciones.value = dataTarifa.arca_tipo_opciones || [];
        } else {
            tarifaActiva.value = { ...(props.tarifaDefaults || {}) };
            arcaTipoOpciones.value = [];
        }
        if (!arcaTipoOpciones.value.some((opcion) => opcion.code === form.tipo_arca)) {
            form.tipo_arca = arcaTipoOpciones.value[0]?.code || '';
        }
        items.value.forEach(recalcularSeguro);
    } catch {
        if (lookupId === pairLookupId) {
            cotizaciones.value = [];
            tarifaActiva.value = { ...(props.tarifaDefaults || {}) };
            arcaTipoOpciones.value = [];
            form.tipo_arca = '';
            items.value.forEach(recalcularSeguro);
        }
    } finally {
        if (lookupId === pairLookupId) cotizacionesLoading.value = false;
    }
};

watch([() => form.origen_cuenta_id, () => form.destino_cuenta_id, () => form.facturar_a_destino], buscarCotizaciones);

const importarCotizacion = (cq) => {
    if (!cq.items || !cq.items.length) return;
    const importesItems = cq.items.map(it => Number(it.importe) || 0);
    const sumaImportes = importesItems.reduce((a, b) => a + b, 0);
    const flete = Number(cq.flete_final) || 0;
    items.value = cq.items.map((it, i) => {
        let importe = importesItems[i];
        if (importe <= 0 && flete > 0) {
            const totalVd = cq.items.reduce((s, item) => s + (Number(item.valor_declarado) || 0), 0);
            if (totalVd > 0) {
                importe = Math.round((Number(it.valor_declarado) || 0) / totalVd * flete * 100) / 100;
            }
        }
        const valorDeclarado = Number(it.valor_declarado) || 0;
        return {
            key: nextKey++,
            descripcion: it.descripcion || '',
            cantidad: Number(it.cantidad) || 1,
            tipo: it.tipo || 'bultos',
            valor_declarado: valorDeclarado,
            importe: importe,
            seguro: calcularSeguro(valorDeclarado),
            cr: Number(it.cr) || 0,
            remito: it.remito || '',
        };
    });
    cotizaciones.value = [];
};

const eliminarItem = (key) => {
    if (items.value.length <= 1) return;
    items.value = items.value.filter(it => it.key !== key);
};

const totales = computed(() => {
    let importe = 0, seguro = 0, cr = 0, subtotal = 0, iva = 0, total = 0;
    for (const it of items.value) {
        const imp = Number(it.importe) || 0;
        const seg = Number(it.seguro) || 0;
        const c = Number(it.cr) || 0;
        const sub = imp + seg + c;
        const iv = sub * ivaPct;

        importe += imp;
        seguro += seg;
        cr += c;
        subtotal += sub;
        iva += iv;
        total += sub + iv;
    }
    return {
        importe: Math.round(importe * 100) / 100,
        seguro: Math.round(seguro * 100) / 100,
        cr: Math.round(cr * 100) / 100,
        subtotal: Math.round(subtotal * 100) / 100,
        iva: Math.round(iva * 100) / 100,
        total: Math.round(total * 100) / 100,
    };
});

const resetForNext = () => {
    items.value = [{
        key: nextKey++,
        descripcion: '',
        cantidad: 1,
        tipo: 'bultos',
        valor_declarado: 0,
        importe: 0,
        seguro: calcularSeguro(0),
        cr: 0,
        remito: '',
    }];
    form.observacion = '';
    form.clearErrors();
};

const submit = () => {
    form.items = items.value.map(it => ({
        descripcion: it.descripcion,
        cantidad: Number(it.cantidad) || 1,
        tipo: it.tipo,
        valor_declarado: Number(it.valor_declarado) || 0,
        importe: Number(it.importe) || 0,
        seguro: Number(it.seguro) || 0,
        cr: Number(it.cr) || 0,
        remito: it.remito,
    }));
    form.tipo_arca = form.tipo_arca || arcaTipoOpciones.value[0]?.code || '';

    form.post(route('facturacion.carga-directa.store'), {
        preserveScroll: true,
        onSuccess: () => resetForNext(),
    });
};

const verificarCuitYEnviar = async () => {
    const cuentas = cuentasAValidar.value;
    const faltanCuentas = !form.origen_cuenta_id || !form.destino_cuenta_id;
    const cuentaObligatoria = cuentaFacturada.value;
    const cuitObligatorioInvalido = !cuentaObligatoria
        || String(cuentaObligatoria.tercero?.cuit || '').replace(/\D/g, '').length !== 11;

    if (faltanCuentas || !cuentas.length || cuitObligatorioInvalido) {
        cuitCheckModal.value = {
            show: true,
            loading: false,
            updating: false,
            aviso: '',
            error: faltanCuentas || !cuentas.length
                ? 'Seleccione remitente y destinatario antes de emitir.'
                : `La cuenta que recibe la factura (${cuentaObligatoria?.nombre_cuenta || cuentaObligatoria?.tercero?.razon_social || ''}) no tiene un CUIT válido de 11 dígitos. Corrija ese dato para poder emitir.`,
            cuentaIds: cuentas.map((item) => item.id),
            key: '',
            checks: [],
        };
        return;
    }

    const key = cuentas
        .map((cuenta) => `${cuenta.id}:${String(cuenta.tercero?.cuit || '').replace(/\D/g, '')}`)
        .sort()
        .join('|');

    if (cuitVerificadoKey.value === key) {
        submit();
        return;
    }

    cuitCheckModal.value = {
        show: true,
        loading: true,
        updating: false,
        error: '',
        aviso: '',
        cuentaIds: cuentas.map((cuenta) => cuenta.id),
        key,
        checks: [],
    };

    try {
        const query = cuentas.map((cuenta) => `cuenta_ids[]=${encodeURIComponent(cuenta.id)}`).join('&');
        const url = route('facturacion.carga-directa.verificar-cuit') + '?' + query;
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const result = await response.json();

        if (!response.ok || !result.found) {
            throw new Error(result.error || 'No se pudo validar el CUIT ante ARCA.');
        }

        if (cuitCheckModal.value.key !== key) return;
        cuitCheckModal.value.loading = false;
        cuitCheckModal.value.checks = result.checks || [];

        const verificacionObligatoria = cuitCheckModal.value.checks.find((check) => String(check.cuenta_id) === String(cuentaObligatoria.id));
        if (!verificacionObligatoria || verificacionObligatoria.error) {
            cuitCheckModal.value.error = `No se pudo verificar en ARCA el CUIT del cliente al que se factura. ${verificacionObligatoria?.error || ''}`.trim();
            return;
        }

        const verificacionesOpcionalesFallidas = cuitCheckModal.value.checks.filter((check) =>
            String(check.cuenta_id) !== String(cuentaObligatoria.id) && check.error,
        );
        if (verificacionesOpcionalesFallidas.length) {
            cuitCheckModal.value.aviso = verificacionesOpcionalesFallidas
                .map((check) => `${check.cuenta_nombre || 'El otro cliente'}: no se pudieron verificar sus datos en ARCA. ${check.error}`)
                .join(' ');
        }

        if (!cuitCheckModal.value.checks.some((check) => check.cambios?.length) && !cuitCheckModal.value.aviso) {
            cuitVerificadoKey.value = key;
            cuitCheckModal.value.show = false;
            submit();
        }
    } catch (error) {
        if (cuitCheckModal.value.key !== key) return;
        cuitCheckModal.value.loading = false;
        cuitCheckModal.value.error = error?.message || 'No se pudo validar el CUIT ante ARCA.';
    }
};

const continuarConFactura = () => {
    cuitVerificadoKey.value = cuitCheckModal.value.key;
    cuitCheckModal.value.show = false;
    submit();
};

const confirmarActualizacionArca = () => {
    const cuentaIds = cuitCheckModal.value.checks
        .filter((check) => check.cambios?.length)
        .map((check) => check.cuenta_id);
    if (!cuentaIds.length || cuitCheckModal.value.updating) return;

    cuitCheckModal.value.updating = true;
    let continuarConFactura = false;
    router.post(route('facturacion.carga-directa.actualizar-datos-arca'), {
        cuenta_ids: cuentaIds,
        cuenta_facturada_id: cuentaFacturada.value?.id,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (responsePage) => {
            const aviso = responsePage.props.tt?.flash?.warning || responsePage.props.flash?.warning;
            if (aviso) window.alert(aviso);
            cuitVerificadoKey.value = cuitCheckModal.value.key;
            cuitCheckModal.value.show = false;
            continuarConFactura = true;
        },
        onError: (errors) => {
            cuitCheckModal.value.error = errors.cuit_arca || 'No se pudieron actualizar los datos desde ARCA.';
        },
        onFinish: async () => {
            cuitCheckModal.value.updating = false;
            if (continuarConFactura) {
                await buscarCotizaciones();
                submit();
            }
        },
    });
};
</script>

<template>
    <AppLayout title="Facturacion / Carga directa">
        <Head title="Facturacion / Carga directa" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturacion / Carga directa</h2>
                    <div class="mt-1 text-sm text-gray-600">Crear factura con pedidos desde cero, sin manifiesto.</div>
                </div>
                <Link :href="route('facturacion.manifiestos.index')">
                    <SecondaryButton>Volver</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-6xl mx-auto py-4 sm:px-6 lg:px-8">
            <div v-if="flashSuccess" class="mb-3 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800 flex items-center justify-between gap-4">
                <span>{{ flashSuccess }}</span>
                <Link :href="route('facturacion.manifiestos.index')" class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold whitespace-nowrap">Ver comprobantes →</Link>
            </div>
            <div v-if="flashError" class="mb-3 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ flashError }}</div>
            <div v-if="edicionError" class="mb-3 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ edicionError }}</div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <form @submit.prevent="verificarCuitYEnviar" class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="relative">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <InputLabel value="Origen (Remitente)" />
                                <div class="flex items-center gap-2">
                                    <button v-if="cuentaOrigenSeleccionada" type="button" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline" @click="abrirEdicionCuenta(cuentaOrigenSeleccionada)">Editar</button>
                                    <button v-if="cuentaOrigenSeleccionada" type="button" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline disabled:opacity-50" :disabled="arcaActualizandoId === cuentaOrigenSeleccionada.id" @click="actualizarCuentaDesdeArca(cuentaOrigenSeleccionada)">{{ arcaActualizandoId === cuentaOrigenSeleccionada.id ? 'Consultando ARCA...' : 'Actualizar ARCA' }}</button>
                                    <a :href="route('admin.terceros.index', { tipo: 'cliente' })" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline">+ Nuevo</a>
                                </div>
                            </div>
                            <TextInput
                                v-model="searchOrigen"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Buscar por nombre o CUIT..."
                                @focus="showOrigenDropdown = true"
                                @blur="setTimeout(() => showOrigenDropdown = false, 200)"
                            />
                            <input type="hidden" v-model="form.origen_cuenta_id" />
                            <InputError :message="form.errors.origen_cuenta_id" />
                            <ul
                                v-if="showOrigenDropdown && filteredOrigen.length"
                                class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto text-sm"
                            >
                                <li
                                    v-for="c in filteredOrigen"
                                    :key="c.id"
                                    class="px-3 py-2 hover:bg-indigo-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                                    @mousedown.prevent="selectOrigen(c)"
                                >
                                    <div class="font-medium text-gray-900">{{ c.nombre_cuenta || c.tercero?.razon_social }}</div>
                                    <div class="text-xs text-gray-500">{{ c.tercero?.cuit || 'Sin CUIT' }}</div>
                                </li>
                            </ul>
                            <div v-if="form.origen_cuenta_id && !showOrigenDropdown" class="mt-1 text-xs text-green-700">
                                {{ selectedOrigen }}
                            </div>
                        </div>

                        <div class="relative">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <InputLabel value="Destino (Destinatario / Entrega)" />
                                <div class="flex items-center gap-2">
                                    <button v-if="cuentaDestinoSeleccionada" type="button" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline" @click="abrirEdicionCuenta(cuentaDestinoSeleccionada)">Editar</button>
                                    <button v-if="cuentaDestinoSeleccionada" type="button" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline disabled:opacity-50" :disabled="arcaActualizandoId === cuentaDestinoSeleccionada.id" @click="actualizarCuentaDesdeArca(cuentaDestinoSeleccionada)">{{ arcaActualizandoId === cuentaDestinoSeleccionada.id ? 'Consultando ARCA...' : 'Actualizar ARCA' }}</button>
                                    <a :href="route('admin.terceros.index', { tipo: 'cliente' })" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline">+ Nuevo</a>
                                </div>
                            </div>
                            <TextInput
                                v-model="searchDestino"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Buscar por nombre o CUIT..."
                                @focus="showDestinoDropdown = true"
                                @blur="setTimeout(() => showDestinoDropdown = false, 200)"
                            />
                            <input type="hidden" v-model="form.destino_cuenta_id" />
                            <InputError :message="form.errors.destino_cuenta_id" />
                            <ul
                                v-if="showDestinoDropdown && filteredDestino.length"
                                class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto text-sm"
                            >
                                <li
                                    v-for="c in filteredDestino"
                                    :key="c.id"
                                    class="px-3 py-2 hover:bg-indigo-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                                    @mousedown.prevent="selectDestino(c)"
                                >
                                    <div class="font-medium text-gray-900">{{ c.nombre_cuenta || c.tercero?.razon_social }}</div>
                                    <div class="text-xs text-gray-500">{{ c.tercero?.cuit || 'Sin CUIT' }}</div>
                                </li>
                            </ul>
                            <div v-if="form.destino_cuenta_id && !showDestinoDropdown" class="mt-1 text-xs text-green-700">
                                {{ selectedDestino }}
                            </div>
                        </div>

                        <div>
                            <InputLabel for="fecha_emision" value="Fecha de emision" />
                            <TextInput id="fecha_emision" v-model="form.fecha_emision" type="date" class="mt-1 block w-full" />
                            <InputError :message="form.errors.fecha_emision" />
                        </div>

                        <div class="flex items-end pb-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.facturar_a_destino"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <span class="text-sm text-gray-700 font-medium">Facturar a destino</span>
                                <span class="text-xs text-gray-400">(si no, se factura a origen)</span>
                            </label>
                        </div>

                        <div v-if="cuentaFacturada" class="sm:col-span-2 rounded-md border border-gray-200 p-3">
                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                <input
                                    v-model="form.enviar_email"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    :disabled="!cuentaFacturada.email"
                                />
                                <span>
                                    Enviar factura por email al emitir
                                    <span v-if="cuentaFacturada.email" class="block text-xs text-gray-500">{{ cuentaFacturada.email }}</span>
                                    <span v-else class="block text-xs text-gray-500">La cuenta facturada no tiene email cargado.</span>
                                </span>
                            </label>
                        </div>

                        <div v-if="form.origen_cuenta_id && form.destino_cuenta_id" class="sm:col-span-2">
                            <InputLabel for="tipo_arca" value="Comprobante fiscal ARCA" />
                            <select id="tipo_arca" v-model="form.tipo_arca" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" :disabled="cotizacionesLoading || !arcaTipoOpciones.length">
                                <option v-for="opcion in arcaTipoOpciones" :key="opcion.code" :value="opcion.code">{{ opcion.label }}</option>
                            </select>
                            <div v-if="arcaTipoOpciones.length" class="mt-1 text-xs text-gray-500">
                                Sugerido según la condición IVA de la empresa y del cliente seleccionado para facturar.
                            </div>
                            <div v-else-if="!cotizacionesLoading" class="mt-1 text-xs text-red-600">
                                No hay tipos de comprobante ARCA disponibles para esta empresa y cliente.
                            </div>
                            <InputError :message="form.errors.tipo_arca" />
                        </div>
                    </div>

                    <div v-if="cotizacionesLoading" class="text-sm text-gray-500 py-2">Buscando presupuestos vigentes...</div>
                    <div v-if="cotizaciones.length" class="border border-green-200 bg-green-50 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-green-800 mb-2">Presupuesto{{ cotizaciones.length > 1 ? 's' : '' }} vigente{{ cotizaciones.length > 1 ? 's' : '' }} para este origen y destino</h4>
                        <div v-for="cq in cotizaciones" :key="cq.id" class="mb-2 last:mb-0 bg-white rounded border border-green-200 p-3 flex items-start justify-between gap-4">
                            <div class="text-sm space-y-1">
                                <div><span class="font-medium text-gray-700">Origen:</span> {{ cq.origen || '-' }} <span class="text-gray-400">→</span> <span class="font-medium text-gray-700">Destino:</span> {{ cq.destino || '-' }}</div>
                                <div class="text-xs text-gray-500">Items: {{ (cq.items || []).length }} · Valor declarado: ${{ formatNum((cq.items || []).reduce((s, i) => s + (Number(i.valor_declarado) || 0), 0)) }}</div>
                                <div class="text-xs text-gray-500">Validez: {{ formatFecha(cq.fecha_validez) }} · Creado: {{ formatFecha(cq.created_at) }}</div>
                                <div class="text-xs" v-if="cq.observacion">Obs: {{ cq.observacion }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-bold text-green-700">${{ formatNum(cq.flete_final) }}</div>
                                <PrimaryButton type="button" class="!text-xs !px-2 !py-1 mt-1" @click="importarCotizacion(cq)">Importar items</PrimaryButton>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex items-center justify-between gap-4 mb-3">
                            <h3 class="text-base font-semibold text-gray-900">Detalle</h3>
                            <button
                                type="button"
                                class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                                @click="agregarItem"
                            >
                                + Agregar fila
                            </button>
                        </div>

                        <div class="space-y-4 lg:hidden">
                            <div
                                v-for="(it, i) in items"
                                :key="it.key"
                                class="rounded-lg border border-gray-200 p-4 space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-900">Item #{{ i + 1 }}</span>
                                    <button v-if="items.length > 1" type="button" class="text-xs text-red-600 hover:text-red-800" @click="eliminarItem(it.key)">Eliminar</button>
                                </div>
                                <div>
                                    <InputLabel :for="'desc-' + it.key" value="Descripcion" />
                                    <TextInput :id="'desc-' + it.key" v-model="it.descripcion" type="text" class="mt-1 block w-full" placeholder="Ej: Flete mercaderia" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel :for="'cant-' + it.key" value="Cantidad" />
                                        <TextInput :id="'cant-' + it.key" v-model="it.cantidad" type="number" min="1" class="mt-1 block w-full" />
                                    </div>
                                    <div>
                                        <InputLabel :for="'tipo-' + it.key" value="Tipo" />
                                        <select :id="'tipo-' + it.key" v-model="it.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="bultos">Bultos</option>
                                            <option value="palets">Palets</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel :for="'vd-' + it.key" value="Valor declarado" />
                                        <TextInput :id="'vd-' + it.key" v-model="it.valor_declarado" type="number" min="0" step="0.01" class="mt-1 block w-full" @input="recalcularImporte(it)" />
                                        <div v-if="it.valor_declarado !== '' && it.valor_declarado !== null" class="mt-0.5 text-[10px] text-gray-500 text-right">{{ formatNum(it.valor_declarado) }}</div>
                                    </div>
                                    <div>
                                        <InputLabel :for="'importe-' + it.key" value="Importe" />
                                        <TextInput :id="'importe-' + it.key" v-model="it.importe" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                                        <div v-if="it.importe !== '' && it.importe !== null" class="mt-0.5 text-[10px] text-gray-500 text-right">{{ formatNum(it.importe) }}</div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel :for="'seguro-' + it.key" value="Seguro (automático)" />
                                        <TextInput :id="'seguro-' + it.key" v-model="it.seguro" type="number" min="0" step="0.01" class="mt-1 block w-full" readonly />
                                        <div class="mt-0.5 text-[10px] text-gray-500 text-right">
                                            {{ tarifaActiva.usar_seguro === false ? 'Desactivado por tarifa' : `${((Number(tarifaActiva.seguro_pct) || 0) * 100).toLocaleString('es-AR')}% del valor declarado` }}
                                        </div>
                                        <div v-if="it.seguro !== '' && it.seguro !== null" class="mt-0.5 text-[10px] text-gray-500 text-right">{{ formatNum(it.seguro) }}</div>
                                    </div>
                                    <div>
                                        <InputLabel :for="'cr-' + it.key" value="CR" />
                                        <TextInput :id="'cr-' + it.key" v-model="it.cr" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                                        <div v-if="it.cr !== '' && it.cr !== null" class="mt-0.5 text-[10px] text-gray-500 text-right">{{ formatNum(it.cr) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 hidden lg:block overflow-x-auto">
                            <table class="min-w-full w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripcion</th>
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Cant</th>
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Val. declarado</th>
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th>
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Seguro</th>
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">CR</th>
                                        <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remito</th>
                                        <th class="px-2 py-1.5"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(it, i) in items" :key="it.key">
                                        <td class="px-2 py-1 text-xs text-gray-500 whitespace-nowrap">{{ i + 1 }}</td>
                                        <td class="px-2 py-1">
                                            <input v-model="it.descripcion" type="text" class="w-20 border-gray-300 rounded text-xs py-0.5 px-1" placeholder="Descripcion" />
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <input v-model="it.cantidad" type="number" min="1" class="w-12 border-gray-300 rounded text-xs py-0.5 px-1 text-center" />
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <select v-model="it.tipo" class="w-16 border-gray-300 rounded text-xs py-0.5 px-0.5">
                                                <option value="bultos">Bultos</option>
                                                <option value="palets">Palets</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-1">
                                            <input v-model.number="it.valor_declarado" @input="recalcularImporte(it)" type="number" min="0" step="0.01" class="w-40 border-gray-300 rounded text-xs py-0.5 px-1 text-right" />
                                            <div v-if="it.valor_declarado !== '' && it.valor_declarado !== null" class="text-[10px] text-gray-500 text-right leading-none mt-0.5">{{ formatNum(it.valor_declarado) }}</div>
                                        </td>
                                        <td class="px-2 py-1">
                                            <input v-model.number="it.importe" type="number" min="0" step="0.01" class="w-40 border-gray-300 rounded text-xs py-0.5 px-1 text-right" />
                                            <div v-if="it.importe !== '' && it.importe !== null" class="text-[10px] text-gray-500 text-right leading-none mt-0.5">{{ formatNum(it.importe) }}</div>
                                        </td>
                                        <td class="px-2 py-1">
                                            <input v-model.number="it.seguro" type="number" min="0" step="0.01" class="w-40 border-gray-300 rounded text-xs py-0.5 px-1 text-right" readonly />
                                            <div class="text-[10px] text-gray-500 text-right leading-none mt-0.5">
                                                {{ tarifaActiva.usar_seguro === false ? 'Desactivado' : `${((Number(tarifaActiva.seguro_pct) || 0) * 100).toLocaleString('es-AR')}% tarifa` }}
                                            </div>
                                            <div v-if="it.seguro !== '' && it.seguro !== null" class="text-[10px] text-gray-500 text-right leading-none mt-0.5">{{ formatNum(it.seguro) }}</div>
                                        </td>
                                        <td class="px-2 py-1">
                                            <input v-model.number="it.cr" type="number" min="0" step="0.01" class="w-40 border-gray-300 rounded text-xs py-0.5 px-1 text-right" />
                                            <div v-if="it.cr !== '' && it.cr !== null" class="text-[10px] text-gray-500 text-right leading-none mt-0.5">{{ formatNum(it.cr) }}</div>
                                        </td>
                                        <td class="px-2 py-1">
                                            <input v-model="it.remito" type="text" class="w-20 border-gray-300 rounded text-xs py-0.5 px-1" />
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <button v-if="items.length > 1" type="button" class="text-xs text-red-600 hover:text-red-800" @click="eliminarItem(it.key)">X</button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 font-semibold">
                                    <tr>
                                        <td colspan="4" class="px-2 py-1.5 text-xs text-gray-700 text-right">Subtotal:</td>
                                        <td class="px-2 py-1.5 text-xs text-gray-900 text-right">{{ formatNum(totales.importe + totales.seguro + totales.cr) }}</td>
                                        <td colspan="5"></td>
                                    </tr>
                                    <tr class="text-xs text-gray-600">
                                        <td colspan="4" class="px-2 py-0.5 text-right">IVA ({{ (ivaPct * 100).toFixed(1) }}%):</td>
                                        <td class="px-2 py-0.5 text-right">{{ formatNum(totales.iva) }}</td>
                                        <td colspan="5"></td>
                                    </tr>
                                    <tr class="border-t-2 border-gray-800">
                                        <td colspan="4" class="px-2 py-1.5 text-xs text-gray-900 text-right font-bold uppercase">Total factura:</td>
                                        <td class="px-2 py-1.5 text-xs text-gray-900 text-right font-bold">{{ formatNum(totales.total) }}</td>
                                        <td colspan="5"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="Servicio mínimo" />
                            <TextInput v-model="form.servicio_minimo" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="0.00" />
                            <div class="text-xs text-gray-500 mt-1">Se aplica a todas las relaciones</div>
                        </div>
                        <div>
                            <InputLabel value="Servicio de retiro" />
                            <TextInput v-model="form.servicio_retiro" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="0.00" />
                            <div class="text-xs text-gray-500 mt-1">Costo retiro</div>
                        </div>
                        <div>
                            <InputLabel value="Valor neto total" />
                            <div class="mt-1 text-sm font-semibold text-gray-900">${{ formatNum(totales.subtotal) }}</div>
                            <div class="text-xs text-gray-500">Subtotal sin IVA</div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <InputLabel for="observacion" value="Observacion" />
                        <textarea
                            id="observacion"
                            v-model="form.observacion"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            rows="2"
                            placeholder="Observaciones generales de la factura (opcional)"
                        ></textarea>
                        <InputError :message="form.errors.observacion" />
                    </div>

                    <div class="border-t border-gray-200 pt-4 flex items-center justify-end gap-3">
                        <Link :href="route('facturacion.manifiestos.index')">
                            <SecondaryButton type="button">Cancelar</SecondaryButton>
                        </Link>
                        <PrimaryButton :disabled="form.processing || !form.origen_cuenta_id || !form.destino_cuenta_id || !form.tipo_arca || !arcaTipoOpciones.length || items.length === 0">
                            Verificar CUIT y emitir factura
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <DialogModal :show="cuentaEditando" max-width="2xl" @close="cuentaEditando = false">
            <template #title>Editar datos del cliente</template>
            <template #content>
                <form class="space-y-4" @submit.prevent="guardarEdicionCuenta">
                    <div class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">
                        CUIT: {{ (props.cuentas || []).find((cuenta) => cuenta.id === cuentaEditandoId)?.tercero?.cuit || 'Sin CUIT' }}
                    </div>
                    <div>
                        <InputLabel for="edicion_razon_social" value="Razón social" />
                        <TextInput id="edicion_razon_social" v-model="cuentaEditForm.razon_social" type="text" class="mt-1 block w-full" />
                        <InputError :message="cuentaEditForm.errors.razon_social" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="edicion_nombre_cuenta" value="Nombre de cuenta" />
                            <TextInput id="edicion_nombre_cuenta" v-model="cuentaEditForm.nombre_cuenta" type="text" class="mt-1 block w-full" />
                            <InputError :message="cuentaEditForm.errors.nombre_cuenta" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="edicion_condicion_iva" value="Condición frente al IVA" />
                            <select id="edicion_condicion_iva" v-model="cuentaEditForm.condicion_iva_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Sin especificar</option>
                                <option v-for="condicion in condicionesIva" :key="condicion.id" :value="condicion.id">{{ condicion.nombre }}</option>
                            </select>
                            <InputError :message="cuentaEditForm.errors.condicion_iva_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="edicion_email" value="Email" />
                            <TextInput id="edicion_email" v-model="cuentaEditForm.email" type="email" class="mt-1 block w-full" />
                            <InputError :message="cuentaEditForm.errors.email" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="edicion_direccion" value="Dirección" />
                            <TextInput id="edicion_direccion" v-model="cuentaEditForm.direccion" type="text" class="mt-1 block w-full" />
                            <InputError :message="cuentaEditForm.errors.direccion" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="edicion_localidad" value="Localidad" />
                            <TextInput id="edicion_localidad" v-model="cuentaEditForm.localidad" type="text" class="mt-1 block w-full" />
                            <InputError :message="cuentaEditForm.errors.localidad" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="edicion_barrio" value="Barrio" />
                            <TextInput id="edicion_barrio" v-model="cuentaEditForm.barrio" type="text" class="mt-1 block w-full" />
                            <InputError :message="cuentaEditForm.errors.barrio" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="edicion_cp" value="Código postal" />
                            <TextInput id="edicion_cp" v-model="cuentaEditForm.cp" type="text" class="mt-1 block w-full" />
                            <InputError :message="cuentaEditForm.errors.cp" class="mt-1" />
                        </div>
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton type="button" :disabled="cuentaEditForm.processing" @click="cuentaEditando = false">Cancelar</SecondaryButton>
                <PrimaryButton type="button" class="ms-3" :disabled="cuentaEditForm.processing" @click="guardarEdicionCuenta">
                    {{ cuentaEditForm.processing ? 'Guardando...' : 'Guardar datos' }}
                </PrimaryButton>
            </template>
        </DialogModal>

        <DialogModal :show="cuitCheckModal.show" max-width="3xl" :closeable="!cuitCheckModal.updating" @close="cuitCheckModal.show = false">
            <template #title>Verificación de CUIT en ARCA</template>
            <template #content>
                <div class="space-y-3">
                    <p class="text-sm text-gray-700">Se verifica obligatoriamente en ARCA el cliente al que se factura. La verificación del otro cliente es informativa.</p>
                    <div v-if="cuitCheckModal.loading" class="rounded-md bg-blue-50 p-3 text-sm text-blue-800">
                        Consultando los CUIT de remitente y destinatario en el padrón de ARCA...
                    </div>
                    <div v-if="cuitCheckModal.error" class="rounded-md bg-red-50 p-3 text-sm text-red-800">
                        {{ cuitCheckModal.error }}
                    </div>
                    <div v-if="cuitCheckModal.aviso" class="rounded-md bg-amber-50 p-3 text-sm text-amber-900">
                        {{ cuitCheckModal.aviso }} La facturación puede continuar.
                    </div>
                    <template v-if="!cuitCheckModal.loading && cuitCheckModal.checks.length">
                        <p v-if="cuitCheckModal.checks.some((check) => check.cambios?.length)" class="text-sm text-gray-700">
                            ARCA informa diferencias. Confirmá si querés actualizar los datos antes de facturar.
                        </p>
                        <div v-for="check in cuitCheckModal.checks" :key="check.cuenta_id" class="rounded-md border border-gray-200">
                            <div class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-800">
                                {{ check.cuenta_nombre || 'Cliente' }} · CUIT {{ check.cuit }}
                            </div>
                            <div v-if="check.error" class="px-3 py-2 text-sm text-amber-800">No se pudieron verificar los datos en ARCA: {{ check.error }}</div>
                            <div v-else-if="!check.cambios?.length" class="px-3 py-2 text-sm text-green-700">Los datos coinciden con ARCA.</div>
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-white">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">Dato</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">Actual</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">Según ARCA</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        <tr v-for="cambio in check.cambios" :key="cambio.campo">
                                            <td class="px-3 py-2 font-medium text-gray-800">{{ cambio.campo }}</td>
                                            <td class="px-3 py-2 text-gray-600">{{ cambio.actual }}</td>
                                            <td class="px-3 py-2 text-gray-900">{{ cambio.arca }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p v-if="cuitCheckModal.checks.some((check) => check.cambios?.length)" class="text-xs text-gray-500">
                            Al confirmar, se actualizan las cuentas con diferencias y continúa la facturación.
                        </p>
                    </template>
                </div>
            </template>
            <template #footer>
                <SecondaryButton type="button" :disabled="cuitCheckModal.updating" @click="cuitCheckModal.show = false">Cancelar</SecondaryButton>
                <PrimaryButton
                    v-if="!cuitCheckModal.loading && !cuitCheckModal.error && cuitCheckModal.checks.some((check) => check.cambios?.length)"
                    type="button"
                    class="ms-3"
                    :disabled="cuitCheckModal.updating"
                    @click="confirmarActualizacionArca"
                >
                    {{ cuitCheckModal.updating ? 'Actualizando...' : 'Actualizar y emitir factura' }}
                </PrimaryButton>
                <PrimaryButton
                    v-else-if="!cuitCheckModal.loading && !cuitCheckModal.error && cuitCheckModal.aviso"
                    type="button"
                    class="ms-3"
                    @click="continuarConFactura"
                >Continuar y emitir factura</PrimaryButton>
                <PrimaryButton v-else-if="cuitCheckModal.error" type="button" class="ms-3" @click="cuitCheckModal.show = false">Cerrar</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
