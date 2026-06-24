<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    manifiesto: Object,
    comprobantes: {
        type: Array,
        default: () => [],
    },
    tarifas: {
        type: Array,
        default: () => [],
    },
    cotizacionesReferencia: {
        type: Object,
        default: () => ({}),
    },
    empresas: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const roles = computed(() => page.props.tt?.roles || []);
const canFacturar = computed(() => roles.value.includes('admin') || roles.value.includes('facturacion'));
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const permiteGuiasNoFiscales = computed(() => !!props.manifiesto?.empresa?.permite_guias_no_fiscales);

const DEFAULT_TARIFA = {
    moneda: props.manifiesto?.empresa?.moneda_base || 'ARS',
    tarifa_bulto: 10000,
    tarifa_palet: 100000,
    tarifa_valor_declarado_pct: 0.03,
    flete_minimo: 20000,
    seguro_pct: 0.007,
    seguro_minimo: null,
    seguro_tope: null,
    cr_comision_pct: 0.025,
    cr_comision_minimo: null,
    cr_comision_tope: null,
    iva_pct: 0.21,
};

const tarifaKey = (remId, destId) => `${remId || 0}-${destId || 0}`;

const tarifasMap = computed(() => {
    const m = new Map();
    for (const t of props.tarifas || []) {
        m.set(tarifaKey(t.remitente_tercero_id, t.destinatario_tercero_id), t);
    }
    return m;
});

const resolveTarifa = (remId, destId) => {
    const t = tarifasMap.value.get(tarifaKey(remId, destId));
    if (!t) return { ...DEFAULT_TARIFA };
    return {
        moneda: t.moneda || 'ARS',
        tarifa_bulto: Number(t.tarifa_bulto || 0),
        tarifa_palet: Number(t.tarifa_palet || 0),
        tarifa_valor_declarado_pct: Number(t.tarifa_valor_declarado_pct || 0),
        flete_minimo: Number(t.flete_minimo || 0),
        seguro_pct: Number(t.seguro_pct || 0),
        seguro_minimo: t.seguro_minimo === null ? null : Number(t.seguro_minimo || 0),
        seguro_tope: t.seguro_tope === null ? null : Number(t.seguro_tope || 0),
        cr_comision_pct: Number(t.cr_comision_pct || 0),
        cr_comision_minimo: t.cr_comision_minimo === null ? null : Number(t.cr_comision_minimo || 0),
        cr_comision_tope: t.cr_comision_tope === null ? null : Number(t.cr_comision_tope || 0),
        iva_pct: Number(t.iva_pct || 0),
    };
};

const getTasa = (moneda) => {
    const m = String(moneda || 'ARS').toUpperCase();
    if (m === 'ARS') return 1;
    return Number(props.cotizacionesReferencia?.[m]?.tasa_ars || 0);
};

const convertAmount = (amount, from, to) => {
    const origen = String(from || 'ARS').toUpperCase();
    const destino = String(to || 'ARS').toUpperCase();
    const n = Number(amount || 0);
    if (origen === destino) return n;
    const tasaOrigen = getTasa(origen);
    const tasaDestino = getTasa(destino);
    if (!tasaOrigen || !tasaDestino) return n;
    const ars = origen === 'ARS' ? n : n * tasaOrigen;
    return destino === 'ARS' ? ars : ars / tasaDestino;
};

const applyOverride = (tarifa, override) => {
    const out = { ...tarifa };
    if (!override) return out;
    if (override.moneda) {
        const origen = out.moneda || 'ARS';
        const destino = override.moneda;
        out.tarifa_bulto = convertAmount(out.tarifa_bulto, origen, destino);
        out.tarifa_palet = convertAmount(out.tarifa_palet, origen, destino);
        out.flete_minimo = convertAmount(out.flete_minimo, origen, destino);
        out.seguro_minimo = out.seguro_minimo === null ? null : convertAmount(out.seguro_minimo, origen, destino);
        out.seguro_tope = out.seguro_tope === null ? null : convertAmount(out.seguro_tope, origen, destino);
        out.cr_comision_minimo = out.cr_comision_minimo === null ? null : convertAmount(out.cr_comision_minimo, origen, destino);
        out.cr_comision_tope = out.cr_comision_tope === null ? null : convertAmount(out.cr_comision_tope, origen, destino);
        out.moneda = override.moneda;
    }
    for (const k of [
        'tarifa_bulto',
        'tarifa_palet',
        'tarifa_valor_declarado_pct',
        'flete_minimo',
        'seguro_pct',
        'seguro_minimo',
        'seguro_tope',
        'cr_comision_pct',
        'cr_comision_minimo',
        'cr_comision_tope',
        'cr_importe_manual',
        'comision_cr_manual',
        'iva_pct',
    ]) {
        if (Object.prototype.hasOwnProperty.call(override, k) && override[k] !== null && override[k] !== '') {
            out[k] = Number(override[k]);
        }
    }
    return out;
};

