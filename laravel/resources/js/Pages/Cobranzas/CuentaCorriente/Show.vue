<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { computed, ref } from 'vue';

const page = usePage();

const props = defineProps({
    cuenta: Object,
    movimientos: Array,
    comprobantes: Array,
    saldos: Object,
    bancos: Array,
});

const roles = computed(() => page.props.tt?.roles || []);
const esSoloCobrador = computed(() => roles.value.includes('cobrador') && !roles.value.includes('cobranzas_admin') && !roles.value.includes('admin'));
const comprobantesOpen = ref(false);
const movimientosOpen = ref(false);

const ajusteForm = useForm({ tipo: 'ajuste_debito', fecha: new Date().toISOString().slice(0, 10), moneda: 'ARS', importe: '', observacion: '' });
const notaForm = useForm({ tipo: 'nota_debito_manual', fecha: new Date().toISOString().slice(0, 10), moneda: 'ARS', importe: '', motivo: '' });
const emailPendientesForm = useForm({});

const reciboForm = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    moneda: 'ARS',
    comprobante_ids: [],
    send_email: !!(props.cuenta?.email),
    retenciones: {
        iibb: { descripcion: '', importe: '' },
        iva: { descripcion: '', importe: '' },
        ganancias: { descripcion: '', importe: '' },
    },
    items: [
        { medio: 'efectivo', importe: '', detalle: '', moneda: 'ARS', cheque_numero: '', cheque_banco: '', cheque_fecha_vencimiento: '', cheque_titular: '' },
    ],
});

const retencionesTotal = computed(() => Object.values(reciboForm.retenciones || {})
    .reduce((sum, retencion) => sum + (parseFloat(retencion?.importe) || 0), 0));

const reciboTotal = computed(() => reciboForm.items
    .reduce((sum, item) => sum + (parseFloat(item.importe) || 0), 0) + retencionesTotal.value);

const esCredito = (tipo) => {
    const t = String(tipo || '');
    return t.startsWith('nota_credito') || t.startsWith('ajuste_credito') || t === 'pago_a_cuenta';
};

const comprobantesPendientes = computed(() => {
    return (props.comprobantes || []).filter(c => {
        if (c.is_pagada) return false;
        const pend = parseFloat(c.pendiente ?? c.total);
        return Math.abs(pend) > 0.01;
    });
});
const emailPreviewOpen = ref(false);
const emailPreviewTotals = computed(() => {
    const totals = new Map();

    comprobantesPendientes.value.forEach((documento) => {
        const moneda = documento.moneda || 'ARS';
        const resumen = totals.get(moneda) || { moneda, a_pagar: 0, creditos: 0, saldo_neto: 0 };
        const pendiente = Number(documento.pendiente ?? documento.total ?? 0);

        if (documento.is_credit || pendiente < 0) {
            resumen.creditos += Math.abs(pendiente);
        } else {
            resumen.a_pagar += pendiente;
        }
        resumen.saldo_neto = resumen.a_pagar - resumen.creditos;
        totals.set(moneda, resumen);
    });

    return [...totals.values()].sort((a, b) => a.moneda.localeCompare(b.moneda));
});
const comprobantesSeleccionados = computed(() => (reciboForm.comprobante_ids || [])
    .map((id) => props.comprobantes.find((comprobante) => String(comprobante.id) === String(id)))
    .filter(Boolean));
const notasCreditoSeleccionadas = computed(() => comprobantesSeleccionados.value.filter((comprobante) => esCredito(comprobante.tipo) && String(comprobante.tipo).startsWith('nota_credito')));
const facturasSeleccionadas = computed(() => comprobantesSeleccionados.value.filter((comprobante) => !esCredito(comprobante.tipo) && parseFloat(comprobante.pendiente ?? comprobante.total) > 0.01));
const creditosSeleccionadosTotal = computed(() => comprobantesSeleccionados.value
    .filter((comprobante) => comprobante.is_credit || esCredito(comprobante.tipo) || parseFloat(comprobante.pendiente ?? comprobante.total) < 0)
    .reduce((sum, comprobante) => sum + Math.abs(parseFloat(comprobante.pendiente ?? comprobante.total) || 0), 0));
