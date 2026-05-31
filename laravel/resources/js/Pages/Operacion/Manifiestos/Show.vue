<script setup>
import { computed } from 'vue';
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
});

const page = usePage();
const roles = computed(() => page.props.tt?.roles || []);
const canFacturar = computed(() => roles.value.includes('admin') || roles.value.includes('facturacion'));
const flashSuccess = computed(() => page.props.flash?.success);

const pedidoForm = useForm({
    remitente: { cuit: '', razon_social: '' },
    destinatario: { cuit: '', razon_social: '' },
    paga: 'destino',
    remito_numero: '',
    bultos: 0,
    palets: 0,
    valor_declarado: 0,
    es_devolucion: false,
    cr_importe: '',
});

const submitPedido = () => {
    pedidoForm.post(route('operacion.manifiestos.pedidos.store', props.manifiesto.id), {
        preserveScroll: true,
        onSuccess: () => {
            pedidoForm.reset();
            pedidoForm.paga = 'destino';
            pedidoForm.bultos = 0;
            pedidoForm.palets = 0;
            pedidoForm.valor_declarado = 0;
        },
    });
};

const totalPedidos = computed(() => (props.manifiesto.pedidos || []).length);

const statsFacturacion = computed(() => {
    const pedidos = props.manifiesto.pedidos || [];
    const pendientes = pedidos.filter((p) => !(p.comprobantes && p.comprobantes.length));
    const sinEntrega = pendientes.filter((p) => !p.destinatario_cuenta_id);
    return {
        total: pedidos.length,
        pendientes: pendientes.length,
        sinEntrega: sinEntrega.length,
        emitidos: (props.comprobantes || []).length,
    };
});

const pedidosPendientes = computed(() => {
    return (props.manifiesto.pedidos || []).filter((p) => !(p.comprobantes && p.comprobantes.length));
});

const gruposFacturacion = computed(() => {
    const grouped = new Map();
    for (const p of pedidosPendientes.value) {
        const entregaId = p.destinatario_cuenta_id;
        if (!entregaId) continue;
        if (!grouped.has(entregaId)) grouped.set(entregaId, []);
        grouped.get(entregaId).push(p);
    }

    const out = [];
    for (const [entregaId, pedidos] of grouped.entries()) {
        const total = pedidos.reduce((acc, x) => acc + Number(x.valor_declarado || 0), 0).toFixed(2);
        const cuentas = new Map();
        for (const p of pedidos) {
            if (p.remitente_cuenta) cuentas.set(p.remitente_cuenta.id, { id: p.remitente_cuenta.id, label: `${p.remitente_cuenta.tercero?.razon_social || 'Remitente'} (Origen)`, cuit: p.remitente_cuenta.tercero?.cuit || '' });
            if (p.destinatario_cuenta) cuentas.set(p.destinatario_cuenta.id, { id: p.destinatario_cuenta.id, label: `${p.destinatario_cuenta.tercero?.razon_social || 'Destinatario'} (Destino)`, cuit: p.destinatario_cuenta.tercero?.cuit || '' });
        }

        // Default suggestion: if all pedidos have same paga, suggest that side
        const pagas = Array.from(new Set(pedidos.map((p) => p.paga)));
        let suggested = '';
        if (pagas.length === 1) {
            if (pagas[0] === 'origen' && pedidos[0].remitente_cuenta) suggested = String(pedidos[0].remitente_cuenta.id);
            if (pagas[0] === 'destino' && pedidos[0].destinatario_cuenta) suggested = String(pedidos[0].destinatario_cuenta.id);
        }

        out.push({ entregaId, pedidos, total, cuentas: Array.from(cuentas.values()), suggested });
    }

    return out.sort((a, b) => a.entregaId - b.entregaId);
});

const facturarPorEntrega = useForm({ confirm: true, facturar_por_entrega: {} });

const backfillForm = useForm({ confirm: true });
const completarCuentas = () => {
    backfillForm.post(route('operacion.manifiestos.backfill-cuentas', props.manifiesto.id), { preserveScroll: true });
};

const initFacturarMap = () => {
    const map = {};
    for (const g of gruposFacturacion.value) {
        map[g.entregaId] = g.suggested || '';
    }
    facturarPorEntrega.facturar_por_entrega = map;
};

initFacturarMap();

const facturarSeleccionado = () => {
    facturarPorEntrega.post(route('operacion.manifiestos.facturar', props.manifiesto.id), { preserveScroll: true });
};

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};
</script>