const calcFactura = (pedidos, tarifa, override) => {
    const t = applyOverride(tarifa, override);
    const bultos = pedidos.reduce((acc, p) => acc + Number(p.bultos || 0), 0);
    const palets = pedidos.reduce((acc, p) => acc + Number(p.palets || 0), 0);
    const valorDeclaradoOrigen = pedidos.reduce((acc, p) => acc + Number(p.valor_declarado || 0), 0);
    const crImporteOrigen = pedidos.reduce((acc, p) => acc + Number(p.cr_importe || 0), 0);
    const valorDeclarado = convertAmount(valorDeclaradoOrigen, 'ARS', t.moneda || 'ARS');
    const crImporteCalculado = convertAmount(crImporteOrigen, 'ARS', t.moneda || 'ARS');
    const crImporte = override?.cr_importe_manual !== null && override?.cr_importe_manual !== ''
        ? Number(override.cr_importe_manual)
        : crImporteCalculado;

    const fletePorUnidad = bultos * t.tarifa_bulto + palets * t.tarifa_palet;
    const fletePorValor = valorDeclarado * t.tarifa_valor_declarado_pct;
    const flete = Math.max(t.flete_minimo, fletePorUnidad, fletePorValor);

    let seguro = valorDeclarado * t.seguro_pct;
    if (t.seguro_minimo !== null) seguro = Math.max(Number(t.seguro_minimo), seguro);
    if (t.seguro_tope !== null) seguro = Math.min(Number(t.seguro_tope), seguro);

    let comisionCr = crImporte * t.cr_comision_pct;
    if (t.cr_comision_minimo !== null) comisionCr = Math.max(Number(t.cr_comision_minimo), comisionCr);
    if (t.cr_comision_tope !== null) comisionCr = Math.min(Number(t.cr_comision_tope), comisionCr);
    if (override?.comision_cr_manual !== null && override?.comision_cr_manual !== '') {
        comisionCr = Number(override.comision_cr_manual);
    }

    const subtotalGravado = flete + seguro + comisionCr;
    const iva = subtotalGravado * t.iva_pct;
    const total = subtotalGravado + iva;

    const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
    return {
        moneda: t.moneda || 'ARS',
        bultos,
        palets,
        valorDeclarado: round2(valorDeclarado),
        crImporte: round2(crImporte),
        flete: round2(flete),
        seguro: round2(seguro),
        comisionCr: round2(comisionCr),
        subtotalGravado: round2(subtotalGravado),
        iva: round2(iva),
        total: round2(total),
        parametros: t,
    };
};

const statsFacturacion = computed(() => {
    const pedidos = props.manifiesto.pedidos || [];
    const pendientes = pedidos.filter((p) => !(p.comprobantes && p.comprobantes.length));
    const listos = pendientes.filter((p) => p.recepcion_estado === 'correcto');
    const sinEntrega = pendientes.filter((p) => !p.destinatario_cuenta_id);
    return {
        total: pedidos.length,
        pendientes: pendientes.length,
        listos: listos.length,
        sinEntrega: sinEntrega.length,
        emitidos: (props.comprobantes || []).length,
    };
});

const pedidosPendientes = computed(() => {
    return (props.manifiesto.pedidos || []).filter((p) => !(p.comprobantes && p.comprobantes.length) && p.recepcion_estado === 'correcto');
});

const facturarPorEntrega = useForm({ confirm: true, facturar_por_entrega: {}, detalles_por_entrega: {}, empresa_por_entrega: {} });

const detalleOverridesSnapshot = computed(() => JSON.stringify(facturarPorEntrega.detalles_por_entrega || {}));