const imputandoCredito = ref(false);
const errorImputacionCredito = ref('');
const flashSuccess = computed(() => page.props.tt?.flash?.success || page.props.flash?.success || null);
const flashError = computed(() => page.props.tt?.flash?.error || page.props.flash?.error || null);

const selectedComprobantesTotal = computed(() => {
    return (reciboForm.comprobante_ids || []).reduce((sum, id) => {
        const c = props.comprobantes.find(c => String(c.id) === String(id));
        if (!c) return sum;
        const pendiente = parseFloat(c.pendiente ?? c.total);
        // Para pago_a_cuenta y notas, usar pendiente directo (ya con signo)
        if (c.is_credit || c.tipo === 'pago_a_cuenta') return sum + pendiente;
        return sum + pendiente;
    }, 0);
});

const submitRecibo = () => {
    if (notasCreditoSeleccionadas.value.length) {
        errorImputacionCredito.value = 'Primero imputá las notas de crédito seleccionadas a las facturas.';
        return;
    }
    const realIds = reciboForm.comprobante_ids.filter(id => Number.isInteger(Number(id)));
    reciboForm.comprobante_ids = realIds;
    reciboForm.post(route('cobranzas.ctacte.recibos.store', props.cuenta.id), { preserveScroll: true });
};

const imputarNotasCredito = () => {
    if (!notasCreditoSeleccionadas.value.length || !facturasSeleccionadas.value.length || imputandoCredito.value) return;

    imputandoCredito.value = true;
    errorImputacionCredito.value = '';
    router.post(route('cobranzas.ctacte.notas-credito.imputar', props.cuenta.id), {
        comprobante_ids: comprobantesSeleccionados.value.map((comprobante) => comprobante.id),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            reciboForm.comprobante_ids = [];
            errorImputacionCredito.value = '';
        },
        onError: (errors) => {
            errorImputacionCredito.value = errors.comprobante_ids || 'No se pudieron imputar las notas de crédito seleccionadas.';
        },
        onFinish: () => { imputandoCredito.value = false; },
    });
};

const agregarItem = () => {
    reciboForm.items.push({ medio: 'efectivo', importe: '', detalle: '', moneda: reciboForm.moneda, cheque_numero: '', cheque_banco: '', cheque_fecha_vencimiento: '', cheque_titular: '' });
};

const quitarItem = (idx) => {
    if (reciboForm.items.length > 1) {
        reciboForm.items.splice(idx, 1);
    }
};

const esCheque = (medio) => medio === 'cheque_tercero';

const submitAjuste = () => ajusteForm.post(route('cobranzas.ctacte.ajustes.store', props.cuenta.id), { preserveScroll: true });
const submitNota = () => notaForm.post(route('cobranzas.ctacte.notas.store', props.cuenta.id), { preserveScroll: true });
const enviarPendientesPorEmail = () => {
    emailPendientesForm.clearErrors();
    emailPreviewOpen.value = true;
};
const confirmarEnvioPendientes = () => emailPendientesForm.post(route('cobranzas.ctacte.email-pendientes', props.cuenta.id), {
    preserveScroll: true,
    onSuccess: () => { emailPreviewOpen.value = false; },
});
const numeroPendiente = (documento) => typeof documento.id === 'string'
    ? (documento.numero_interno || documento.numero || documento.id)
    : comprobanteNumero(documento);

const eliminarRecibo = (reciboId) => {
    if (!confirm('¿Eliminar recibo? Esta accion no se puede deshacer.')) return;
    router.delete(route('cobranzas.recibos.destroy', reciboId), { preserveScroll: true });
};