<template>
    <AppLayout :title="`Operacion / Manifiesto #${manifiesto.id}`">
        <Head :title="`Operacion / Manifiesto #${manifiesto.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Manifiesto #{{ manifiesto.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ formatFecha(manifiesto.fecha) }} · {{ manifiesto.empresa?.razon_social || '-' }} · {{ manifiesto.deposito?.nombre || '-' }}
                    </div>
                </div>
                <Link :href="route('operacion.manifiestos.index')">
                    <SecondaryButton>Volver</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded">
                {{ flashSuccess }}
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
                        <p class="mt-1 text-sm text-gray-600">{{ totalPedidos }} cargados</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <PrimaryButton v-if="canFacturar" :disabled="facturarPorEntrega.processing || !gruposFacturacion.length" @click.prevent="facturarSeleccionado">Emitir facturas</PrimaryButton>
                    </div>
                </div>

                <div v-if="canFacturar" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <div class="text-sm font-medium text-gray-900">Facturacion (v1)</div>
                    <p class="mt-1 text-xs text-gray-600">Se crea 1 comprobante por cuenta de entrega. Elegi la cuenta a facturar por cada grupo.</p>

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

                    <div class="mt-4 space-y-3">
                        <div v-for="g in gruposFacturacion" :key="g.entregaId" class="rounded-lg bg-white border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-4 flex-wrap">
                                <div>
                                    <div class="text-xs text-gray-500">Cuenta de entrega</div>
                                    <div class="text-sm font-medium text-gray-900">#{{ g.entregaId }}</div>
                                    <div class="text-xs text-gray-600">Pedidos: {{ g.pedidos.length }} · Total (v1): ARS {{ g.total }}</div>
                                </div>
                                <div class="min-w-[260px]">
                                    <div class="text-xs text-gray-500">Facturar a</div>
                                    <select
                                        v-model="facturarPorEntrega.facturar_por_entrega[g.entregaId]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    >
                                        <option value="">(seleccionar)</option>
                                        <option v-for="c in g.cuentas" :key="c.id" :value="String(c.id)">
                                            {{ c.label }}{{ c.cuit ? ' · CUIT ' + c.cuit : '' }}
                                        </option>
                                    </select>
                                </div>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facturar</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="c in comprobantes" :key="c.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">#{{ c.id }}</td>
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
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <form class="mt-6 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submitPedido">
                    <div class="sm:col-span-2">
                        <div class="text-sm font-medium text-gray-900">Remitente</div>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="CUIT" />
                                <TextInput v-model="pedidoForm.remitente.cuit" type="text" class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="pedidoForm.errors['remitente.cuit']" />
                            </div>
                            <div>
                                <InputLabel value="Razon social" />
                                <TextInput v-model="pedidoForm.remitente.razon_social" type="text" class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="pedidoForm.errors['remitente.razon_social']" />
                            </div>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <div class="text-sm font-medium text-gray-900">Destinatario</div>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="CUIT" />
                                <TextInput v-model="pedidoForm.destinatario.cuit" type="text" class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="pedidoForm.errors['destinatario.cuit']" />
                            </div>
                            <div>
                                <InputLabel value="Razon social" />
                                <TextInput v-model="pedidoForm.destinatario.razon_social" type="text" class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="pedidoForm.errors['destinatario.razon_social']" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Paga" />
                        <select v-model="pedidoForm.paga" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="origen">Origen</option>
                            <option value="destino">Destino</option>
                        </select>
                        <InputError class="mt-2" :message="pedidoForm.errors.paga" />
                    </div>

                    <div>
                        <InputLabel value="Remito" />
                        <TextInput v-model="pedidoForm.remito_numero" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="pedidoForm.errors.remito_numero" />
                    </div>

                    <div>
                        <InputLabel value="Bultos" />
                        <TextInput v-model="pedidoForm.bultos" type="number" min="0" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="pedidoForm.errors.bultos" />
                    </div>

                    <div>
                        <InputLabel value="Palets" />
                        <TextInput v-model="pedidoForm.palets" type="number" min="0" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="pedidoForm.errors.palets" />
                    </div>

                    <div>
                        <InputLabel value="Valor declarado" />
                        <TextInput v-model="pedidoForm.valor_declarado" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="pedidoForm.errors.valor_declarado" />
                    </div>

                    <div>
                        <InputLabel value="CR" />
                        <TextInput v-model="pedidoForm.cr_importe" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                        <InputError class="mt-2" :message="pedidoForm.errors.cr_importe" />
                    </div>

                    <div class="sm:col-span-2 flex items-center gap-2">
                        <Checkbox v-model:checked="pedidoForm.es_devolucion" />
                        <span class="text-sm text-gray-700">Es devolucion</span>
                    </div>

                    <div class="sm:col-span-2 flex items-center justify-end">
                        <PrimaryButton :disabled="pedidoForm.processing">Agregar pedido</PrimaryButton>
                    </div>
                </form>

                <div class="mt-8 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remitente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatario</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paga</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Palets</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CR</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in (manifiesto.pedidos || [])" :key="p.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ p.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ p.remitente?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ p.remitente?.cuit || '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ p.destinatario?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ p.destinatario?.cuit || '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.paga }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.bultos }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.palets }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.valor_declarado }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.cr_importe || '-' }}</td>
                            </tr>

                            <tr v-if="!(manifiesto.pedidos || []).length">
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">Todavia no hay pedidos cargados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