const gruposFacturacion = computed(() => {
    detalleOverridesSnapshot.value;

    const grouped = new Map();
    for (const p of pedidosPendientes.value) {
        const entregaId = p.destinatario_cuenta_id;
        if (!entregaId) continue;
        if (!grouped.has(entregaId)) grouped.set(entregaId, []);
        grouped.get(entregaId).push(p);
    }

    const out = [];
    for (const [entregaId, pedidos] of grouped.entries()) {
        if (!facturarPorEntrega.facturar_por_entrega?.[entregaId]) {
            facturarPorEntrega.facturar_por_entrega[entregaId] = '';
        }
        if (!facturarPorEntrega.detalles_por_entrega?.[entregaId]) {
            facturarPorEntrega.detalles_por_entrega[entregaId] = {
                editar: false,
                persistir_tarifa: false,
                moneda: props.manifiesto?.empresa?.moneda_base || 'ARS',
                tarifa_bulto: null,
                tarifa_palet: null,
                tarifa_valor_declarado_pct: null,
                flete_minimo: null,
                seguro_pct: null,
                seguro_minimo: null,
                seguro_tope: null,
                cr_comision_pct: null,
                cr_comision_minimo: null,
                cr_comision_tope: null,
                cr_importe_manual: null,
                comision_cr_manual: null,
                iva_pct: null,
            };
        }

        const remitenteIds = Array.from(new Set(pedidos.map((p) => Number(p.remitente_tercero_id || 0)).filter(Boolean)));
        const destinatarioIds = Array.from(new Set(pedidos.map((p) => Number(p.destinatario_tercero_id || 0)).filter(Boolean)));
        const isSingleRelacion = remitenteIds.length === 1 && destinatarioIds.length === 1;

        const override = facturarPorEntrega.detalles_por_entrega?.[entregaId] || null;

        let detalle;
        if (isSingleRelacion) {
            const tarifaBase = resolveTarifa(remitenteIds[0], destinatarioIds[0]);
            detalle = calcFactura(pedidos, tarifaBase, override);
        } else {
            const groupedByRel = new Map();
            for (const p of pedidos) {
                const key = tarifaKey(p.remitente_tercero_id, p.destinatario_tercero_id);
                if (!groupedByRel.has(key)) groupedByRel.set(key, []);
                groupedByRel.get(key).push(p);
            }

            const sum = {
                moneda: 'ARS',
                bultos: 0,
                palets: 0,
                valorDeclarado: 0,
                crImporte: 0,
                flete: 0,
                seguro: 0,
                comisionCr: 0,
                subtotalGravado: 0,
                iva: 0,
                total: 0,
                porRelacion: [],
                parametros: null,
            };

            for (const [key, relPedidos] of groupedByRel.entries()) {
                const [remStr, destStr] = String(key).split('-');
                const remId = Number(remStr || 0);
                const destId = Number(destStr || 0);
                const tarifaBase = resolveTarifa(remId, destId);
                const calc = calcFactura(relPedidos, tarifaBase, override);
                sum.moneda = calc.moneda || sum.moneda;
                sum.bultos += Number(calc.bultos || 0);
                sum.palets += Number(calc.palets || 0);
                sum.valorDeclarado += Number(calc.valorDeclarado || 0);
                sum.crImporte += Number(calc.crImporte || 0);
                sum.flete += Number(calc.flete || 0);
                sum.seguro += Number(calc.seguro || 0);
                sum.comisionCr += Number(calc.comisionCr || 0);
                sum.subtotalGravado += Number(calc.subtotalGravado || 0);
                sum.iva += Number(calc.iva || 0);
                sum.total += Number(calc.total || 0);
                sum.porRelacion.push({ remitenteId: remId, destinatarioId: destId, detalle: calc });
            }

            const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
            detalle = {
                ...sum,
                valorDeclarado: round2(sum.valorDeclarado),
                crImporte: round2(sum.crImporte),
                flete: round2(sum.flete),
                seguro: round2(sum.seguro),
                comisionCr: round2(sum.comisionCr),
                subtotalGravado: round2(sum.subtotalGravado),
                iva: round2(sum.iva),
                total: round2(sum.total),
            };
        }
        const cuentas = new Map();
        for (const p of pedidos) {
            if (p.remitente_cuenta) cuentas.set(p.remitente_cuenta.id, { id: p.remitente_cuenta.id, label: `${p.remitente_cuenta.tercero?.razon_social || 'Remitente'} (Origen)`, cuit: p.remitente_cuenta.tercero?.cuit || '' });
            if (p.destinatario_cuenta) cuentas.set(p.destinatario_cuenta.id, { id: p.destinatario_cuenta.id, label: `${p.destinatario_cuenta.tercero?.razon_social || 'Destinatario'} (Destino)`, cuit: p.destinatario_cuenta.tercero?.cuit || '' });
        }

        const pagas = Array.from(new Set(pedidos.map((p) => p.paga)));
        let suggested = '';
        if (pagas.length === 1) {
            if (pagas[0] === 'origen' && pedidos[0].remitente_cuenta) suggested = String(pedidos[0].remitente_cuenta.id);
            if (pagas[0] === 'destino' && pedidos[0].destinatario_cuenta) suggested = String(pedidos[0].destinatario_cuenta.id);
        }

        out.push({
            entregaId,
            pedidos,
            detalle,
            cuentas: Array.from(cuentas.values()),
            suggested,
            isSingleRelacion,
            remitenteId: isSingleRelacion ? remitenteIds[0] : null,
            destinatarioId: isSingleRelacion ? destinatarioIds[0] : null,
        });
    }

    return out.sort((a, b) => a.entregaId - b.entregaId);
});

