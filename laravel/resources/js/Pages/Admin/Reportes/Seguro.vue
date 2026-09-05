<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    rows: Array,
    detallesPorChofer: Object,
    viajes: Array,
    bultosPorViaje: Object,
    totalGeneral: Number,
    mes: Number,
    anio: Number,
    mesNombre: String,
});

const flash = usePage().props.flash || {};

const editando = ref(null);
const editForm = ref({ desmovil: '', patmovil: '', pacmovil: '', total_viajes: null, total_cargas: null, total_valor_declarado: null });
const editProcessing = ref(false);

const confirmandoEliminar = ref(null);
const deleteProcessing = ref(false);

const formatNum = (n) => {
    const val = Number(n || 0);
    return '$ ' + val.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const cambiarMes = (dir) => {
    const m = props.mes + dir;
    let anio = props.anio;
    let mes = m;
    if (mes < 1) { mes = 12; anio--; }
    if (mes > 12) { mes = 1; anio++; }
    router.get(route('admin.reportes.seguro', { mes, anio }));
};

const abrirEditar = (r) => {
    const ov = r.override_fields || {};
    editForm.value = {
        desmovil: ov.desmovil ?? r.desmovil ?? '',
        patmovil: ov.patmovil ?? r.patmovil ?? '',
        pacmovil: ov.pacmovil ?? r.pacmovil ?? '',
        total_viajes: ov.total_viajes ?? null,
        total_cargas: ov.total_cargas ?? null,
        total_valor_declarado: ov.total_valor_declarado ?? null,
    };
    editando.value = r.nummovil;
};

const cerrarEditar = () => {
    editando.value = null;
    editForm.value = { desmovil: '', patmovil: '', pacmovil: '', total_viajes: null, total_cargas: null, total_valor_declarado: null };
};

const guardarEditar = () => {
    editProcessing.value = true;
    router.post(route('admin.reportes.seguro.update'), {
        nummovil: editando.value,
        mes: props.mes,
        anio: props.anio,
        ...editForm.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { cerrarEditar(); editProcessing.value = false; },
        onError: () => { editProcessing.value = false; },
    });
};

const confirmarEliminar = (nummovil) => {
    confirmandoEliminar.value = nummovil;
};

const ejecutarEliminar = () => {
    deleteProcessing.value = true;
    router.post(route('admin.reportes.seguro.destroy'), {
        nummovil: confirmandoEliminar.value,
        mes: props.mes,
        anio: props.anio,
    }, {
        preserveScroll: true,
        onSuccess: () => { confirmandoEliminar.value = null; deleteProcessing.value = false; },
        onError: () => { deleteProcessing.value = false; },
    });
};

const descargarExcel = () => {
    window.open(route('admin.reportes.seguro.csv', { mes: props.mes, anio: props.anio, agrupacion: vista.value }), '_blank');
};

const hasOverride = (r, field) => {
    const ov = r.override_fields || {};
    return ov[field] !== null && ov[field] !== undefined;
};

const vista = ref('viaje'); // viaje | chofer | bulto

const expandedChofer = ref(new Set());
const expandedViaje = ref(new Set());
const toggleChofer = (key) => {
    const s = new Set(expandedChofer.value);
    if (s.has(key)) s.delete(key);
    else s.add(key);
    expandedChofer.value = s;
};
const choferKey = (r) => `${r.nummovil}|${r.nomchof || 'sin-chofer'}`;
const formatFecha = (v) => {
    if (!v) return '-';
    const s = String(v).slice(0, 10);
    const d = new Date(s + 'T12:00:00');
    if (isNaN(d.getTime())) return s.split('-').reverse().join('-');
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}-${mm}-${yyyy}`;
};
</script>

<template>
    <AppLayout title="Informe seguro">
        <Head title="Informe seguro" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Informe para seguro</h2>
                <div class="flex items-center gap-2">
                    <button @click="cambiarMes(-1)" class="px-3 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50">&larr; Mes anterior</button>
                    <span class="text-sm font-medium">{{ mesNombre }} {{ anio }}</span>
                    <button @click="cambiarMes(1)" class="px-3 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-50">Mes siguiente &rarr;</button>
                    <button @click="descargarExcel" class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">Excel</button>
                    <button @click="window.print()" class="px-3 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">Imprimir</button>
                </div>
            </div>
        </template>

        <div v-if="flash.success" class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
            <div class="bg-green-100 border border-green-200 text-green-800 px-4 py-2 rounded text-sm">{{ flash.success }}</div>
        </div>

        <div class="max-w-7xl mx-auto py-3 sm:px-6 lg:px-8">
            <div class="flex items-center justify-center gap-2 mb-3 print:hidden">
                <span class="text-xs text-gray-500">Vista:</span>
                <button @click="vista = 'chofer'" :class="vista === 'chofer' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border'" class="px-3 py-1 text-xs rounded border">Por chofer/camión</button>
                <button @click="vista = 'viaje'" :class="vista === 'viaje' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border'" class="px-3 py-1 text-xs rounded border">Por viaje (ID envío)</button>
                <button @click="vista = 'bulto'" :class="vista === 'bulto' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border'" class="px-3 py-1 text-xs rounded border">Detalle por bultos</button>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div v-if="!rows.length" class="px-6 py-4 text-center text-sm text-gray-500">
                    No hay movimientos en {{ mesNombre }} {{ anio }}.
                </div>

                <!-- Vista por viaje (agrupado por id_envio) - nuevo requerimiento -->
                <div v-if="vista === 'viaje'" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">ID Envío</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Móvil</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Chofer</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Depósitos</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider">Cargas</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider print:hidden">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="v in (props.viajes || [])" :key="v.id_envio">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 whitespace-nowrap font-mono text-indigo-700 font-bold">
                                        <button @click="toggleViaje(v.id_envio)" class="text-indigo-600 hover:text-indigo-800 mr-1">{{ expandedViaje.has(v.id_envio) ? '▼' : '▶' }}</button>
                                        #{{ v.id_envio }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ formatFecha(v.fecha_envio) }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">{{ v.nummovil }} <span class="text-gray-400">({{ v.patmovil || '-' }})</span></td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ v.nomchof || '-' }}</td>
                                    <td class="px-3 py-2 text-gray-700 max-w-[180px] text-xs">{{ v.depositos_origen || '-' }}</td>
                                    <td class="px-3 py-2 text-center text-gray-700">{{ v.total_cargas }}</td>
                                    <td class="px-3 py-2 text-center text-gray-700">{{ v.total_bultos || '-' }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-gray-700">{{ formatNum(v.total_valor_declarado) }}</td>
                                    <td class="px-3 py-2 text-center print:hidden"><span class="text-xs text-gray-400">{{ (props.bultosPorViaje?.[v.id_envio] || []).length }} bultos</span></td>
                                </tr>
                                <tr v-if="expandedViaje.has(v.id_envio)" class="bg-blue-50/50">
                                    <td colspan="9" class="px-4 py-2">
                                        <div class="text-xs font-semibold text-gray-700 mb-1">Bultos del envío #{{ v.id_envio }} — {{ v.nomchof || 'Sin chofer' }} ({{ (props.bultosPorViaje?.[v.id_envio] || []).length }} bultos)</div>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 text-xs bg-white rounded border">
                                                <thead class="bg-gray-100"><tr>
                                                    <th class="px-2 py-1 text-left">Remitente</th>
                                                    <th class="px-2 py-1 text-left">Carga ID</th>
                                                    <th class="px-2 py-1 text-left">Depósito</th>
                                                    <th class="px-2 py-1 text-center">Cant.</th>
                                                    <th class="px-2 py-1 text-left">Unidad</th>
                                                    <th class="px-2 py-1 text-left">Remito</th>
                                                    <th class="px-2 py-1 text-right">Valor</th>
                                                </tr></thead>
                                                <tbody>
                                                    <template v-for="(grupo, remitente) in (() => { const g={}; (props.bultosPorViaje?.[v.id_envio] || []).forEach(b=>{ const k=b.remitente || 'Sin remitente'; if(!g[k]) g[k]=[]; g[k].push(b); }); return g; })()" :key="remitente">
                                                        <tr class="bg-gray-100"><td colspan="7" class="px-2 py-1 font-semibold text-gray-700">Remitente: {{ remitente }} ({{ grupo.length }} bultos)</td></tr>
                                                        <tr v-for="b in grupo" :key="b.carga_id" class="border-t border-gray-100 hover:bg-gray-50">
                                                            <td class="px-2 py-1 text-gray-600">{{ b.remitente || '-' }}</td>
                                                            <td class="px-2 py-1 font-mono">#{{ b.carga_id }}</td>
                                                            <td class="px-2 py-1">{{ b.deposito_origen }}</td>
                                                            <td class="px-2 py-1 text-center">{{ b.cantidad }}</td>
                                                            <td class="px-2 py-1">{{ b.unidad || '-' }}</td>
                                                            <td class="px-2 py-1 font-mono">{{ b.remito || '-' }}</td>
                                                            <td class="px-2 py-1 text-right font-mono">$ {{ Number(b.valordeclarado || 0).toLocaleString('es-AR', {minimumFractionDigits:2}) }}</td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td colspan="7" class="px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Total general</td>
                                <td class="px-3 py-2 text-right text-xs font-mono font-semibold text-gray-900">{{ formatNum(totalGeneral) }}</td>
                                <td class="print:hidden"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div v-else-if="vista === 'bulto'" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">ID Envío</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Móvil</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Chofer</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Depósito</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Carga</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider">Cant.</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="r in rows" :key="r.nummovil">
                                <tr v-for="d in (props.detallesPorChofer?.[choferKey(r)] || [])" :key="d.carga_id + '-' + d.id_envio" class="hover:bg-gray-50">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ formatFecha(d.fecha_envio) }}</td>
                                    <td class="px-3 py-2 font-mono">#{{ d.id_envio }}</td>
                                    <td class="px-3 py-2 font-mono">{{ d.nummovil }}</td>
                                    <td class="px-3 py-2">{{ d.nomchof || '-' }}</td>
                                    <td class="px-3 py-2">{{ d.deposito_origen }}</td>
                                    <td class="px-3 py-2 font-mono">#{{ d.carga_id }}</td>
                                    <td class="px-3 py-2 text-center">{{ d.cantidad }}</td>
                                    <td class="px-3 py-2 text-right font-mono">$ {{ Number(d.valordeclarado || 0).toLocaleString('es-AR', {minimumFractionDigits:2}) }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td colspan="7" class="px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Total general</td>
                                <td class="px-3 py-2 text-right text-xs font-mono font-semibold text-gray-900">{{ formatNum(totalGeneral) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Móvil</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Patente</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Acoplado</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Chofer</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Fecha envío</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Depósitos origen</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider">Viajes</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider">Cargas</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">Valor declarado</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase tracking-wider print:hidden">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template v-for="r in rows" :key="r.nummovil">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-900">
                                        <button @click="toggleChofer(choferKey(r))" class="text-indigo-600 hover:text-indigo-800 mr-1">{{ expandedChofer.has(choferKey(r)) ? '▼' : '▶' }}</button>
                                        {{ r.nummovil }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ r.desmovil || '-' }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">{{ r.patmovil || '-' }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-700">{{ r.pacmovil || '-' }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ r.nomchof || '-' }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-700 text-xs">{{ r.primera_fecha ? formatFecha(r.primera_fecha) : '-' }}<span v-if="r.ultima_fecha && r.ultima_fecha !== r.primera_fecha"> - {{ formatFecha(r.ultima_fecha) }}</span></td>
                                    <td class="px-3 py-2 text-gray-700 max-w-[200px] text-xs">{{ r.depositos_origen || '-' }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-center text-gray-700" :class="{ 'font-bold text-amber-700': hasOverride(r, 'total_viajes') }">{{ r.total_viajes }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-center text-gray-700" :class="{ 'font-bold text-amber-700': hasOverride(r, 'total_cargas') }">{{ r.total_cargas }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right font-mono text-gray-700" :class="{ 'font-bold text-amber-700': hasOverride(r, 'total_valor_declarado') }">{{ formatNum(r.total_valor_declarado) }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap text-center print:hidden">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="abrirEditar(r)" class="text-indigo-600 hover:text-indigo-800 text-[10px] underline">Editar</button>
                                            <button @click="confirmarEliminar(r.nummovil)" class="text-red-600 hover:text-red-800 text-[10px] underline">Eliminar</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expandedChofer.has(choferKey(r))" class="bg-blue-50/50">
                                    <td colspan="11" class="px-4 py-2">
                                        <div class="text-xs font-semibold text-gray-700 mb-1">Detalle de viajes — {{ r.nomchof || 'Sin chofer' }} ({{ (props.detallesPorChofer?.[choferKey(r)] || []).length }} viajes)</div>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 text-xs bg-white rounded border">
                                                <thead class="bg-gray-100"><tr>
                                                    <th class="px-2 py-1 text-left">Fecha</th>
                                                    <th class="px-2 py-1 text-left">ID Envío</th>
                                                    <th class="px-2 py-1 text-left">Depósito</th>
                                                    <th class="px-2 py-1 text-left">Carga ID</th>
                                                    <th class="px-2 py-1 text-center">Cant.</th>
                                                    <th class="px-2 py-1 text-left">Unidad</th>
                                                    <th class="px-2 py-1 text-left">Remito</th>
                                                    <th class="px-2 py-1 text-right">Valor</th>
                                                </tr></thead>
                                                <tbody>
                                                    <tr v-for="d in (props.detallesPorChofer?.[choferKey(r)] || [])" :key="d.carga_id + '-' + d.id_envio" class="border-t border-gray-100 hover:bg-gray-50">
                                                        <td class="px-2 py-1 whitespace-nowrap">{{ formatFecha(d.fecha_envio) }}</td>
                                                        <td class="px-2 py-1 font-mono">#{{ d.id_envio }}</td>
                                                        <td class="px-2 py-1">{{ d.deposito_origen }}</td>
                                                        <td class="px-2 py-1 font-mono">#{{ d.carga_id }}</td>
                                                        <td class="px-2 py-1 text-center">{{ d.cantidad }}</td>
                                                        <td class="px-2 py-1">{{ d.unidad || '-' }}</td>
                                                        <td class="px-2 py-1 font-mono">{{ d.remito || '-' }}</td>
                                                        <td class="px-2 py-1 text-right font-mono">$ {{ Number(d.valordeclarado || 0).toLocaleString('es-AR', {minimumFractionDigits:2}) }}</td>
                                                    </tr>
                                                    <tr v-if="!(props.detallesPorChofer?.[choferKey(r)] || []).length">
                                                        <td colspan="8" class="px-2 py-2 text-center text-gray-400">Sin detalle</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td colspan="9" class="px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase">Total general</td>
                                <td class="px-3 py-2 text-right text-xs font-mono font-semibold text-gray-900">{{ formatNum(totalGeneral) }}</td>
                                <td class="print:hidden"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="mt-4 text-xs text-gray-500 text-center print:hidden">
                Los datos corresponden al período {{ mesNombre }} {{ anio }}. Fuente: base de datos externa. Los valores en <span class="font-bold text-amber-700">ámbar</span> fueron editados manualmente.
            </div>
        </div>

        <DialogModal :show="!!editando" @close="cerrarEditar">
            <template #title>
                Editar móvil #{{ editando }}
            </template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Descripción" />
                        <TextInput v-model="editForm.desmovil" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Patente" />
                        <TextInput v-model="editForm.patmovil" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Acoplado" />
                        <TextInput v-model="editForm.pacmovil" type="text" class="mt-1 block w-full" />
                    </div>
                    <hr class="border-gray-200">
                    <p class="text-xs text-gray-500">Dejar en blanco para usar los valores originales de la base externa.</p>
                    <div>
                        <InputLabel value="Total viajes" />
                        <TextInput v-model="editForm.total_viajes" type="number" min="0" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Total cargas" />
                        <TextInput v-model="editForm.total_cargas" type="number" min="0" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Valor declarado" />
                        <TextInput v-model="editForm.total_valor_declarado" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <SecondaryButton @click="cerrarEditar">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="editProcessing" @click="guardarEditar">Guardar</PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <DialogModal :show="!!confirmandoEliminar" @close="confirmandoEliminar = null">
            <template #title>
                Eliminar móvil #{{ confirmandoEliminar }}
            </template>
            <template #content>
                <p class="text-sm text-gray-600">¿Estás seguro de eliminar este móvil del informe? Se quitarán las ediciones manuales. Esta acción no se puede deshacer.</p>
            </template>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <SecondaryButton @click="confirmandoEliminar = null">Cancelar</SecondaryButton>
                    <DangerButton :disabled="deleteProcessing" @click="ejecutarEliminar">Eliminar</DangerButton>
                </div>
            </template>
        </DialogModal>
    </AppLayout>
</template>

<style>
@media print {
    body { font-size: 10px; }
    .sm\:px-6 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
}
</style>
