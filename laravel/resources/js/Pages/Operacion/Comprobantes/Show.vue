<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatFecha } from '@/Utils/format.js';

const props = defineProps({
    comprobante: Object,
    auditLogs: Array,
    cuentas: Array,
    creditSummary: Object,
    motivoOptions: Array,
});

const notaCreditoForm = useForm({ motivo_tipo: 'devolucion_parcial', motivo_detalle: '', importe: '' });
const notaDebitoForm = useForm({ motivo: '', importe: '' });
const autorizarForm = useForm({ tipo: '' });
const cuentaForm = useForm({ facturar_cuenta_id: '', motivo: '' });
const editandoCliente = ref(false);

const submitCuenta = () => {
    cuentaForm.put(route('operacion.comprobantes.update', props.comprobante.id), { preserveScroll: true, onSuccess: () => { editandoCliente.value = false; } });
};

const imprimir = () => window.open(route('operacion.comprobantes.print', props.comprobante.id), '_blank');
const anular = () => {
    if (!window.confirm('Se anulara el comprobante y se revertiran sus movimientos. Continuar?')) return;
    router.post(route('operacion.comprobantes.anular', props.comprobante.id), {}, { preserveScroll: true });
};

const generarNotaCredito = () => {
    notaCreditoForm.post(route('operacion.comprobantes.nota-credito', props.comprobante.id), { preserveScroll: true });
};

const generarNotaDebito = () => {
    notaDebitoForm.post(route('operacion.comprobantes.nota-debito', props.comprobante.id), { preserveScroll: true });
};

const autorizarArca = () => {
    const tipo = props.comprobante.comprobante_origen?.arca_tipo_cbte || 'FC';
    autorizarForm.tipo = tipo;
    autorizarForm.post(route('operacion.comprobantes.autorizar-arca', props.comprobante.id), { preserveScroll: true });
};

const tipoLabel = (tipo) => {
    if (tipo === 'guia_envio') return 'Guia no fiscal';
    if (tipo === 'factura_interna') return 'Factura';
    if (tipo === 'nota_credito_interna') return 'Nota de credito';
    if (tipo === 'nota_debito_interna') return 'Nota de debito';
    if (tipo === 'nota_debito_manual') return 'Nota de debito';
    if (tipo === 'nota_credito_manual') return 'Nota de credito manual';
    return tipo || '-';
};

const cotizacion = props.comprobante?.detalle_facturacion?.calculo?.cotizacion || props.comprobante?.detalle_facturacion?.cotizacion || null;
</script>