const detalleGrupo = (g) => {
    const pedidos = g?.pedidos || [];
    const remitenteIds = Array.from(new Set(pedidos.map((p) => Number(p.remitente_tercero_id || 0)).filter(Boolean)));
    const destinatarioIds = Array.from(new Set(pedidos.map((p) => Number(p.destinatario_tercero_id || 0)).filter(Boolean)));
    const isSingleRelacion = remitenteIds.length === 1 && destinatarioIds.length === 1;
    const override = facturarPorEntrega.detalles_por_entrega?.[g.entregaId] || null;

    if (isSingleRelacion) {
        const tarifaBase = resolveTarifa(remitenteIds[0], destinatarioIds[0]);
        return calcFactura(pedidos, tarifaBase, override);
    }

    const groupedByRel = new Map();
    for (const p of pedidos) {
        const key = tarifaKey(p.remitente_tercero_id, p.destinatario_tercero_id);
        if (!groupedByRel.has(key)) groupedByRel.set(key, []);
        groupedByRel.get(key).push(p);
    }

    const sum = {
        moneda: 'ARS',
        bultos: 0,
        palets: 0,
        valorDeclarado: 0,
        crImporte: 0,
        flete: 0,
        seguro: 0,
        comisionCr: 0,
        subtotalGravado: 0,
        iva: 0,
        total: 0,
    };

    for (const [key, relPedidos] of groupedByRel.entries()) {
        const [remStr, destStr] = String(key).split('-');
        const remId = Number(remStr || 0);
        const destId = Number(destStr || 0);
        const tarifaBase = resolveTarifa(remId, destId);
        const calc = calcFactura(relPedidos, tarifaBase, override);
        sum.moneda = calc.moneda || sum.moneda;
        sum.bultos += Number(calc.bultos || 0);
        sum.palets += Number(calc.palets || 0);
        sum.valorDeclarado += Number(calc.valorDeclarado || 0);
        sum.crImporte += Number(calc.crImporte || 0);
        sum.flete += Number(calc.flete || 0);
        sum.seguro += Number(calc.seguro || 0);
        sum.comisionCr += Number(calc.comisionCr || 0);
        sum.subtotalGravado += Number(calc.subtotalGravado || 0);
        sum.iva += Number(calc.iva || 0);
        sum.total += Number(calc.total || 0);
    }

    const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
    return {
        ...sum,
        valorDeclarado: round2(sum.valorDeclarado),
        crImporte: round2(sum.crImporte),
        flete: round2(sum.flete),
        seguro: round2(sum.seguro),
        comisionCr: round2(sum.comisionCr),
        subtotalGravado: round2(sum.subtotalGravado),
        iva: round2(sum.iva),
        total: round2(sum.total),
    };
};

const backfillForm = useForm({ confirm: true });
const completarCuentas = () => {
    backfillForm.post(route('operacion.manifiestos.backfill-cuentas', props.manifiesto.id), { preserveScroll: true });
};

const autorizarForm = useForm({ tipo: 'FC' });
const tipoArcaPorComprobante = reactive({});

for (const c of props.comprobantes || []) {
    if (c.tipo === 'factura_interna' && !c.arca_cae) {
        tipoArcaPorComprobante[c.id] = c.arca_tipo_opciones?.[0]?.code || 'FC';
    }
}

const autorizarArca = (comprobanteId, tipo) => {
    autorizarForm.tipo = tipo;
    autorizarForm.post(route('operacion.comprobantes.autorizar-arca', comprobanteId), { preserveScroll: true });
};