const tipoLabel = (tipo) => {
    const map = {
        factura_interna: 'Factura',
        nota_debito_manual: 'Nota debito',
        nota_credito_manual: 'Nota credito',
        ajuste_debito: 'Ajuste debito',
        ajuste_credito: 'Ajuste credito',
        pago_a_cuenta: 'Pago a cuenta',
    };
    return map[tipo] || tipo;
};
const formatFecha = (value) => {
    if (!value) return '-';
    const s = String(value).slice(0, 10);
    const d = new Date(s + 'T12:00:00');
    if (isNaN(d.getTime())) return s.split('-').reverse().join('-');
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}-${mm}-${yyyy}`;
};
const comprobanteNumero = (c) => {
    if (c.arca_punto_venta && c.arca_numero) {
        return String(parseInt(c.arca_punto_venta)) + '-' + String(c.arca_numero).padStart(8, '0');
    }
    if (c.numero_interno) return '#' + c.numero_interno;
    return '-';
};
const formatNum = (n) => {
    const val = Number(n || 0);
    return val.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
    <AppLayout :title="`Cuenta corriente #${cuenta.id}`">
        <Head :title="`Cuenta corriente #${cuenta.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cuenta corriente</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-base font-semibold text-indigo-900">{{ cuenta.tercero?.razon_social || '-' }}</span>
                        <span class="text-xs text-gray-500">CUIT {{ cuenta.tercero?.cuit || '-' }}</span>
                    </div>
                    <div v-if="cuenta.empresa?.razon_social" class="mt-1 text-xs text-gray-500">Empresa: {{ cuenta.empresa.razon_social }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2 justify-end">
                    <a :href="route('cobranzas.ctacte.print', { cuenta: cuenta.id, tipo: 'pendientes' })" target="_blank" rel="noopener"><SecondaryButton>Imprimir pendientes</SecondaryButton></a>
                    <a :href="route('cobranzas.ctacte.print', { cuenta: cuenta.id, tipo: 'movimientos' })" target="_blank" rel="noopener"><SecondaryButton>Imprimir movimientos</SecondaryButton></a>
                    <PrimaryButton :disabled="!cuenta.email || emailPendientesForm.processing" :title="cuenta.email ? `Enviar a ${cuenta.email}` : 'La cuenta no tiene email cargado'" @click="enviarPendientesPorEmail">
                        {{ emailPendientesForm.processing ? 'Enviando...' : 'Enviar pendientes por email' }}
                    </PrimaryButton>
                    <Link :href="route('cobranzas.ctacte.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div v-if="flashSuccess" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ flashSuccess }}</div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ flashError }}</div>
            <div v-if="errorImputacionCredito" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ errorImputacionCredito }}</div>
            <div class="bg-white shadow sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div><div class="text-xs text-gray-500">Zona</div><div class="text-sm font-medium text-gray-900">{{ cuenta.zona?.nombre || 'Sin zona' }}</div></div>
                <div><div class="text-xs text-gray-500">Ciudad</div><div class="text-sm font-medium text-gray-900">{{ cuenta.localidad || 'Sin ciudad' }}</div></div>
                <div class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2">
                    <div class="text-xs font-medium text-indigo-700">Saldo total</div>
                    <div class="text-lg font-semibold leading-6 text-indigo-900">{{ formatNum(saldos.saldo_total) }}</div>
                </div>
                <div><div class="text-xs text-gray-500">Vencido +30</div><div class="text-sm font-medium" :class="saldos.vencido_30 > 0 ? 'text-red-700' : 'text-gray-900'">{{ formatNum(saldos.vencido_30) }}</div></div>
                <div><div class="text-xs text-gray-500">Retenciones</div><div class="text-sm font-medium text-blue-700">{{ formatNum(saldos.retenciones) }}</div></div>
                <div><div class="text-xs text-gray-500">Saldo a cancelar</div><div class="text-sm font-medium" :class="saldos.saldo_a_cancelar > 0 ? 'text-green-700' : 'text-gray-900'">{{ formatNum(saldos.saldo_a_cancelar) }}</div></div>
            </div>

            <div class="space-y-3">
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-900">Emitir recibo</h3>
                    <div class="mt-2 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <TextInput v-model="reciboForm.fecha" type="date" class="block w-full text-xs" />
                            <select v-model="reciboForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                        </div>
                        <InputError :message="reciboForm.errors.fecha" />
                        <InputError :message="reciboForm.errors.moneda" />
                        <fieldset class="border border-gray-200 rounded p-1 max-h-40 overflow-y-auto">
                            <legend class="text-xs text-gray-500 px-1">Comprobantes a cancelar (opcional)</legend>
                            <div v-for="c in comprobantesPendientes" :key="c.id" class="grid grid-cols-[auto,minmax(0,1fr),minmax(5rem,auto)] items-center gap-1.5 py-0.5">
                                <input type="checkbox" :value="c.id" v-model="reciboForm.comprobante_ids" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 size-3.5" />
                                <span class="min-w-0 truncate text-xs" :class="c.is_credit ? 'text-green-600' : 'text-gray-700'">{{ tipoLabel(c.tipo) }} {{ comprobanteNumero(c) }} <span class="text-[10px] text-gray-400">{{ formatFecha(c.fecha_emision) }}</span></span>
                                <span class="whitespace-nowrap text-right text-xs tabular-nums" :class="c.is_credit ? 'font-medium text-green-600' : 'text-gray-700'">{{ c.moneda }} {{ formatNum(c.pendiente ?? c.total) }}</span>
                            </div>
                            <div v-if="!comprobantesPendientes.length" class="text-xs text-gray-400 py-0.5">Sin comprobantes pendientes</div>
                        </fieldset>
                        <div v-if="reciboForm.comprobante_ids.length" class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-2 text-xs font-semibold">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span class="text-gray-800">Acumulado neto seleccionado: {{ formatNum(selectedComprobantesTotal) }}</span>
                                <span class="text-green-700">Crédito NC / recibos: −{{ formatNum(creditosSeleccionadosTotal) }}</span>
                            </div>
                            <button v-if="!notasCreditoSeleccionadas.length" type="button" class="text-indigo-600 hover:text-indigo-800 underline" @click="reciboForm.items[0].importe = selectedComprobantesTotal">Completar</button>
                        </div>
                        <div v-if="notasCreditoSeleccionadas.length" class="rounded border border-blue-200 bg-blue-50 p-2 text-xs text-blue-900">
                            Seleccioná las facturas destino y aplicá el crédito. Después, si queda saldo en efectivo o cheque, emití el recibo.
                            <PrimaryButton class="mt-2 !px-2 !py-1 !text-xs" :disabled="imputandoCredito || !facturasSeleccionadas.length" @click="imputarNotasCredito">
                                {{ imputandoCredito ? 'Imputando...' : 'Imputar créditos a facturas' }}
                            </PrimaryButton>
                        </div>
                        <InputError :message="reciboForm.errors.comprobante_ids" />

                        <div class="border border-gray-200 rounded p-2 space-y-2">
                            <div class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Medios de pago</div>
                            <div v-for="(item, idx) in reciboForm.items" :key="idx" class="border border-gray-100 rounded p-1.5 space-y-1.5">
                                <div class="flex items-center gap-1.5">
                                    <select v-model="item.medio" class="block w-full border-gray-300 rounded-md shadow-sm text-xs">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="transferencia">Transferencia</option>
                                        <option value="cheque_tercero">Cheque tercero</option>
                                    </select>
                                    <TextInput v-model="item.importe" type="number" min="0.01" step="0.01" class="block w-28 text-xs" placeholder="Importe" />
                                    <button type="button" class="text-red-500 text-base leading-none font-bold" @click="quitarItem(idx)" :disabled="reciboForm.items.length <= 1">&times;</button>
                                </div>
                                <div v-if="esCheque(item.medio)" class="grid grid-cols-2 gap-1.5">
                                    <TextInput v-model="item.cheque_numero" type="text" class="block w-full text-xs" placeholder="Nro cheque" />
                                    <select v-model="item.cheque_banco" class="block w-full border-gray-300 rounded-md shadow-sm text-xs">
                                        <option value="">Banco</option>
                                        <option v-for="b in bancos" :key="b.id" :value="b.nombre">{{ b.nombre }}</option>
                                    </select>
                                    <TextInput v-model="item.cheque_fecha_vencimiento" type="date" class="block w-full text-xs" />
                                    <TextInput v-model="item.cheque_titular" type="text" class="block w-full text-xs" placeholder="Titular" />
                                </div>
                                <TextInput v-model="item.detalle" type="text" class="block w-full text-xs" placeholder="Detalle (opcional)" />
                            </div>
                            <SecondaryButton type="button" class="!text-xs !px-2 !py-1" @click="agregarItem">+ Agregar medio</SecondaryButton>
                        </div>

                        <div class="border border-gray-200 rounded p-2 space-y-1">
                            <div class="text-xs font-semibold text-gray-700 uppercase tracking-wider">Retenciones (opcional)</div>
                            <div v-for="(label, key) in { iibb: 'IIBB', iva: 'IVA', ganancias: 'Ganancias' }" :key="key" class="grid grid-cols-3 gap-1 items-end">
                                <div><label class="text-xs text-gray-500">{{ label }}</label></div>
                                <div><input v-model="reciboForm.retenciones[key].descripcion" type="text" class="block w-full border-gray-300 rounded shadow-sm text-xs" placeholder="Descripcion" /></div>
                                <div><input v-model="reciboForm.retenciones[key].importe" type="number" min="0" step="0.01" class="block w-full border-gray-300 rounded shadow-sm text-xs" placeholder="Importe" /></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <label v-if="cuenta.email" class="flex items-center gap-1.5 text-xs text-gray-700">
                                <input type="checkbox" v-model="reciboForm.send_email" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 size-3.5" />
                                Enviar email
                            </label>
                            <div class="flex items-center gap-2">
                                <div class="text-xs font-semibold text-gray-900">Total: {{ formatNum(reciboTotal) }}</div>
                                <PrimaryButton class="!text-xs !px-3 !py-1.5" :disabled="reciboForm.processing || notasCreditoSeleccionadas.length > 0" @click="submitRecibo">Emitir recibo</PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!esSoloCobrador" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white shadow sm:rounded-lg p-3">
                        <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wider">Ajuste</h3>
                        <div class="mt-2 space-y-2">
                            <select v-model="ajusteForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option value="ajuste_debito">Ajuste debito</option><option value="ajuste_credito">Ajuste credito</option></select>
                            <div class="grid grid-cols-3 gap-2">
                                <TextInput v-model="ajusteForm.fecha" type="date" class="block w-full text-xs" />
                                <select v-model="ajusteForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                                <TextInput v-model="ajusteForm.importe" type="number" min="0.01" step="0.01" class="block w-full text-xs" placeholder="Importe" />
                            </div>
                            <InputError :message="ajusteForm.errors.fecha" />
                            <InputError :message="ajusteForm.errors.moneda" />
                            <InputError :message="ajusteForm.errors.importe" />
                            <TextInput v-model="ajusteForm.observacion" type="text" class="block w-full text-xs" placeholder="Observacion" />
                            <InputError :message="ajusteForm.errors.observacion" />
                            <PrimaryButton class="!text-xs !px-3 !py-1.5" :disabled="ajusteForm.processing" @click="submitAjuste">Guardar</PrimaryButton>
                        </div>
                    </div>

                    <div class="bg-white shadow sm:rounded-lg p-3">
                        <h3 class="text-xs font-semibold text-gray-900 uppercase tracking-wider">Nota debito / credito</h3>
                        <div class="mt-2 space-y-2">
                            <select v-model="notaForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option value="nota_debito_manual">Nota debito</option><option value="nota_credito_manual">Nota credito</option></select>
                            <div class="grid grid-cols-3 gap-2">
                                <TextInput v-model="notaForm.fecha" type="date" class="block w-full text-xs" />
                                <select v-model="notaForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                                <TextInput v-model="notaForm.importe" type="number" min="0.01" step="0.01" class="block w-full text-xs" placeholder="Importe" />
                            </div>
                            <InputError :message="notaForm.errors.fecha" />
                            <InputError :message="notaForm.errors.moneda" />
                            <InputError :message="notaForm.errors.importe" />
                            <TextInput v-model="notaForm.motivo" type="text" class="block w-full text-xs" placeholder="Motivo" />
                            <InputError :message="notaForm.errors.motivo" />
                            <PrimaryButton class="!text-xs !px-3 !py-1.5" :disabled="notaForm.processing" @click="submitNota">Generar</PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <button
                    type="button"
                    class="flex w-full items-center justify-between p-4 text-left hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                    :aria-expanded="comprobantesOpen"
                    aria-controls="cuenta-comprobantes"
                    @click="comprobantesOpen = !comprobantesOpen"
                >
                    <span class="text-sm font-semibold text-gray-900">Comprobantes</span>
                    <svg class="h-4 w-4 text-gray-500 transition-transform" :class="comprobantesOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div v-if="comprobantesOpen" id="cuenta-comprobantes" class="border-t border-gray-200">
                <div class="space-y-3 p-4 sm:hidden">
                        <div v-for="c in comprobantes" :key="c.id" class="rounded-lg border p-2" :class="c.is_pagada ? 'border-green-200 bg-green-50/40' : 'border-gray-200 bg-white'">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-xs font-semibold text-gray-900">{{ tipoLabel(c.tipo) }} {{ comprobanteNumero(c) }} <span v-if="c.is_pagada" class="ml-1 inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-800">Pagada</span><span v-else-if="!c.is_credit" class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">Pendiente</span></div>
                                <div class="flex items-center justify-between gap-3 text-xs text-gray-500">
                                    <span>{{ formatFecha(c.fecha_emision) }}</span>
                                    <span class="whitespace-nowrap text-right tabular-nums" :class="c.is_credit ? 'font-medium text-green-700' : 'text-gray-700'">Pend: {{ c.moneda }} {{ formatNum(c.pendiente ?? c.total) }}</span>
                                </div>
                            </div>
                            <span v-if="c.entregado" class="inline-flex shrink-0 items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-[10px] font-medium text-green-800">
                                <span aria-hidden="true">✓</span> {{ formatFecha(c.fecha_entrega) }}
                            </span>
                            <Link v-if="!c.is_credit" class="text-xs text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', c.id)">Ver</Link>
                            <span v-else class="text-xs text-gray-400 italic">Pago a cuenta</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-3 text-xs font-medium text-gray-900"><span>Total</span><span class="whitespace-nowrap text-right tabular-nums">{{ c.moneda }} {{ formatNum(c.total) }}</span></div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comprobante</th><th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pendiente</th><th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th><th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th><th class="px-2 py-1 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead>
                            <tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes" :key="c.id" :class="c.is_pagada ? 'bg-green-50/40' : ''"><td class="px-2 py-1 text-xs text-gray-900">{{ tipoLabel(c.tipo) }} {{ comprobanteNumero(c) }}</td><td class="px-2 py-1 text-xs text-gray-700">{{ formatFecha(c.fecha_emision) }}</td><td class="px-2 py-1 text-right text-xs text-gray-700 tabular-nums">{{ c.moneda }} {{ formatNum(c.total) }}</td><td class="px-2 py-1 text-right text-xs tabular-nums" :class="c.is_pagada ? 'text-green-700 font-medium' : (c.is_credit ? 'text-green-700 font-medium' : 'text-amber-700')">{{ c.moneda }} {{ formatNum(c.pendiente ?? c.total) }}</td><td class="px-2 py-1 text-xs"><span v-if="c.is_pagada" class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-800">Pagada</span><span v-else-if="c.is_credit" class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium text-blue-800">Crédito</span><span v-else class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800">Pendiente</span></td><td class="px-2 py-1 text-xs text-green-700"><span v-if="c.entregado" class="font-medium">✓ {{ formatFecha(c.fecha_entrega) }}</span><span v-else class="text-gray-400">-</span></td><td class="px-2 py-1 text-right text-xs"><Link v-if="!c.is_credit" class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', c.id)">Ver</Link><span v-else class="text-xs text-gray-400 italic">Pago a cuenta</span></td></tr></tbody></table>
                </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <button
                    type="button"
                    class="flex w-full items-center justify-between p-4 text-left hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                    :aria-expanded="movimientosOpen"
                    aria-controls="cuenta-movimientos"
                    @click="movimientosOpen = !movimientosOpen"
                >
                    <span class="text-sm font-semibold text-gray-900">Movimientos</span>
                    <svg class="h-4 w-4 text-gray-500 transition-transform" :class="movimientosOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div v-if="movimientosOpen" id="cuenta-movimientos" class="border-t border-gray-200">
                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="m in movimientos" :key="m.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold" :class="m.tipo === 'anulacion_recibo' ? 'text-red-700' : 'text-gray-900'">{{ tipoLabel(m.tipo) }}</div>
                                <div class="text-xs text-gray-500">{{ formatFecha(m.fecha) }}</div>
                            </div>
                            <div class="text-sm font-medium" :class="m.tipo === 'anulacion_recibo' ? 'text-red-700' : 'text-gray-900'">{{ formatNum(m.importe_signed) }}</div>
                        </div>
                        <div class="mt-2 grid grid-cols-1 gap-2 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Moneda</div>
                                <div class="font-medium text-gray-900">{{ m.moneda }} <span v-if="m.moneda !== 'ARS'" class="text-xs text-gray-500">({{ formatNum(m.cotizacion_ars) }})</span></div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Comprobante</div>
                                <div class="font-medium text-gray-900"><template v-if="m.comprobante_tipo && m.comprobante_numero">{{ tipoLabel(m.comprobante_tipo) }} {{ m.comprobante_numero }}</template><span v-else class="text-gray-400">-</span></div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Observacion</div>
                                <div class="font-medium text-gray-900">{{ m.observacion || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Referencia</div>
                                <div class="font-medium text-gray-900">
                                    <Link v-if="m.referencia_tipo === 'comprobante' && m.referencia_id" class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', m.referencia_id)">Comprobante</Link>
                                    <Link v-else-if="m.referencia_tipo === 'recibo' && m.referencia_id" class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.show', m.referencia_id)">Recibo {{ m.recibo_numero || '' }}</Link>
                                    <span v-else>-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comprobante</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ref.</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200"><tr v-for="m in movimientos" :key="m.id"><td class="px-4 py-2 text-sm text-gray-700">{{ formatFecha(m.fecha) }}</td><td class="px-4 py-2 text-sm" :class="m.tipo === 'anulacion_recibo' ? 'text-red-700 font-medium' : 'text-gray-700'">{{ tipoLabel(m.tipo) }}</td><td class="px-4 py-2 text-sm text-gray-700"><template v-if="m.comprobante_tipo && m.comprobante_numero">{{ tipoLabel(m.comprobante_tipo) }} {{ m.comprobante_numero }}</template><span v-else class="text-gray-400">-</span></td><td class="px-4 py-2 text-sm text-gray-700">{{ m.moneda }} <span v-if="m.moneda !== 'ARS'" class="text-xs text-gray-500">({{ formatNum(m.cotizacion_ars) }})</span></td><td class="px-4 py-2 text-sm" :class="m.tipo === 'anulacion_recibo' ? 'text-red-700 font-medium' : 'text-gray-700'">{{ formatNum(m.importe_signed) }}</td><td class="px-4 py-2 text-sm text-gray-700">{{ m.observacion || '-' }}</td><td class="px-4 py-2 text-right text-sm"><Link v-if="m.referencia_tipo === 'comprobante' && m.referencia_id" class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', m.referencia_id)">Comprobante</Link><span v-else-if="m.referencia_tipo === 'recibo' && m.referencia_id"><Link class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.show', m.referencia_id)">Recibo {{ m.recibo_numero || '' }}</Link><button class="ml-1 text-red-500 hover:text-red-700" @click="eliminarRecibo(m.referencia_id)" title="Eliminar recibo">&times;</button></span><span v-else>-</span></td></tr></tbody>
                    </table>
                </div>
                </div>
            </div>
        </div>

        <DialogModal :show="emailPreviewOpen" @close="emailPreviewOpen = false">
            <template #title>Vista previa del email</template>
            <template #content>
                <div class="space-y-3">
                    <p class="text-sm text-gray-700">Se enviará a <strong>{{ cuenta.email }}</strong> el listado de documentos pendientes y créditos disponibles.</p>
                    <div v-if="comprobantesPendientes.length" class="max-h-72 overflow-auto rounded border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr>
                                    <th class="px-2 py-1.5 text-left font-medium text-gray-600">Documento</th>
                                    <th class="px-2 py-1.5 text-left font-medium text-gray-600">Fecha</th>
                                    <th class="px-2 py-1.5 text-right font-medium text-gray-600">Total</th>
                                    <th class="px-2 py-1.5 text-right font-medium text-gray-600">Pendiente / crédito</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="documento in comprobantesPendientes" :key="documento.id">
                                    <td class="px-2 py-1.5 text-gray-800">{{ tipoLabel(documento.tipo) }} {{ numeroPendiente(documento) }}</td>
                                    <td class="px-2 py-1.5 text-gray-600">{{ formatFecha(documento.fecha_emision) }}</td>
                                    <td class="px-2 py-1.5 text-right text-gray-700">{{ documento.moneda }} {{ formatNum(documento.total) }}</td>
                                    <td class="px-2 py-1.5 text-right" :class="documento.is_credit ? 'font-medium text-green-700' : 'text-gray-800'">{{ documento.moneda }} {{ formatNum(documento.pendiente ?? documento.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="rounded border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">No hay documentos pendientes; se enviará el aviso igualmente.</div>

                    <div v-if="emailPreviewTotals.length" class="space-y-2">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600">Totales por moneda</h4>
                        <div v-for="total in emailPreviewTotals" :key="total.moneda" class="grid grid-cols-2 gap-x-3 gap-y-1 rounded bg-gray-50 p-2 text-xs sm:grid-cols-4">
                            <strong class="text-gray-800">{{ total.moneda }}</strong>
                            <span class="text-right text-gray-700">Facturas + ND: {{ formatNum(total.a_pagar) }}</span>
                            <span class="text-right text-green-700">NC + recibos: −{{ formatNum(total.creditos) }}</span>
                            <strong class="text-right text-gray-900">Neto: {{ formatNum(total.saldo_neto) }}</strong>
                        </div>
                    </div>
                    <InputError :message="emailPendientesForm.errors.email" />
                    <InputError :message="emailPendientesForm.errors.documentos" />
                </div>
            </template>
            <template #footer>
                <SecondaryButton :disabled="emailPendientesForm.processing" @click="emailPreviewOpen = false">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="emailPendientesForm.processing || !cuenta.email" @click="confirmarEnvioPendientes">
                    {{ emailPendientesForm.processing ? 'Enviando...' : 'Confirmar y enviar' }}
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