<template>
    <AppLayout :title="`Comprobante #${comprobante.id}`">
        <Head :title="`Comprobante #${comprobante.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Comprobante #{{ comprobante.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">{{ tipoLabel(comprobante.tipo) }} · {{ comprobante.estado }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2 justify-end">
                    <SecondaryButton @click="imprimir">Imprimir / PDF</SecondaryButton>
                    <PrimaryButton v-if="['nota_credito_interna', 'nota_debito_interna'].includes(comprobante.tipo) && !comprobante.arca_cae && comprobante.comprobante_origen?.arca_tipo_cbte" :disabled="autorizarForm.processing" @click="autorizarArca">Autorizar ARCA</PrimaryButton>
                    <PrimaryButton v-if="comprobante.estado === 'emitida' && !comprobante.arca_cae" @click="anular">Anular</PrimaryButton>
                    <Link :href="route('operacion.comprobantes.index')"><SecondaryButton>Volver</SecondaryButton></Link>
                </div>
            </div>
        </template>

        <div class="max-w-5xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="text-xs uppercase tracking-wider text-gray-500">Empresa</div>
                    <div class="mt-1 text-sm text-gray-900">{{ comprobante.empresa?.razon_social || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider text-gray-500">Deposito</div>
                    <div class="mt-1 text-sm text-gray-900">{{ comprobante.deposito?.nombre || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider text-gray-500">Facturar a</div>
                    <div class="mt-1 text-sm text-gray-900">{{ comprobante.facturar_cuenta?.tercero?.razon_social || '-' }}
                        <button v-if="!editandoCliente && comprobante.arca_resultado === 'importado'" class="ml-2 text-xs text-indigo-600 hover:text-indigo-800" @click="editandoCliente = true; cuentaForm.facturar_cuenta_id = String(comprobante.facturar_cuenta_id || '')">[Cambiar]</button>
                    </div>
                    <div v-if="editandoCliente" class="mt-2 space-y-2">
                        <select v-model="cuentaForm.facturar_cuenta_id" class="block w-full border-gray-300 rounded-md shadow-sm text-xs">
                            <option value="">Seleccionar cliente...</option>
                            <option v-for="c in cuentas" :key="c.id" :value="String(c.id)">{{ c.tercero?.razon_social || '?' }} ({{ c.tercero?.cuit || '?' }})</option>
                        </select>
                        <div class="flex gap-2">
                            <PrimaryButton class="!text-xs" :disabled="cuentaForm.processing || !cuentaForm.facturar_cuenta_id" @click="submitCuenta">Guardar</PrimaryButton>
                            <SecondaryButton class="!text-xs" @click="editandoCliente = false">Cancelar</SecondaryButton>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider text-gray-500">Entrega</div>
                    <div class="mt-1 text-sm text-gray-900">{{ comprobante.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider text-gray-500">Fecha</div>
                    <div class="mt-1 text-sm text-gray-900">{{ formatFecha(comprobante.fecha_emision) }}</div>
                </div>
                <div v-if="comprobante.arca_cae">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Comprobante ARCA</div>
                    <div class="mt-1 text-sm font-mono text-gray-900">{{ comprobante.arca_tipo_cbte || comprobante.tipo }} · {{ String(parseInt(comprobante.arca_punto_venta || 0)) + '-' + String(comprobante.arca_numero || 0).padStart(8,'0') }} · CAE: {{ comprobante.arca_cae }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider text-gray-500">Total</div>
                    <div class="mt-1 text-sm text-gray-900">{{ comprobante.moneda }} {{ comprobante.total }}</div>
                </div>
                <div v-if="cotizacion && comprobante.moneda !== 'ARS'">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Cotizacion usada</div>
                    <div class="mt-1 text-sm text-gray-900">1 {{ comprobante.moneda }} = {{ cotizacion.tasa_ars }} ARS</div>
                </div>
                <div v-if="comprobante.iva_total > 0 || comprobante.subtotal > 0" class="border-t border-gray-200 pt-3 mt-1 col-span-2">
                    <div class="text-xs uppercase tracking-wider text-gray-500 mb-1">Desglose</div>
                    <div class="text-sm text-gray-900">Subtotal: {{ comprobante.moneda }} {{ comprobante.subtotal }}</div>
                    <div class="text-sm text-gray-900">IVA: {{ comprobante.moneda }} {{ comprobante.iva_total }}</div>
                    <div v-if="comprobante.tributos_total > 0" class="text-sm text-gray-900">Tributos: {{ comprobante.moneda }} {{ comprobante.tributos_total }}</div>
                    <div class="text-sm font-semibold text-gray-900">Total: {{ comprobante.moneda }} {{ comprobante.total }}</div>
                </div>
                <div v-if="comprobante.comprobante_origen">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Comprobante origen</div>
                    <div class="mt-1 text-sm text-gray-900">
                        <Link :href="route('operacion.comprobantes.show', comprobante.comprobante_origen.id)" class="text-indigo-600 hover:text-indigo-800">
                            #{{ comprobante.comprobante_origen.id }}
                        </Link>
                    </div>
                </div>
                <div v-if="comprobante.motivo">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Motivo</div>
                    <div class="mt-1 text-sm text-gray-900">{{ comprobante.motivo }}</div>
                </div>
            </div>

            <div v-if="comprobante.tipo === 'factura_interna' && comprobante.arca_cae" class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nota de credito</h3>
                <p class="mt-1 text-sm text-gray-600">Genera una nota de credito vinculada a esta factura fiscal. Queda auditada.</p>
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Total factura</div>
                        <div class="mt-1 text-gray-900">{{ comprobante.moneda }} {{ creditSummary?.total_origen }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">NC emitidas</div>
                        <div class="mt-1 text-gray-900">{{ comprobante.moneda }} {{ creditSummary?.credito_emitido }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-gray-500">Saldo acreditable</div>
                        <div class="mt-1 text-gray-900">{{ comprobante.moneda }} {{ creditSummary?.saldo_acreditable }}</div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <select v-model="notaCreditoForm.motivo_tipo" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        <option v-for="o in motivoOptions || []" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                    <input v-model="notaCreditoForm.motivo_detalle" type="text" class="block w-full border-gray-300 rounded-md shadow-sm text-sm sm:col-span-2" placeholder="Detalle adicional (opcional)" />
                    <input v-model="notaCreditoForm.importe" type="number" min="0.01" step="0.01" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Importe" />
                </div>
                <div class="mt-4 flex flex-col sm:flex-row gap-3">
                    <PrimaryButton :disabled="notaCreditoForm.processing || !notaCreditoForm.motivo_tipo || !notaCreditoForm.importe || Number(creditSummary?.saldo_acreditable || 0) <= 0" @click="generarNotaCredito">Generar NC</PrimaryButton>
                </div>
                <div v-if="notaCreditoForm.errors.motivo_tipo" class="mt-2 text-sm text-red-600">{{ notaCreditoForm.errors.motivo_tipo }}</div>
                <div v-if="notaCreditoForm.errors.motivo_detalle" class="mt-2 text-sm text-red-600">{{ notaCreditoForm.errors.motivo_detalle }}</div>
                <div v-if="notaCreditoForm.errors.importe" class="mt-2 text-sm text-red-600">{{ notaCreditoForm.errors.importe }}</div>
            </div>

            <div v-if="comprobante.tipo === 'factura_interna' && comprobante.arca_cae" class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nota de debito fiscal</h3>
                <p class="mt-1 text-sm text-gray-600">Genera una nota de debito fiscal vinculada a esta factura.</p>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <input v-model="notaDebitoForm.motivo" type="text" class="block w-full border-gray-300 rounded-md shadow-sm text-sm sm:col-span-2" placeholder="Motivo" />
                    <input v-model="notaDebitoForm.importe" type="number" min="0.01" step="0.01" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Importe" />
                </div>
                <div class="mt-4 flex flex-col sm:flex-row gap-3">
                    <PrimaryButton :disabled="notaDebitoForm.processing || !notaDebitoForm.motivo || !notaDebitoForm.importe" @click="generarNotaDebito">Generar ND</PrimaryButton>
                </div>
                <div v-if="notaDebitoForm.errors.motivo" class="mt-2 text-sm text-red-600">{{ notaDebitoForm.errors.motivo }}</div>
                <div v-if="notaDebitoForm.errors.importe" class="mt-2 text-sm text-red-600">{{ notaDebitoForm.errors.importe }}</div>
            </div>

            <div v-if="(comprobante.notas_credito || []).length" class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Notas de credito relacionadas</h3>
                <div class="mt-4 space-y-2">
                    <div v-for="nc in comprobante.notas_credito" :key="nc.id" class="flex items-center justify-between rounded border border-gray-200 px-4 py-3">
                        <div class="text-sm text-gray-700">
                            <Link :href="route('operacion.comprobantes.show', nc.id)" class="text-indigo-600 hover:text-indigo-800">#{{ nc.id }}</Link>
                            · {{ tipoLabel(nc.tipo) }} · {{ nc.estado }} · {{ nc.moneda }} {{ nc.total }}
                        </div>
                        <div class="text-xs text-gray-500 text-right">
                            <div>{{ nc.arca_cae ? 'CAE ' + nc.arca_cae : 'Pendiente ARCA' }}</div>
                            <div>{{ String(nc.created_at || '').replace('T', ' ').slice(0, 19) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Detalle de pedidos</h3>
                <div class="mt-4 space-y-3 sm:hidden">
                    <div v-for="p in comprobante.pedidos || []" :key="p.id" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Pedido #{{ p.id }}</div>
                                <div class="text-xs text-gray-500">Bultos {{ p.bultos }} · Palets {{ p.palets }}</div>
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ p.valor_declarado }}</div>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Remitente</div>
                                <div class="font-medium text-gray-900">{{ p.remitente?.razon_social || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Destinatario</div>
                                <div class="font-medium text-gray-900">{{ p.destinatario?.razon_social || '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4 hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pedido</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remitente</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatario</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Palets</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in comprobante.pedidos || []" :key="p.id">
                                <td class="px-4 py-2 text-sm text-gray-900">#{{ p.id }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ p.remitente?.razon_social || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ p.destinatario?.razon_social || '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ p.bultos }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ p.palets }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ p.valor_declarado }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="(auditLogs || []).length" class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Auditoria</h3>
                <div class="mt-4 space-y-3">
                    <div v-for="log in auditLogs" :key="log.id" class="rounded border border-gray-200 px-4 py-3">
                        <div class="text-sm font-medium text-gray-900">{{ log.action }}</div>
                        <div class="text-xs text-gray-500">{{ log.user?.name || 'Sistema' }} · {{ String(log.created_at || '').replace('T', ' ').slice(0, 19) }}</div>
                        <pre class="mt-2 text-xs text-gray-700 whitespace-pre-wrap">{{ JSON.stringify(log.context || {}, null, 2) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
