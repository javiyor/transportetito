<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    asientos: Object,
    cuentasContables: Array,
    filtros: Object,
    totales: Object,
});

const page = usePage();
const flashSuccess = computed(() => page.props.tt?.flash?.success || page.props.flash?.success || null);
const flashError = computed(() => page.props.tt?.flash?.error || page.props.flash?.error || null);

const expanded = ref(new Set());

const toggle = (id) => {
    const s = new Set(expanded.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expanded.value = s;
};

const showCreate = ref(false);
const createForm = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    descripcion: '',
    moneda: 'ARS',
    lineas: [
        { cuenta_contable_id: '', debe: '', haber: '', descripcion: '' },
        { cuenta_contable_id: '', debe: '', haber: '', descripcion: '' },
    ],
});

const openCreate = () => {
    createForm.fecha = new Date().toISOString().slice(0, 10);
    createForm.descripcion = '';
    createForm.moneda = 'ARS';
    createForm.lineas = [
        { cuenta_contable_id: '', debe: '', haber: '', descripcion: '', _search: '', _showCuentas: false },
        { cuenta_contable_id: '', debe: '', haber: '', descripcion: '', _search: '', _showCuentas: false },
    ];
    createForm.clearErrors();
    showCreate.value = true;
};

const addLinea = () => {
    createForm.lineas.push({ cuenta_contable_id: '', debe: '', haber: '', descripcion: '', _search: '', _showCuentas: false });
};

const removeLinea = (idx) => {
    if (createForm.lineas.length <= 2) return;
    createForm.lineas.splice(idx, 1);
};

const totalesCreate = computed(() => {
    let debe = 0, haber = 0;
    for (const l of createForm.lineas) {
        debe += parseFloat(l.debe) || 0;
        haber += parseFloat(l.haber) || 0;
    }
    return { debe: Math.round(debe * 100) / 100, haber: Math.round(haber * 100) / 100, diff: Math.round((debe - haber) * 100) / 100, balanceado: Math.abs(debe - haber) < 0.01 && debe > 0 };
});

const cuentaLabel = (c) => `${c.codigo_completo || c.codigo} - ${c.nombre}`;

const filteredCuentas = (query) => {
    if (!query) return props.cuentasContables;
    const q = String(query).toLowerCase();
    return props.cuentasContables.filter(c =>
        (c.codigo_completo || c.codigo || '').toLowerCase().includes(q) ||
        (c.codigo || '').toLowerCase().includes(q) ||
        (c.nombre || '').toLowerCase().includes(q)
    );
};

const onCuentaSearchInput = (linea) => {
    linea._showCuentas = true;
};

const selectCuenta = (linea, cuenta) => {
    linea.cuenta_contable_id = cuenta.id;
    linea._search = cuentaLabel(cuenta);
    linea._showCuentas = false;
};

const clearCuenta = (linea) => {
    linea.cuenta_contable_id = '';
    linea._search = '';
    linea._showCuentas = false;
};

const submitCreate = () => {
    // Enviar solo líneas con cuenta seleccionada
    const payload = {
        fecha: createForm.fecha,
        descripcion: createForm.descripcion,
        moneda: createForm.moneda,
        lineas: createForm.lineas.map(l => ({
            cuenta_contable_id: l.cuenta_contable_id ? parseInt(l.cuenta_contable_id, 10) : null,
            debe: l.debe === '' ? 0 : parseFloat(l.debe) || 0,
            haber: l.haber === '' ? 0 : parseFloat(l.haber) || 0,
            descripcion: l.descripcion || null,
        })),
    };
    createForm.transform(() => payload).post(route('finanzas.libro-diario.store'), {
        preserveScroll: true,
        onSuccess: () => { showCreate.value = false; },
    });
};

const filtroCuentaQuery = ref('');

const cuentasFiltradasFiltro = computed(() => filteredCuentas(filtroCuentaQuery.value));

const applyFilters = () => {
    router.get(route('finanzas.libro-diario'), {
        fecha_desde: document.getElementById('fecha_desde')?.value || '',
        fecha_hasta: document.getElementById('fecha_hasta')?.value || '',
        cuenta_contable_id: document.getElementById('cuenta_contable_id')?.value || '',
    }, { preserveState: true, replace: true });
};

const goToPage = (page) => {
    if (page < 1 || page > props.asientos.last_page) return;
    router.get(route('finanzas.libro-diario'), {
        fecha_desde: props.filtros.fecha_desde || '',
        fecha_hasta: props.filtros.fecha_hasta || '',
        cuenta_contable_id: props.filtros.cuenta_contable_id || '',
        page,
    }, { preserveState: true, preserveScroll: true });
};

const refLabel = (tipo) => ({
    comprobante: 'Venta',
    proveedor_comprobante: 'Compra',
    recibo: 'Recibo',
    orden_pago: 'OP',
}[tipo] || tipo);

