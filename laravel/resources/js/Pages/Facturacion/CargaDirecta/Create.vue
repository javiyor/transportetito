<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    empresa: Object,
    cuentas: Array,
    tarifaDefaults: Object,
});

const defaultPct = Number(props.tarifaDefaults?.tarifa_valor_declarado_pct) || 0.03;
const ivaPct = Number(props.tarifaDefaults?.iva_pct) || 0.21;

const form = useForm({
    origen_cuenta_id: '',
    destino_cuenta_id: '',
    facturar_a_destino: true,
    fecha_emision: new Date().toISOString().slice(0, 10),
    observacion: '',
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

const parseNum = (v) => {
    const cleaned = String(v).replace(/\./g, '').replace(',', '.');
    return Number(cleaned) || 0;
};

const recalcularImporte = (it) => {
    const vd = Number(it.valor_declarado) || 0;
    it.importe = Math.round(vd * defaultPct * 100) / 100;
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
        seguro: 0,
        cr: 0,
        remito: '',
    });
};

agregarItem();

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
                            <InputLabel value="Origen (Remitente)" />
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
                            <InputLabel value="Destino (Destinatario / Entrega)" />
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
                                    </div>
                                    <div>
                                        <InputLabel :for="'importe-' + it.key" value="Importe" />
                                        <TextInput :id="'importe-' + it.key" v-model="it.importe" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel :for="'seguro-' + it.key" value="Seguro" />
                                        <TextInput :id="'seguro-' + it.key" v-model="it.seguro" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                                    </div>
                                    <div>
                                        <InputLabel :for="'cr-' + it.key" value="CR" />
                                        <TextInput :id="'cr-' + it.key" v-model="it.cr" type="number" min="0" step="0.01" class="mt-1 block w-full" />
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
                                            <input :value="formatNum(it.valor_declarado)" @input="it.valor_declarado = parseNum($event.target.value); recalcularImporte(it)" type="text" class="w-20 border-gray-300 rounded text-xs py-0.5 px-1 text-right" />
                                        </td>
                                        <td class="px-2 py-1">
                                            <input :value="formatNum(it.importe)" @input="it.importe = parseNum($event.target.value)" type="text" class="w-20 border-gray-300 rounded text-xs py-0.5 px-1 text-right" />
                                        </td>
                                        <td class="px-2 py-1">
                                            <input :value="formatNum(it.seguro)" @input="it.seguro = parseNum($event.target.value)" type="text" class="w-20 border-gray-300 rounded text-xs py-0.5 px-1 text-right" />
                                        </td>
                                        <td class="px-2 py-1">
                                            <input :value="formatNum(it.cr)" @input="it.cr = parseNum($event.target.value)" type="text" class="w-20 border-gray-300 rounded text-xs py-0.5 px-1 text-right" />
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
                        <PrimaryButton :disabled="form.processing || !form.origen_cuenta_id || !form.destino_cuenta_id || items.length === 0">
                            Emitir factura
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
