<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';

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

const ajusteForm = useForm({ tipo: 'ajuste_debito', fecha: new Date().toISOString().slice(0, 10), moneda: 'ARS', importe: '', observacion: '' });
const notaForm = useForm({ tipo: 'nota_debito_manual', fecha: new Date().toISOString().slice(0, 10), moneda: 'ARS', importe: '', motivo: '' });

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

const reciboTotal = computed(() => {
    return reciboForm.items.reduce((sum, item) => sum + (parseFloat(item.importe) || 0), 0);
});

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
const submitRecibo = () => reciboForm.post(route('cobranzas.ctacte.recibos.store', props.cuenta.id), { preserveScroll: true });

const formatFecha = (value) => value ? String(value).slice(0, 10) : '-';
const comprobanteNumero = (c) => {
    if (c.arca_punto_venta && c.arca_numero) {
        return String(parseInt(c.arca_punto_venta)) + '-' + String(c.arca_numero).padStart(8, '0');
    }
    if (c.numero) return c.numero;
    if (c.numero_interno) return '#' + c.numero_interno;
    return c.tipo;
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
                    <div class="mt-1 text-sm text-gray-600">{{ cuenta.tercero?.razon_social || '-' }} · CUIT {{ cuenta.tercero?.cuit || '-' }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2 justify-end">
                    <a :href="route('cobranzas.ctacte.print', cuenta.id)" target="_blank"><SecondaryButton>Imprimir / PDF</SecondaryButton></a>
                    <Link :href="route('cobranzas.ctacte.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div><div class="text-xs text-gray-500">Zona</div><div class="text-sm font-medium text-gray-900">{{ cuenta.zona?.nombre || 'Sin zona' }}</div></div>
                <div><div class="text-xs text-gray-500">Ciudad</div><div class="text-sm font-medium text-gray-900">{{ cuenta.localidad || 'Sin ciudad' }}</div></div>
                <div><div class="text-xs text-gray-500">Saldo total</div><div class="text-sm font-medium text-gray-900">{{ formatNum(saldos.saldo_total) }}</div></div>
                <div><div class="text-xs text-gray-500">Vencido +30</div><div class="text-sm font-medium" :class="saldos.vencido_30 > 0 ? 'text-red-700' : 'text-gray-900'">{{ formatNum(saldos.vencido_30) }}</div></div>
            </div>

            <div class="grid grid-cols-1 gap-6" :class="esSoloCobrador ? '' : 'lg:grid-cols-3'">
                <!-- Ajuste panel (solo cobranzas_admin/admin, no cobrador) -->
                <div v-if="!esSoloCobrador" class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Generar ajuste</h3>
                    <div class="mt-4 space-y-3">
                        <select v-model="ajusteForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="ajuste_debito">Ajuste debito</option><option value="ajuste_credito">Ajuste credito</option></select>
                        <TextInput v-model="ajusteForm.fecha" type="date" class="block w-full" />
                        <InputError :message="ajusteForm.errors.fecha" />
                        <select v-model="ajusteForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                        <InputError :message="ajusteForm.errors.moneda" />
                        <TextInput v-model="ajusteForm.importe" type="number" min="0.01" step="0.01" class="block w-full" placeholder="Importe" />
                        <InputError :message="ajusteForm.errors.importe" />
                        <TextInput v-model="ajusteForm.observacion" type="text" class="block w-full" placeholder="Observacion" />
                        <InputError :message="ajusteForm.errors.observacion" />
                        <PrimaryButton :disabled="ajusteForm.processing" @click="submitAjuste">Guardar ajuste</PrimaryButton>
                    </div>
                </div>

                <!-- Nota panel (solo cobranzas_admin/admin, no cobrador) -->
                <div v-if="!esSoloCobrador" class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Nota de debito / credito</h3>
                    <div class="mt-4 space-y-3">
                        <select v-model="notaForm.tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="nota_debito_manual">Nota de debito</option><option value="nota_credito_manual">Nota de credito</option></select>
                        <TextInput v-model="notaForm.fecha" type="date" class="block w-full" />
                        <InputError :message="notaForm.errors.fecha" />
                        <select v-model="notaForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                        <InputError :message="notaForm.errors.moneda" />
                        <TextInput v-model="notaForm.importe" type="number" min="0.01" step="0.01" class="block w-full" placeholder="Importe" />
                        <InputError :message="notaForm.errors.importe" />
                        <TextInput v-model="notaForm.motivo" type="text" class="block w-full" placeholder="Motivo" />
                        <InputError :message="notaForm.errors.motivo" />
                        <PrimaryButton :disabled="notaForm.processing" @click="submitNota">Generar nota</PrimaryButton>
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900">Emitir recibo</h3>
                    <div class="mt-4 space-y-3">
                        <TextInput v-model="reciboForm.fecha" type="date" class="block w-full" />
                        <InputError :message="reciboForm.errors.fecha" />
                        <select v-model="reciboForm.moneda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select>
                        <InputError :message="reciboForm.errors.moneda" />
                        <fieldset class="border border-gray-200 rounded-md p-2 max-h-48 overflow-y-auto">
                            <legend class="text-xs text-gray-500 px-1">Comprobantes a cancelar (opcional)</legend>
                            <div v-for="c in comprobantes" :key="c.id" class="flex items-center gap-2 py-1">
                                <input type="checkbox" :value="c.id" v-model="reciboForm.comprobante_ids" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">{{ c.tipo }} {{ comprobanteNumero(c) }} · {{ c.moneda }} {{ formatNum(c.total) }}</span>
                            </div>
                            <div v-if="!comprobantes.length" class="text-xs text-gray-400 py-1">Sin comprobantes pendientes</div>
                        </fieldset>
                        <InputError :message="reciboForm.errors.comprobante_ids" />

                        <div class="border border-gray-200 rounded-md p-3 space-y-3">
                            <div class="text-sm font-medium text-gray-700">Medios de pago</div>
                            <div v-for="(item, idx) in reciboForm.items" :key="idx" class="border border-gray-100 rounded p-2 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <select v-model="item.medio" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="transferencia">Transferencia</option>
                                        <option value="cheque_tercero">Cheque tercero</option>
                                    </select>
                                    <TextInput v-model="item.importe" type="number" min="0.01" step="0.01" class="block w-32" placeholder="Importe" />
                                    <button type="button" class="text-red-500 text-lg leading-none font-bold" @click="quitarItem(idx)" :disabled="reciboForm.items.length <= 1">&times;</button>
                                </div>
                                <div v-if="esCheque(item.medio)" class="grid grid-cols-2 gap-2">
                                    <TextInput v-model="item.cheque_numero" type="text" class="block w-full" placeholder="Nro cheque" />
                                    <select v-model="item.cheque_banco" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">Banco</option>
                                        <option v-for="b in bancos" :key="b.id" :value="b.nombre">{{ b.nombre }}</option>
                                    </select>
                                    <TextInput v-model="item.cheque_fecha_vencimiento" type="date" class="block w-full" />
                                    <TextInput v-model="item.cheque_titular" type="text" class="block w-full" placeholder="Titular" />
                                </div>
                                <TextInput v-model="item.detalle" type="text" class="block w-full" placeholder="Detalle (opcional)" />
                            </div>
                            <SecondaryButton type="button" @click="agregarItem">+ Agregar medio</SecondaryButton>
                        </div>

                        <div class="border border-gray-200 rounded-md p-3 space-y-2">
                            <div class="text-sm font-medium text-gray-700">Retenciones (opcional)</div>
                            <div class="space-y-2">
                                <div class="grid grid-cols-3 gap-2 items-end">
                                    <div><label class="block text-xs text-gray-500">IIBB</label></div>
                                    <div><input v-model="reciboForm.retenciones.iibb.descripcion" type="text" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Descripcion" /></div>
                                    <div><input v-model="reciboForm.retenciones.iibb.importe" type="number" min="0" step="0.01" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Importe" /></div>
                                </div>
                                <div class="grid grid-cols-3 gap-2 items-end">
                                    <div><label class="block text-xs text-gray-500">IVA</label></div>
                                    <div><input v-model="reciboForm.retenciones.iva.descripcion" type="text" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Descripcion" /></div>
                                    <div><input v-model="reciboForm.retenciones.iva.importe" type="number" min="0" step="0.01" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Importe" /></div>
                                </div>
                                <div class="grid grid-cols-3 gap-2 items-end">
                                    <div><label class="block text-xs text-gray-500">Ganancias</label></div>
                                    <div><input v-model="reciboForm.retenciones.ganancias.descripcion" type="text" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Descripcion" /></div>
                                    <div><input v-model="reciboForm.retenciones.ganancias.importe" type="number" min="0" step="0.01" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Importe" /></div>
                                </div>
                            </div>
                        </div>

                        <div class="text-sm font-semibold text-gray-900 text-right">Total: {{ formatNum(reciboTotal) }}</div>

                        <label v-if="cuenta.email" class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" v-model="reciboForm.send_email" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            Enviar recibo por email
                        </label>
                        <PrimaryButton :disabled="reciboForm.processing" @click="submitRecibo">Emitir recibo</PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Comprobantes</h3></div>
                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="c in comprobantes" :key="c.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ c.tipo }} {{ comprobanteNumero(c) }}</div>
                                <div class="text-xs text-gray-500">{{ formatFecha(c.fecha_emision) }}</div>
                            </div>
                            <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', c.id)">Ver</Link>
                        </div>
                        <div class="mt-3 text-sm font-medium text-gray-900">{{ c.moneda }} {{ formatNum(c.total) }}</div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comprobante</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th></tr></thead>
                            <tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes" :key="c.id"><td class="px-6 py-4 text-sm text-gray-900">{{ c.tipo }} {{ comprobanteNumero(c) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(c.fecha_emision) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ formatNum(c.total) }}</td><td class="px-6 py-4 text-right text-sm"><Link class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', c.id)">Ver</Link></td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Movimientos</h3></div>
                <div class="space-y-3 p-4 sm:hidden">
                    <div v-for="m in movimientos" :key="m.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ m.tipo }}</div>
                                <div class="text-xs text-gray-500">{{ formatFecha(m.fecha) }}</div>
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ formatNum(m.importe_signed) }}</div>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Moneda</div>
                                <div class="font-medium text-gray-900">{{ m.moneda }} <span v-if="m.moneda !== 'ARS'" class="text-xs text-gray-500">({{ formatNum(m.cotizacion_ars) }})</span></div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Observacion</div>
                                <div class="font-medium text-gray-900">{{ m.observacion || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Referencia</div>
                                <div class="font-medium text-gray-900">
                                    <Link v-if="m.referencia_tipo === 'comprobante' && m.referencia_id" class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', m.referencia_id)">Comprobante</Link>
                                    <Link v-else-if="m.referencia_tipo === 'recibo' && m.referencia_id" class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.show', m.referencia_id)">Recibo</Link>
                                    <span v-else>-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moneda</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ref.</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200"><tr v-for="m in movimientos" :key="m.id"><td class="px-6 py-4 text-sm text-gray-700">{{ formatFecha(m.fecha) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.tipo }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.moneda }} <span v-if="m.moneda !== 'ARS'" class="text-xs text-gray-500">({{ formatNum(m.cotizacion_ars) }})</span></td><td class="px-6 py-4 text-sm text-gray-700">{{ formatNum(m.importe_signed) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ m.observacion || '-' }}</td><td class="px-6 py-4 text-right text-sm"><Link v-if="m.referencia_tipo === 'comprobante' && m.referencia_id" class="text-indigo-600 hover:text-indigo-800" :href="route('operacion.comprobantes.show', m.referencia_id)">Comprobante</Link><Link v-else-if="m.referencia_tipo === 'recibo' && m.referencia_id" class="text-indigo-600 hover:text-indigo-800" :href="route('cobranzas.recibos.show', m.referencia_id)">Recibo</Link><span v-else>-</span></td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