const initFacturarMap = () => {
    const map = {};
    const det = {};
    const emp = {};
    for (const g of gruposFacturacion.value) {
        const empresaId = props.manifiesto?.empresa?.id || '';
        map[g.entregaId] = g.suggested || '';
        emp[g.entregaId] = String(empresaId);
        det[g.entregaId] = {
            editar: false,
            persistir_tarifa: false,
            moneda: props.manifiesto?.empresa?.moneda_base || 'ARS',
            tarifa_bulto: null,
            tarifa_palet: null,
            tarifa_valor_declarado_pct: null,
            flete_minimo: null,
            seguro_pct: null,
            seguro_minimo: null,
            seguro_tope: null,
            cr_comision_pct: null,
            cr_comision_minimo: null,
            cr_comision_tope: null,
            cr_importe_manual: null,
            comision_cr_manual: null,
            iva_pct: null,
        };
    }
    facturarPorEntrega.facturar_por_entrega = map;
    facturarPorEntrega.detalles_por_entrega = det;
    facturarPorEntrega.empresa_por_entrega = emp;
};

initFacturarMap();

const faltanSelecciones = computed(() => {
    for (const g of gruposFacturacion.value) {
        const selected = facturarPorEntrega.facturar_por_entrega?.[g.entregaId] || '';
        if (!selected) return true;
    }
    return false;
});

const facturarSeleccionado = () => {
    facturarPorEntrega
        .transform((data) => {
            const out = { ...data };
            const det = {};
            const keysToNull = [
                'tarifa_bulto',
                'tarifa_palet',
                'tarifa_valor_declarado_pct',
                'flete_minimo',
                'seguro_pct',
                'seguro_minimo',
                'seguro_tope',
                'cr_comision_pct',
                'cr_comision_minimo',
                'cr_comision_tope',
                'cr_importe_manual',
                'comision_cr_manual',
                'iva_pct',
            ];
            for (const [entregaId, v] of Object.entries(data.detalles_por_entrega || {})) {
                const x = { ...(v || {}) };
                delete x.editar;
                for (const k of keysToNull) {
                    if (x[k] === '') x[k] = null;
                }
                det[entregaId] = x;
            }
            out.detalles_por_entrega = det;
            return out;
        })
        .post(route('operacion.manifiestos.facturar', props.manifiesto.id), { preserveScroll: true });
};

const emitirGuias = () => {
    facturarPorEntrega
        .transform((data) => {
            const out = { ...data };
            const det = {};
            const keysToNull = [
                'tarifa_bulto',
                'tarifa_palet',
                'tarifa_valor_declarado_pct',
                'flete_minimo',
                'seguro_pct',
                'seguro_minimo',
                'seguro_tope',
                'cr_comision_pct',
                'cr_comision_minimo',
                'cr_comision_tope',
                'iva_pct',
            ];
            for (const [entregaId, v] of Object.entries(data.detalles_por_entrega || {})) {
                const x = { ...(v || {}) };
                delete x.editar;
                for (const k of keysToNull) {
                    if (x[k] === '') x[k] = null;
                }
                det[entregaId] = x;
            }
            out.detalles_por_entrega = det;
            return out;
        })
        .post(route('operacion.manifiestos.emitir-guias', props.manifiesto.id), { preserveScroll: true });
};

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};

