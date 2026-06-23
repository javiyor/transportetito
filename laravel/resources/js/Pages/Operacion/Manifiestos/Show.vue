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
    pedidosPendientes: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

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

const recepcionForms = reactive({});

for (const p of (props.manifiesto?.pedidos || [])) {
    recepcionForms[p.id] = useForm({
        recepcion_estado: p.recepcion_estado || 'correcto',
        recepcion_observacion: p.recepcion_observacion || '',
        recepcion_errores: p.recepcion_errores || [],
        recepcion_foto: null,
    });
}

const toggleError = (pedidoId, campo) => {
    const form = recepcionForms[pedidoId];
    const idx = form.recepcion_errores.indexOf(campo);
    if (idx >= 0) {
        form.recepcion_errores.splice(idx, 1);
    } else {
        form.recepcion_errores.push(campo);
    }
};

const onRecepcionFotoChange = (pedidoId, e) => {
    recepcionForms[pedidoId].recepcion_foto = e.target.files[0] || null;
};

const guardarRecepcion = (pedidoId) => {
    recepcionForms[pedidoId].put(route('operacion.pedidos.recepcion.update', pedidoId), { preserveScroll: true });
};

const asignarAManifiesto = async (pedidoId) => {
    if (!confirm('Asignar este pedido al manifiesto actual?')) return;
    try {
        await fetch(route('operacion.manifiestos.pedidos.asignar', [props.manifiesto.id, pedidoId]), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        window.location.reload();
    } catch {
        alert('Error al asignar pedido.');
    }
};

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
const filtroRecepcion = reactive({ soloErrores: false });

const pedidosVisibles = computed(() => {
    const items = props.manifiesto.pedidos || [];
    return filtroRecepcion.soloErrores ? items.filter((p) => p.recepcion_estado === 'con_error') : items;
});

const formatFecha = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 10);
};


</script>