const fmtFecha = (f) => {
    if (!f) return '';
    const d = new Date(f);
    if (isNaN(d.getTime())) return f;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}-${mm}-${yyyy}`;
};

const fmtDesc = (d) => {
    if (!d) return '';
    return d.replace(/factura_interna/g, 'factura');
};
</script>

<template>
    <AppLayout title="Libro Diario">
        <Head title="Libro Diario" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Libro Diario</h2>
                <PrimaryButton @click="openCreate">Crear asiento</PrimaryButton>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div v-if="flashSuccess" class="rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-800">{{ flashSuccess }}</div>
            <div v-if="flashError" class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">{{ flashError }}</div>
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha desde</label>
                        <input id="fecha_desde" type="date" :value="filtros.fecha_desde" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fecha hasta</label>
                        <input id="fecha_hasta" type="date" :value="filtros.fecha_hasta" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Cuenta contable</label>
                        <input v-model="filtroCuentaQuery" type="text" placeholder="Buscar por código o descripción..." class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1 mb-1" />
                        <select id="cuenta_contable_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Todas</option>
                            <option v-for="c in cuentasFiltradasFiltro" :key="c.id" :value="c.id" :selected="filtros.cuenta_contable_id == c.id">{{ cuentaLabel(c) }}</option>
                        </select>
                        <div v-if="filtroCuentaQuery && !cuentasFiltradasFiltro.length" class="text-[10px] text-gray-400 mt-1">Sin resultados</div>
                    </div>
                    <div>
                        <button @click="applyFilters" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">Filtrar</button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Asientos</div>
                    <div class="text-lg font-bold text-gray-900">{{ asientos.total }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Total Debe</div>
                    <div class="text-lg font-bold text-green-700">$ {{ totales.debe.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-4">
                    <div class="text-xs text-gray-500">Total Haber</div>
                    <div class="text-lg font-bold text-red-700">$ {{ totales.haber.toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}</div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripcion</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debe</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Haber</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="asiento in asientos.data" :key="asiento.id">
                                <tr class="hover:bg-gray-50 cursor-pointer" @click="toggle(asiento.id)">
                                    <td class="px-4 py-2 text-sm whitespace-nowrap">{{ fmtFecha(asiento.fecha) }}</td>
                                    <td class="px-4 py-2 text-sm">{{ fmtDesc(asiento.descripcion) }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <span class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ refLabel(asiento.referencia_tipo) }} #{{ asiento.referencia_id }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-mono text-green-700">
                                        $ {{ asiento.lineas.reduce((s, l) => s + parseFloat(l.debe), 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-mono text-red-700">
                                        $ {{ asiento.lineas.reduce((s, l) => s + parseFloat(l.haber), 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-center text-gray-400">{{ expanded.has(asiento.id) ? '▲' : '▼' }}</td>
                                </tr>
                                <tr v-if="expanded.has(asiento.id)">
                                    <td colspan="6" class="px-8 py-2 bg-gray-50">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="text-xs text-gray-500 uppercase">
                                                    <th class="text-left px-2 py-1">Cuenta</th>
                                                    <th class="text-left px-2 py-1">Tercero</th>
                                                    <th class="text-right px-2 py-1">Debe</th>
                                                    <th class="text-right px-2 py-1">Haber</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="linea in asiento.lineas" :key="linea.id" class="border-t border-gray-100">
                                                    <td class="px-2 py-1 text-xs">{{ linea.cuenta_contable?.codigo_completo }} - {{ linea.cuenta_contable?.nombre }}</td>
                                                    <td class="px-2 py-1 text-xs text-gray-500">{{ linea.tercero_cuenta?.tercero?.razon_social || '-' }}</td>
                                                    <td class="px-2 py-1 text-right text-xs font-mono text-green-700">{{ parseFloat(linea.debe) > 0 ? '$ ' + parseFloat(linea.debe).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '' }}</td>
                                                    <td class="px-2 py-1 text-right text-xs font-mono text-red-700">{{ parseFloat(linea.haber) > 0 ? '$ ' + parseFloat(linea.haber).toLocaleString('es-AR', { minimumFractionDigits: 2 }) : '' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!asientos.data?.length">
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Sin asientos en este periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-gray-200" v-if="asientos.last_page > 1">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-sm">
                        <span class="text-gray-500">Pág. {{ asientos.current_page }} de {{ asientos.last_page }} ({{ asientos.total }} asientos)</span>
                        <div class="flex items-center gap-1 flex-wrap">
                            <button :disabled="asientos.current_page <= 1" @click="goToPage(asientos.current_page - 1)" class="px-3 py-1 bg-white border rounded-md hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Anterior</button>
                            <button
                                v-for="p in asientos.last_page"
                                :key="p"
                                @click="goToPage(p)"
                                :class="p === asientos.current_page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 border rounded-md min-w-[36px]"
                            >{{ p }}</button>
                            <button :disabled="asientos.current_page >= asientos.last_page" @click="goToPage(asientos.current_page + 1)" class="px-3 py-1 bg-white border rounded-md hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Siguiente</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DialogModal :show="showCreate" max-width="5xl" @close="showCreate = false">
            <template #title>Crear asiento manual</template>
            <template #content>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <InputLabel value="Fecha" class="!text-xs" />
                            <TextInput v-model="createForm.fecha" type="date" class="mt-0.5 block w-full text-sm py-1" />
                            <InputError class="mt-1 text-xs" :message="createForm.errors.fecha" />
                        </div>
                        <div>
                            <InputLabel value="Moneda" class="!text-xs" />
                            <select v-model="createForm.moneda" class="mt-0.5 block w-full border-gray-300 rounded-md shadow-sm text-sm py-1">
                                <option value="ARS">ARS</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="BRL">BRL</option>
                            </select>
                            <InputError class="mt-1 text-xs" :message="createForm.errors.moneda" />
                        </div>
                        <div class="sm:col-span-1">
                            <InputLabel value="Descripción" class="!text-xs" />
                            <TextInput v-model="createForm.descripcion" type="text" class="mt-0.5 block w-full text-sm py-1" placeholder="Ej: Ajuste manual" />
                            <InputError class="mt-1 text-xs" :message="createForm.errors.descripcion" />
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-gray-700">Líneas (mínimo 2, Debe = Haber)</span>
                            <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold" @click="addLinea">+ Agregar línea</button>
                        </div>

                        <div class="space-y-2">
                            <div class="hidden sm:grid grid-cols-12 gap-2 text-[10px] uppercase tracking-wider text-gray-500 px-1">
                                <div class="col-span-5">Cuenta</div>
                                <div class="col-span-2 text-right">Debe</div>
                                <div class="col-span-2 text-right">Haber</div>
                                <div class="col-span-2">Detalle</div>
                                <div class="col-span-1"></div>
                            </div>
                            <div v-for="(linea, idx) in createForm.lineas" :key="idx" class="grid grid-cols-12 gap-2 items-start">
                                <div class="col-span-12 sm:col-span-5 relative">
                                    <input v-model="linea._search" @input="onCuentaSearchInput(linea)" @focus="linea._showCuentas = true" @blur="setTimeout(() => linea._showCuentas = false, 150)" type="text" placeholder="Buscar por código o descripción..." class="block w-full border-gray-300 rounded-md shadow-sm text-xs py-1" />
                                    <button v-if="linea.cuenta_contable_id" type="button" class="absolute right-1 top-1.5 text-gray-400 hover:text-gray-600 text-[10px]" @mousedown.prevent="clearCuenta(linea)">✕</button>
                                    <ul v-if="linea._showCuentas" class="absolute z-20 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-40 overflow-y-auto">
                                        <li v-for="c in filteredCuentas(linea._search).slice(0, 40)" :key="c.id" class="px-2 py-1 text-xs hover:bg-indigo-50 cursor-pointer" @mousedown.prevent="selectCuenta(linea, c)">{{ cuentaLabel(c) }}</li>
                                        <li v-if="!filteredCuentas(linea._search).length" class="px-2 py-1 text-xs text-gray-400">Sin resultados</li>
                                    </ul>
                                    <div v-if="linea.cuenta_contable_id" class="text-[10px] text-green-700 mt-0.5 truncate">{{ cuentaLabel(cuentasContables.find(x => x.id == linea.cuenta_contable_id) || {}) }}</div>
                                    <div v-else class="text-[10px] text-gray-400 mt-0.5">Seleccioná una cuenta (código o nombre)</div>
                                </div>
                                <div class="col-span-5 sm:col-span-2">
                                    <TextInput v-model="linea.debe" type="number" min="0" step="0.01" class="block w-full text-xs py-1 text-right" placeholder="0.00" />
                                </div>
                                <div class="col-span-5 sm:col-span-2">
                                    <TextInput v-model="linea.haber" type="number" min="0" step="0.01" class="block w-full text-xs py-1 text-right" placeholder="0.00" />
                                </div>
                                <div class="col-span-10 sm:col-span-2">
                                    <TextInput v-model="linea.descripcion" type="text" class="block w-full text-xs py-1" placeholder="Detalle" />
                                </div>
                                <div class="col-span-2 sm:col-span-1 flex justify-end pt-1">
                                    <button type="button" class="text-xs text-red-600 hover:text-red-800 disabled:opacity-30" :disabled="createForm.lineas.length <= 2" @click="removeLinea(idx)">✕</button>
                                </div>
                            </div>
                        </div>

                        <InputError class="mt-2 text-xs" :message="createForm.errors.lineas" />

                        <div class="mt-3 flex justify-end gap-4 text-xs font-mono border-t border-gray-200 pt-2">
                            <span>Debe: <b class="text-green-700">{{ totalesCreate.debe.toLocaleString('es-AR', {minimumFractionDigits:2}) }}</b></span>
                            <span>Haber: <b class="text-red-700">{{ totalesCreate.haber.toLocaleString('es-AR', {minimumFractionDigits:2}) }}</b></span>
                            <span :class="totalesCreate.balanceado ? 'text-green-600' : 'text-red-600'">{{ totalesCreate.balanceado ? '✓ Balanceado' : '✗ Desbalance: ' + totalesCreate.diff.toLocaleString('es-AR', {minimumFractionDigits:2}) }}</span>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showCreate = false">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="createForm.processing || !totalesCreate.balanceado" @click="submitCreate">Crear asiento</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