const formatMoney = (n) => {
    const x = Number(n || 0);
    return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const comprobanteTipoLabel = (tipo) => {
    if (tipo === 'guia_envio') return 'Guia no fiscal';
    if (tipo === 'factura_interna') return 'Factura';
    if (tipo === 'nota_credito_interna') return 'Nota de credito';
    return tipo || '-';
};

const recepcionConErrores = computed(() => (props.manifiesto.pedidos || []).filter((p) => p.recepcion_estado === 'con_error'));
const bloquearEmisionPorRecepcion = computed(() => recepcionConErrores.value.length > 0);
const pedidosSinControl = computed(() => (props.manifiesto.pedidos || []).filter((p) => !p.recepcion_estado));
const bloquearEmisionPorControlPendiente = computed(() => pedidosSinControl.value.length > 0);
</script>

<template>
    <AppLayout :title="`Facturacion / Manifiesto #${manifiesto.id}`">
        <Head :title="`Facturacion / Manifiesto #${manifiesto.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturacion / Manifiesto #{{ manifiesto.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ formatFecha(manifiesto.fecha) }} · {{ manifiesto.empresa?.razon_social || '-' }} · {{ manifiesto.deposito?.nombre || '-' }}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('facturacion.manifiestos.index')">
                        <SecondaryButton>Volver</SecondaryButton>
                    </Link>
                    <Link :href="route('operacion.manifiestos.show', manifiesto.id)">
                        <SecondaryButton>Ver en Operacion</SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded">
                {{ flashSuccess }}
            </div>

            <div v-if="flashError" class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded">
                {{ flashError }}
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Transporte</div>
                        <div class="mt-1 text-sm text-gray-900">{{ manifiesto.transporte || '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Chofer</div>
                        <div class="mt-1 text-sm text-gray-900">{{ manifiesto.chofer || '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Patentes</div>
                        <div class="mt-1 text-sm text-gray-900">
                            {{ manifiesto.patente_camion || '-' }}<span v-if="manifiesto.patente_acoplado"> / {{ manifiesto.patente_acoplado }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Pedidos</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ statsFacturacion.total }} cargados · {{ statsFacturacion.pendientes }} pendientes · {{ statsFacturacion.emitidos }} comprobantes</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <PrimaryButton :disabled="facturarPorEntrega.processing || !gruposFacturacion.length || faltanSelecciones || bloquearEmisionPorRecepcion || bloquearEmisionPorControlPendiente" @click.prevent="facturarSeleccionado">Emitir facturas</PrimaryButton>
                        <SecondaryButton v-if="permiteGuiasNoFiscales" :disabled="facturarPorEntrega.processing || !gruposFacturacion.length || faltanSelecciones || bloquearEmisionPorRecepcion || bloquearEmisionPorControlPendiente" @click.prevent="emitirGuias">Emitir guias</SecondaryButton>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <div class="text-sm font-medium text-gray-900">Facturacion</div>
                    <p class="mt-1 text-xs text-gray-600">Se crea 1 comprobante por cuenta de entrega. Elegi la cuenta a facturar por cada grupo.</p>
                    <p v-if="!permiteGuiasNoFiscales" class="mt-1 text-xs text-gray-500">Esta empresa no tiene habilitada la emision de guias no fiscales.</p>

                    <div v-if="faltanSelecciones && gruposFacturacion.length" class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                        Falta seleccionar "Facturar a" en uno o mas grupos.
                    </div>
                    <div v-if="bloquearEmisionPorRecepcion" class="mt-3 text-sm text-red-800 bg-red-50 border border-red-200 rounded-md px-3 py-2">
                        Hay pedidos recibidos con error. Debes revisarlos/corregirlos antes de emitir facturas o guias.
                    </div>
                    <div v-if="bloquearEmisionPorControlPendiente" class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                        Hay pedidos sin controlar. Solo se pueden facturar o emitir guias los pedidos ya controlados como correctos.
                    </div>

                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                        <div class="rounded-md border border-gray-200 bg-white px-3 py-2">
                            <div class="text-xs text-gray-500">Pedidos</div>
                            <div class="font-medium text-gray-900">{{ statsFacturacion.total }}</div>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-white px-3 py-2">
                            <div class="text-xs text-gray-500">Pendientes</div>
                            <div class="font-medium text-gray-900">{{ statsFacturacion.pendientes }}</div>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-white px-3 py-2">
                            <div class="text-xs text-gray-500">Sin entrega</div>
                            <div class="font-medium text-gray-900">{{ statsFacturacion.sinEntrega }}</div>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-white px-3 py-2">
                            <div class="text-xs text-gray-500">Comprobantes</div>
                            <div class="font-medium text-gray-900">{{ statsFacturacion.emitidos }}</div>
                        </div>
                    </div>

                    <div v-if="statsFacturacion.sinEntrega" class="mt-4 flex items-center justify-between gap-4 flex-wrap">
                        <div class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                            Hay pedidos pendientes sin cuenta de entrega. Primero completa cuentas para poder facturar.
                        </div>
                        <PrimaryButton :disabled="backfillForm.processing" @click.prevent="completarCuentas">Completar cuentas</PrimaryButton>
                    </div>

                    <div class="mt-4 space-y-2">
                        <div v-for="g in gruposFacturacion" :key="g.entregaId" class="rounded-lg bg-white border border-gray-200">
                            <div class="flex items-center justify-between gap-2 px-4 py-2 border-b border-gray-100 bg-gray-50">
                                <div class="text-xs font-medium text-gray-700">
                                    Entrega #{{ g.entregaId }} · {{ g.pedidos.length }} ped · Total: {{ detalleGrupo(g).moneda }} {{ formatMoney(detalleGrupo(g).total) }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <select
                                        v-model="facturarPorEntrega.facturar_por_entrega[g.entregaId]"
                                        class="border-gray-300 rounded text-xs py-1 px-1 w-44 focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">(facturar a)</option>
                                        <option v-for="c in g.cuentas" :key="c.id" :value="String(c.id)">
                                            {{ c.label }}{{ c.cuit ? ' CUIT ' + c.cuit : '' }}
                                        </option>
                                    </select>
                                    <select
                                        v-model="facturarPorEntrega.empresa_por_entrega[g.entregaId]"
                                        class="border-gray-300 rounded text-xs py-1 px-1 w-36 focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option v-for="emp in empresas" :key="emp.id" :value="String(emp.id)">
                                            {{ emp.razon_social }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remito</th>
                                            <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remitente</th>
                                            <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatario</th>
                                            <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                            <th class="px-3 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Palets</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="p in g.pedidos" :key="p.id" class="hover:bg-gray-50">
                                            <td class="px-3 py-1.5 whitespace-nowrap font-mono text-gray-900">{{ p.remito_numero || '-' }}</td>
                                            <td class="px-3 py-1.5 whitespace-nowrap">
                                                <div class="text-gray-900">{{ p.remitente?.razon_social || '-' }}</div>
                                                <div class="text-gray-500">{{ p.remitente?.cuit || '' }}</div>
                                            </td>
                                            <td class="px-3 py-1.5 whitespace-nowrap">
                                                <div class="text-gray-900">{{ p.destinatario?.razon_social || '-' }}</div>
                                                <div class="text-gray-500">{{ p.destinatario?.cuit || '' }}</div>
                                            </td>
                                            <td class="px-3 py-1.5 whitespace-nowrap text-center text-gray-700">{{ p.bultos }}</td>
                                            <td class="px-3 py-1.5 whitespace-nowrap text-center text-gray-700">{{ p.palets }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex items-center justify-between gap-2 px-4 py-2 border-t border-gray-100">
                                <div class="text-xs text-gray-600">
                                    Bultos: {{ detalleGrupo(g).bultos }} · Palets: {{ detalleGrupo(g).palets }} · Valor: {{ detalleGrupo(g).moneda }} {{ formatMoney(detalleGrupo(g).valorDeclarado) }} · CR: {{ detalleGrupo(g).moneda }} {{ formatMoney(detalleGrupo(g).crImporte) }}
                                    · Flete: {{ detalleGrupo(g).moneda }} {{ formatMoney(detalleGrupo(g).flete) }} · IVA: {{ detalleGrupo(g).moneda }} {{ formatMoney(detalleGrupo(g).iva) }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="text-xs text-gray-700 underline"
                                        @click.prevent="facturarPorEntrega.detalles_por_entrega[g.entregaId].editar = !facturarPorEntrega.detalles_por_entrega[g.entregaId].editar"
                                    >
                                        {{ facturarPorEntrega.detalles_por_entrega[g.entregaId].editar ? 'Ocultar edicion' : 'Editar tarifa' }}
                                    </button>
                                    <div v-if="!g.isSingleRelacion" class="text-[10px] text-gray-500">(mezcla)</div>
                                </div>
                            </div>

                            <div v-if="facturarPorEntrega.detalles_por_entrega[g.entregaId].editar" class="border-t border-gray-100 p-4 bg-gray-50">
                                <div class="text-xs font-medium text-gray-900 mb-2">Editar tarifa</div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                    <div>
                                        <InputLabel value="Moneda" />
                                        <select v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].moneda" class="mt-0.5 block w-full border-gray-300 rounded text-xs py-1 px-1">
                                            <option value="ARS">ARS</option>
                                            <option value="USD">USD</option>
                                            <option value="EUR">EUR</option>
                                            <option value="BRL">BRL</option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="Tarifa bulto" />
                                        <TextInput v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].tarifa_bulto" type="number" min="0" step="0.01" class="mt-0.5 block w-full text-xs" placeholder="(usa tarifa)" />
                                    </div>
                                    <div>
                                        <InputLabel value="Tarifa palet" />
                                        <TextInput v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].tarifa_palet" type="number" min="0" step="0.01" class="mt-0.5 block w-full text-xs" placeholder="(usa tarifa)" />
                                    </div>
                                    <div>
                                        <InputLabel value="% valor declarado" />
                                        <TextInput v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].tarifa_valor_declarado_pct" type="number" min="0" step="0.0001" class="mt-0.5 block w-full text-xs" placeholder="0.03" />
                                    </div>
                                    <div>
                                        <InputLabel value="Flete minimo" />
                                        <TextInput v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].flete_minimo" type="number" min="0" step="0.01" class="mt-0.5 block w-full text-xs" placeholder="(usa tarifa)" />
                                    </div>
                                    <div>
                                        <InputLabel value="% seguro" />
                                        <TextInput v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].seguro_pct" type="number" min="0" step="0.0001" class="mt-0.5 block w-full text-xs" placeholder="0.007" />
                                    </div>
                                    <div>
                                        <InputLabel value="% CR" />
                                        <TextInput v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].cr_comision_pct" type="number" min="0" step="0.0001" class="mt-0.5 block w-full text-xs" placeholder="0.025" />
                                    </div>
                                    <div>
                                        <InputLabel value="CR manual" />
                                        <TextInput v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].cr_importe_manual" type="number" min="0" step="0.01" class="mt-0.5 block w-full text-xs" placeholder="(usa CR pedidos)" />
                                    </div>
                                    <div>
                                        <InputLabel value="Comision CR manual" />
                                        <TextInput v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].comision_cr_manual" type="number" min="0" step="0.01" class="mt-0.5 block w-full text-xs" placeholder="(calcula automatica)" />
                                    </div>
                                    <div>
                                        <InputLabel value="% IVA" />
                                        <TextInput v-model="facturarPorEntrega.detalles_por_entrega[g.entregaId].iva_pct" type="number" min="0" step="0.0001" class="mt-0.5 block w-full text-xs" placeholder="0.21" />
                                    </div>
                                </div>
                                <div v-if="g.isSingleRelacion" class="mt-2 flex items-center gap-2">
                                    <Checkbox v-model:checked="facturarPorEntrega.detalles_por_entrega[g.entregaId].persistir_tarifa" />
                                    <span class="text-xs text-gray-700">Actualizar tarifa</span>
                                </div>
                                <div v-else class="mt-2 text-xs text-gray-500">No se puede persistir tarifa (mezcla remitente/destinatario).</div>
                            </div>
                        </div>

                        <div v-if="!gruposFacturacion.length" class="text-sm text-gray-600">No hay pedidos pendientes de facturar.</div>
                    </div>
                </div>

                <div v-if="(comprobantes || []).length" class="mt-6 rounded-lg border border-gray-200 bg-white overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="text-sm font-medium text-gray-900">Comprobantes emitidos</div>
                        <div class="mt-1 text-xs text-gray-600">Generados para este manifiesto.</div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facturar</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ARCA</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="c in comprobantes" :key="c.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                        <Link :href="route('operacion.comprobantes.show', c.id)" class="text-indigo-600 hover:text-indigo-800">#{{ c.id }}</Link>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ comprobanteTipoLabel(c.tipo) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <div class="font-medium text-gray-900">{{ c.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                                        <div class="text-xs text-gray-500">CUIT {{ c.entrega_cuenta?.tercero?.cuit || '-' }} · Nro {{ c.entrega_cuenta?.numero_cliente || '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        <div class="font-medium text-gray-900">{{ c.facturar_cuenta?.tercero?.razon_social || '-' }}</div>
                                        <div class="text-xs text-gray-500">CUIT {{ c.facturar_cuenta?.tercero?.cuit || '-' }} · Nro {{ c.facturar_cuenta?.numero_cliente || '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ String(c.fecha_emision || '').slice(0, 10) }}</td>
                                     <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ c.moneda }} {{ c.total }}</td>
                                     <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                         <div v-if="c.arca_cae" class="text-xs text-gray-700">
                                             <div>CAE {{ c.arca_cae }}</div>
                                             <div class="text-gray-500">Vto {{ String(c.arca_cae_vto || '').slice(0, 10) }}</div>
                                         </div>
                                         <div v-else-if="c.tipo === 'factura_interna'" class="flex justify-end gap-2 items-center">
                                             <select
                                                 v-model="tipoArcaPorComprobante[c.id]"
                                                 class="block border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs"
                                             >
                                                 <option v-for="op in c.arca_tipo_opciones || []" :key="op.code" :value="op.code">{{ op.label }}</option>
                                             </select>
                                             <button
                                                 type="button"
                                                 class="px-3 py-2 text-xs rounded border border-gray-200 bg-white hover:bg-gray-50"
                                                 :disabled="autorizarForm.processing || !(c.arca_tipo_opciones || []).length"
                                                 @click.prevent="autorizarArca(c.id, tipoArcaPorComprobante[c.id])"
                                             >
                                                 Autorizar ARCA
                                             </button>
                                         </div>
                                         <div v-else-if="c.tipo === 'nota_credito_interna' && c.comprobante_origen?.arca_tipo_cbte" class="flex justify-end gap-2">
                                             <button
                                                 type="button"
                                                 class="px-3 py-2 text-xs rounded border border-gray-200 bg-white hover:bg-gray-50"
                                                 :disabled="autorizarForm.processing"
                                                 @click.prevent="autorizarArca(c.id, c.comprobante_origen.arca_tipo_cbte)"
                                             >
                                                 Autorizar NC
                                             </button>
                                         </div>
                                         <div v-else class="text-xs text-gray-500">No fiscal</div>
                                      </td>
                                  </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