<template>
    <AppLayout :title="`Control de pedidos / Manifiesto #${manifiesto.id}`">
        <Head :title="`Control de pedidos / Manifiesto #${manifiesto.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control de pedidos / Manifiesto #{{ manifiesto.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ formatFecha(manifiesto.fecha) }} · {{ manifiesto.empresa?.razon_social || '-' }} · {{ manifiesto.deposito?.nombre || '-' }}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.terceros.index', { tipo: 'cliente' })">
                        <SecondaryButton>Nuevo cliente</SecondaryButton>
                    </Link>
                    <Link :href="route('operacion.manifiestos.index')">
                        <SecondaryButton>Volver</SecondaryButton>
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
                        <p class="mt-1 text-sm text-gray-600">{{ totalPedidos }} cargados</p>
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

                <div class="mt-8 flex items-center justify-between gap-4 flex-wrap">
                    <div class="text-sm text-gray-600">Control de recepcion de pedidos importados.</div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <Checkbox v-model:checked="filtroRecepcion.soloErrores" />
                        Mostrar solo pedidos con error
                    </label>
                </div>

                <div class="mt-4 space-y-4 lg:hidden">
                    <div
                        v-for="p in pedidosVisibles"
                        :key="p.id"
                        class="rounded-lg border p-4"
                        :class="p.recepcion_estado === 'con_error' ? 'border-red-200 bg-red-50' : (p.recepcion_estado === 'correcto' ? 'border-green-200 bg-green-50/40' : 'border-gray-200 bg-white')"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Pedido #{{ p.id }}</div>
                                <div class="text-xs text-gray-500">{{ p.paga }}</div>
                            </div>
                            <div class="text-xs text-gray-500 text-right">
                                <div>Bultos {{ p.bultos }}</div>
                                <div>Palets {{ p.palets }}</div>
                            </div>
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
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">Valor declarado</div>
                                    <div class="font-medium text-gray-900">{{ p.valor_declarado }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wider text-gray-500">CR</div>
                                    <div class="font-medium text-gray-900">{{ p.cr_importe || '-' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 space-y-2 border-t border-gray-200 pt-4">
                            <select v-model="recepcionForms[p.id].recepcion_estado" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="recibido">Recibido</option>
                                <option value="correcto">Correcto</option>
                                <option value="con_error">Con error</option>
                            </select>
                            <div v-if="recepcionForms[p.id].recepcion_estado === 'con_error'" class="space-y-1 pt-1">
                                <div class="text-xs font-medium text-red-700">Campos con error:</div>
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" value="remitente" :checked="recepcionForms[p.id].recepcion_errores?.includes('remitente')" @change="toggleError(p.id, 'remitente')" class="rounded border-gray-300" /> Remitente</label>
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" value="destinatario" :checked="recepcionForms[p.id].recepcion_errores?.includes('destinatario')" @change="toggleError(p.id, 'destinatario')" class="rounded border-gray-300" /> Destinatario</label>
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" value="valor_declarado" :checked="recepcionForms[p.id].recepcion_errores?.includes('valor_declarado')" @change="toggleError(p.id, 'valor_declarado')" class="rounded border-gray-300" /> Valor declarado</label>
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" value="bultos" :checked="recepcionForms[p.id].recepcion_errores?.includes('bultos')" @change="toggleError(p.id, 'bultos')" class="rounded border-gray-300" /> Bultos</label>
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" value="palets" :checked="recepcionForms[p.id].recepcion_errores?.includes('palets')" @change="toggleError(p.id, 'palets')" class="rounded border-gray-300" /> Palets</label>
                                <TextInput v-model="recepcionForms[p.id].recepcion_observacion" type="text" class="block w-full" placeholder="Observacion adicional" />
                                <InputError class="mt-1" :message="recepcionForms[p.id].errors.recepcion_errores" />
                                <div class="pt-1">
                                    <label class="text-xs text-gray-500">Foto del bulto:</label>
                                    <input type="file" accept="image/*" class="block w-full text-sm mt-1" @change="onRecepcionFotoChange(p.id, $event)" />
                                </div>
                                <div v-if="p.recepcion_fotos?.length" class="text-xs text-green-600">Fotos: {{ p.recepcion_fotos.length }}</div>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <div class="text-xs text-gray-500">
                                    <span v-if="p.recepcion_controlado_at">Ultimo control: {{ String(p.recepcion_controlado_at).replace('T', ' ').slice(0, 16) }}</span>
                                    <span v-else>Sin control</span>
                                </div>
                                <PrimaryButton :disabled="recepcionForms[p.id].processing" @click.prevent="guardarRecepcion(p.id)">Guardar</PrimaryButton>
                            </div>
                        </div>


                    </div>

                    <div v-if="!pedidosVisibles.length" class="rounded-lg border border-gray-200 bg-white px-6 py-10 text-center text-sm text-gray-500">
                        Todavia no hay pedidos cargados.
                    </div>
                </div>

                <div class="mt-4 hidden lg:block overflow-x-auto">
                    <table class="min-w-[1400px] w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recepcion</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Hoja Ruta</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remitente</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatario</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Palets</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CR</th>
                             </tr>
                         </thead>
                         <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in pedidosVisibles" :key="p.id" :class="p.recepcion_estado === 'con_error' ? 'bg-red-50' : (p.recepcion_estado === 'correcto' ? 'bg-green-50/40' : '')">
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">{{ p.id }}</td>
                                <td class="px-4 py-4 whitespace-nowrap align-top">
                                    <div class="flex items-center gap-1">
                                        <select v-model="recepcionForms[p.id].recepcion_estado" class="border-gray-300 rounded text-xs py-1 px-1 w-24 focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="recibido">Recibido</option>
                                            <option value="correcto">Correcto</option>
                                            <option value="con_error">Con error</option>
                                        </select>
                                        <button type="button" class="inline-flex items-center px-2 py-1 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50" :disabled="recepcionForms[p.id].processing" @click.prevent="guardarRecepcion(p.id)">✓</button>
                                    </div>
                                    <div v-if="recepcionForms[p.id].recepcion_estado === 'con_error'" class="mt-2 space-y-1">
                                        <div class="text-xs font-medium text-red-700">Campos con error:</div>
                                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" value="remitente" :checked="recepcionForms[p.id].recepcion_errores?.includes('remitente')" @change="toggleError(p.id, 'remitente')" class="rounded border-gray-300" /> Remitente</label>
                                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" value="destinatario" :checked="recepcionForms[p.id].recepcion_errores?.includes('destinatario')" @change="toggleError(p.id, 'destinatario')" class="rounded border-gray-300" /> Destinatario</label>
                                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" value="valor_declarado" :checked="recepcionForms[p.id].recepcion_errores?.includes('valor_declarado')" @change="toggleError(p.id, 'valor_declarado')" class="rounded border-gray-300" /> Valor declarado</label>
                                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" value="bultos" :checked="recepcionForms[p.id].recepcion_errores?.includes('bultos')" @change="toggleError(p.id, 'bultos')" class="rounded border-gray-300" /> Bultos</label>
                                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" value="palets" :checked="recepcionForms[p.id].recepcion_errores?.includes('palets')" @change="toggleError(p.id, 'palets')" class="rounded border-gray-300" /> Palets</label>
                                        <input v-model="recepcionForms[p.id].recepcion_observacion" type="text" class="w-full border-gray-300 rounded text-xs py-1 px-1 mt-1" placeholder="Observacion adicional" />
                                        <InputError class="mt-0.5" :message="recepcionForms[p.id].errors.recepcion_errores" />
                                        <div class="pt-1">
                                            <label class="text-xs text-gray-500">Foto del bulto:</label>
                                            <input type="file" accept="image/*" class="block w-full text-xs mt-0.5" @change="onRecepcionFotoChange(p.id, $event)" />
                                        </div>
                                        <div v-if="p.recepcion_fotos?.length" class="text-xs text-green-600">Fotos: {{ p.recepcion_fotos.length }}</div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        <span v-if="p.recepcion_controlado_at">{{ String(p.recepcion_controlado_at).replace('T', ' ').slice(0, 10) }}</span>
                                        <span v-else>Sin control</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.numero_hoja_ruta_origen || '-' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ p.remitente?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ p.remitente?.cuit || '' }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ p.destinatario?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ p.destinatario?.cuit || '' }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 text-center">{{ p.bultos }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 text-center">{{ p.palets }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-mono text-gray-700">${{ Number(p.valor_declarado).toFixed(2) }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.cr_importe || '-' }}</td>
                             </tr>

                             <tr v-if="!pedidosVisibles.length">
                                 <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500">Todavia no hay pedidos cargados.</td>
                             </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pedidosPendientes.length" class="mt-8 border-t border-gray-200 pt-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Pedidos pendientes de otras empresas</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Hoja Ruta</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Remitente</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Destinatario</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bultos</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Accion</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="p in pedidosPendientes" :key="p.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ p.id }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ p.numero_hoja_ruta_origen || '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ p.remitente?.razon_social || '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ p.destinatario?.razon_social || '-' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ p.bultos }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ p.valor_declarado }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <button type="button" class="text-indigo-600 hover:text-indigo-900 text-xs" @click="asignarAManifiesto(p.id)">
                                            Asignar a este manifiesto
                                        </button>
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
