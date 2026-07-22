<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    empresa: Object,
    cuentas: Array,
    cotizaciones: Object,
});

const page = usePage();

const agregarFila = () => {
    pedidos.value.push({
        key: nextKey++,
        remitente_cuit: '',
        remitente_razon_social: '',
        destinatario_cuit: '',
        destinatario_razon_social: '',
        bultos: 0,
        palets: 0,
        valor_declarado: 0,
        cr_importe: null,
        paga: 'destino',
        remito_numero: '',
        observacion: '',
    });
};

let nextKey = 1;
const pedidos = ref([]);

agregarFila();

const eliminarFila = (key) => {
    if (pedidos.value.length <= 1) return;
    pedidos.value = pedidos.value.filter(p => p.key !== key);
};

const formatNum = (v) => {
    const n = Number(v) || 0;
    return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const subtotales = computed(() => {
    let b = 0, p = 0, vd = 0, cr = 0;
    for (const row of pedidos.value) {
        b += Number(row.bultos) || 0;
        p += Number(row.palets) || 0;
        vd += Number(row.valor_declarado) || 0;
        cr += Number(row.cr_importe) || 0;
    }
    return { bultos: b, palets: p, valor_declarado: vd, cr_importe: cr };
});

const searchFacturar = ref('');
const showFacturarDropdown = ref(false);
const selectedFacturar = ref('');

const searchEntrega = ref('');
const showEntregaDropdown = ref(false);
const selectedEntrega = ref('');

const filteredFacturar = computed(() => {
    const q = searchFacturar.value.toLowerCase().trim();
    if (!q) return props.cuentas;
    return props.cuentas.filter(c => {
        const nom = (c.nombre_cuenta || c.tercero?.razon_social || '').toLowerCase();
        const cui = (c.tercero?.cuit || '').toLowerCase();
        return nom.includes(q) || cui.includes(q);
    });
});

const filteredEntrega = computed(() => {
    const q = searchEntrega.value.toLowerCase().trim();
    if (!q) return props.cuentas;
    return props.cuentas.filter(c => {
        const nom = (c.nombre_cuenta || c.tercero?.razon_social || '').toLowerCase();
        const cui = (c.tercero?.cuit || '').toLowerCase();
        return nom.includes(q) || cui.includes(q);
    });
});

const selectFacturar = (cuenta) => {
    form.facturar_cuenta_id = cuenta.id;
    selectedFacturar.value = cuenta.nombre_cuenta || cuenta.tercero?.razon_social || '';
    searchFacturar.value = selectedFacturar.value;
    showFacturarDropdown.value = false;
};

const selectEntrega = (cuenta) => {
    form.entrega_cuenta_id = cuenta.id;
    selectedEntrega.value = cuenta.nombre_cuenta || cuenta.tercero?.razon_social || '';
    searchEntrega.value = selectedEntrega.value;
    showEntregaDropdown.value = false;
};

const form = useForm({
    facturar_cuenta_id: '',
    entrega_cuenta_id: '',
    fecha_emision: new Date().toISOString().slice(0, 10),
    moneda: 'ARS',
    pedidos: [],
});

const submit = () => {
    form.pedidos = pedidos.value.map(p => ({
        remitente_cuit: p.remitente_cuit,
        remitente_razon_social: p.remitente_razon_social,
        destinatario_cuit: p.destinatario_cuit,
        destinatario_razon_social: p.destinatario_razon_social,
        bultos: Number(p.bultos) || 0,
        palets: Number(p.palets) || 0,
        valor_declarado: Number(p.valor_declarado) || 0,
        cr_importe: p.cr_importe !== null && p.cr_importe !== '' ? Number(p.cr_importe) : null,
        paga: p.paga,
        remito_numero: p.remito_numero,
        observacion: p.observacion,
    }));

    form.post(route('facturacion.carga-directa.store'));
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

        <div class="max-w-6xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="relative">
                            <InputLabel value="Facturar a (cliente)" />
                            <TextInput
                                v-model="searchFacturar"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Buscar por nombre o CUIT..."
                                @focus="showFacturarDropdown = true"
                                @blur="setTimeout(() => showFacturarDropdown = false, 200)"
                            />
                            <input type="hidden" v-model="form.facturar_cuenta_id" />
                            <InputError :message="form.errors.facturar_cuenta_id" />
                            <ul
                                v-if="showFacturarDropdown && filteredFacturar.length"
                                class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto text-sm"
                            >
                                <li
                                    v-for="c in filteredFacturar"
                                    :key="c.id"
                                    class="px-3 py-2 hover:bg-indigo-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                                    @mousedown.prevent="selectFacturar(c)"
                                >
                                    <div class="font-medium text-gray-900">{{ c.nombre_cuenta || c.tercero?.razon_social }}</div>
                                    <div class="text-xs text-gray-500">{{ c.tercero?.cuit || 'Sin CUIT' }}</div>
                                </li>
                            </ul>
                            <div v-if="form.facturar_cuenta_id && !showFacturarDropdown" class="mt-1 text-xs text-green-700">
                                Seleccionado: {{ selectedFacturar }}
                            </div>
                        </div>

                        <div class="relative">
                            <InputLabel value="Entrega (destinatario)" />
                            <TextInput
                                v-model="searchEntrega"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Buscar por nombre o CUIT..."
                                @focus="showEntregaDropdown = true"
                                @blur="setTimeout(() => showEntregaDropdown = false, 200)"
                            />
                            <input type="hidden" v-model="form.entrega_cuenta_id" />
                            <InputError :message="form.errors.entrega_cuenta_id" />
                            <ul
                                v-if="showEntregaDropdown && filteredEntrega.length"
                                class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto text-sm"
                            >
                                <li
                                    v-for="c in filteredEntrega"
                                    :key="c.id"
                                    class="px-3 py-2 hover:bg-indigo-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                                    @mousedown.prevent="selectEntrega(c)"
                                >
                                    <div class="font-medium text-gray-900">{{ c.nombre_cuenta || c.tercero?.razon_social }}</div>
                                    <div class="text-xs text-gray-500">{{ c.tercero?.cuit || 'Sin CUIT' }}</div>
                                </li>
                            </ul>
                            <div v-if="form.entrega_cuenta_id && !showEntregaDropdown" class="mt-1 text-xs text-green-700">
                                Seleccionado: {{ selectedEntrega }}
                            </div>
                        </div>

                        <div>
                            <InputLabel for="fecha_emision" value="Fecha de emision" />
                            <TextInput id="fecha_emision" v-model="form.fecha_emision" type="date" class="mt-1 block w-full" />
                            <InputError :message="form.errors.fecha_emision" />
                        </div>
                        <div>
                            <InputLabel for="moneda" value="Moneda" />
                            <select
                                id="moneda"
                                v-model="form.moneda"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option value="ARS">ARS</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="BRL">BRL</option>
                            </select>
                            <InputError :message="form.errors.moneda" />
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex items-center justify-between gap-4 mb-3">
                            <h3 class="text-base font-semibold text-gray-900">Pedidos</h3>
                            <button
                                type="button"
                                class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                                @click="agregarFila"
                            >
                                + Agregar fila
                            </button>
                        </div>

                        <div class="space-y-4 lg:hidden">
                            <div
                                v-for="(p, i) in pedidos"
                                :key="p.key"
                                class="rounded-lg border border-gray-200 p-4 space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold text-gray-900">Pedido #{{ i + 1 }}</span>
                                    <button
                                        v-if="pedidos.length > 1"
                                        type="button"
                                        class="text-xs text-red-600 hover:text-red-800"
                                        @click="eliminarFila(p.key)"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                                <div>
                                    <InputLabel :for="'rmt-cuit-' + p.key" value="Remitente CUIT" />
                                    <TextInput :id="'rmt-cuit-' + p.key" v-model="p.remitente_cuit" type="text" class="mt-1 block w-full" placeholder="CUIT" />
                                </div>
                                <div>
                                    <InputLabel :for="'rmt-rs-' + p.key" value="Remitente Razon social" />
                                    <TextInput :id="'rmt-rs-' + p.key" v-model="p.remitente_razon_social" type="text" class="mt-1 block w-full" placeholder="Razon social" />
                                </div>
                                <div>
                                    <InputLabel :for="'dest-cuit-' + p.key" value="Destinatario CUIT" />
                                    <TextInput :id="'dest-cuit-' + p.key" v-model="p.destinatario_cuit" type="text" class="mt-1 block w-full" placeholder="CUIT" />
                                </div>
                                <div>
                                    <InputLabel :for="'dest-rs-' + p.key" value="Destinatario Razon social" />
                                    <TextInput :id="'dest-rs-' + p.key" v-model="p.destinatario_razon_social" type="text" class="mt-1 block w-full" placeholder="Razon social" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel :for="'bultos-' + p.key" value="Bultos" />
                                        <TextInput :id="'bultos-' + p.key" v-model="p.bultos" type="number" min="0" class="mt-1 block w-full" />
                                    </div>
                                    <div>
                                        <InputLabel :for="'palets-' + p.key" value="Palets" />
                                        <TextInput :id="'palets-' + p.key" v-model="p.palets" type="number" min="0" class="mt-1 block w-full" />
                                    </div>
                                    <div>
                                        <InputLabel :for="'vd-' + p.key" value="Valor declarado" />
                                        <TextInput :id="'vd-' + p.key" v-model="p.valor_declarado" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                                    </div>
                                    <div>
                                        <InputLabel :for="'cr-' + p.key" value="CR importe" />
                                        <TextInput :id="'cr-' + p.key" v-model="p.cr_importe" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="(opcional)" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel :for="'paga-' + p.key" value="Paga" />
                                        <select :id="'paga-' + p.key" v-model="p.paga" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            <option value="origen">Origen</option>
                                            <option value="destino">Destino</option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel :for="'remito-' + p.key" value="Remito" />
                                        <TextInput :id="'remito-' + p.key" v-model="p.remito_numero" type="text" class="mt-1 block w-full" />
                                    </div>
                                </div>
                                <div>
                                    <InputLabel :for="'obs-' + p.key" value="Observacion" />
                                    <textarea
                                        :id="'obs-' + p.key"
                                        v-model="p.observacion"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        rows="1"
                                        placeholder="(opcional)"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 hidden lg:block overflow-x-auto">
                            <table class="min-w-full w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remitente</th>
                                        <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatario</th>
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Palets</th>
                                        <th class="px-2 py-1.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Val. declarado</th>
                                        <th class="px-2 py-1.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">CR</th>
                                        <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Paga</th>
                                        <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remito</th>
                                        <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th>
                                        <th class="px-2 py-1.5"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(p, i) in pedidos" :key="p.key">
                                        <td class="px-2 py-1 text-xs text-gray-500 whitespace-nowrap">{{ i + 1 }}</td>
                                        <td class="px-2 py-1 whitespace-nowrap">
                                            <input v-model="p.remitente_cuit" type="text" class="w-28 border-gray-300 rounded text-xs py-0.5 px-1" placeholder="CUIT" />
                                            <input v-model="p.remitente_razon_social" type="text" class="w-32 border-gray-300 rounded text-xs py-0.5 px-1 mt-0.5" placeholder="Razon social" />
                                        </td>
                                        <td class="px-2 py-1 whitespace-nowrap">
                                            <input v-model="p.destinatario_cuit" type="text" class="w-28 border-gray-300 rounded text-xs py-0.5 px-1" placeholder="CUIT" />
                                            <input v-model="p.destinatario_razon_social" type="text" class="w-32 border-gray-300 rounded text-xs py-0.5 px-1 mt-0.5" placeholder="Razon social" />
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <input v-model="p.bultos" type="number" min="0" class="w-14 border-gray-300 rounded text-xs py-0.5 px-1 text-center" />
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <input v-model="p.palets" type="number" min="0" class="w-14 border-gray-300 rounded text-xs py-0.5 px-1 text-center" />
                                        </td>
                                        <td class="px-2 py-1 text-right">
                                            <input v-model="p.valor_declarado" type="number" min="0" step="0.01" class="w-20 border-gray-300 rounded text-xs py-0.5 px-1 text-right" />
                                        </td>
                                        <td class="px-2 py-1 text-right">
                                            <input v-model="p.cr_importe" type="number" min="0" step="0.01" class="w-16 border-gray-300 rounded text-xs py-0.5 px-1 text-right" placeholder="-" />
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <select v-model="p.paga" class="w-16 border-gray-300 rounded text-xs py-0.5 px-0.5">
                                                <option value="origen">Org</option>
                                                <option value="destino">Dst</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-1">
                                            <input v-model="p.remito_numero" type="text" class="w-16 border-gray-300 rounded text-xs py-0.5 px-1" />
                                        </td>
                                        <td class="px-2 py-1">
                                            <input v-model="p.observacion" type="text" class="w-20 border-gray-300 rounded text-xs py-0.5 px-1" />
                                        </td>
                                        <td class="px-2 py-1 text-center">
                                            <button
                                                v-if="pedidos.length > 1"
                                                type="button"
                                                class="text-xs text-red-600 hover:text-red-800"
                                                @click="eliminarFila(p.key)"
                                            >
                                                X
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 font-semibold">
                                    <tr>
                                        <td colspan="3" class="px-2 py-1.5 text-xs text-gray-700 text-right">Totales:</td>
                                        <td class="px-2 py-1.5 text-xs text-gray-900 text-center">{{ formatNum(subtotales.bultos) }}</td>
                                        <td class="px-2 py-1.5 text-xs text-gray-900 text-center">{{ formatNum(subtotales.palets) }}</td>
                                        <td class="px-2 py-1.5 text-xs text-gray-900 text-right">{{ formatNum(subtotales.valor_declarado) }}</td>
                                        <td class="px-2 py-1.5 text-xs text-gray-900 text-right">{{ formatNum(subtotales.cr_importe) }}</td>
                                        <td colspan="4"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-if="pedidos.length === 0" class="py-6 text-center text-sm text-gray-500">
                            Agrega al menos un pedido para emitir la factura.
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 flex items-center justify-end gap-3">
                        <Link :href="route('facturacion.manifiestos.index')">
                            <SecondaryButton type="button">Cancelar</SecondaryButton>
                        </Link>
                        <PrimaryButton :disabled="form.processing || !form.facturar_cuenta_id || !form.entrega_cuenta_id || pedidos.length === 0">
                            Emitir factura
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
