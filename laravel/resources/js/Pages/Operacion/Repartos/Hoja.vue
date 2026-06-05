<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DialogModal from '@/Components/DialogModal.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    hoja: Object,
    vehiculos: Array,
    zonas: Array,
    choferes: Array,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const closeForm = useForm({ confirm: true });

const cerrar = () => {
    closeForm.post(route('operacion.repartos.hojas.cerrar', props.hoja.id));
};

const setEntrega = (itemId, estado) => {
    useForm({ estado_entrega: estado }).put(route('operacion.repartos.hojas.items.update', [props.hoja.id, itemId]), { preserveScroll: true });
};

const setOrden = (itemId, orden) => {
    useForm({ orden }).put(route('operacion.repartos.hojas.items.update', [props.hoja.id, itemId]), { preserveScroll: true });
};

const setObs = (itemId, observacion_operador) => {
    useForm({ observacion_operador }).put(route('operacion.repartos.hojas.items.update', [props.hoja.id, itemId]), { preserveScroll: true });
};

const deliveryForm = useForm({});
const deliveryItem = ref(null);
const showDelivery = ref(false);

const openDelivery = (item) => {
    deliveryItem.value = item;
    deliveryForm.estado_entrega = 'entregado';
    deliveryForm.recibe_nombre = item.recibe_nombre || '';
    deliveryForm.recibe_dni = item.recibe_dni || '';
    deliveryForm.observacion_operador = item.observacion_operador || '';
    deliveryForm.firma_recibo = '';
    deliveryForm.clearErrors();
    showDelivery.value = true;
};

const canvasRef = ref(null);
const isDrawing = ref(false);

const startDrawing = (e) => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const rect = canvas.getBoundingClientRect();
    const x = (e.clientX || e.touches?.[0]?.clientX || 0) - rect.left;
    const y = (e.clientY || e.touches?.[0]?.clientY || 0) - rect.top;
    ctx.beginPath();
    ctx.moveTo(x, y);
    isDrawing.value = true;
};

const draw = (e) => {
    if (!isDrawing.value) return;
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const rect = canvas.getBoundingClientRect();
    const x = (e.clientX || e.touches?.[0]?.clientX || 0) - rect.left;
    const y = (e.clientY || e.touches?.[0]?.clientY || 0) - rect.top;
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#000';
    ctx.lineTo(x, y);
    ctx.stroke();
};

const stopDrawing = () => {
    isDrawing.value = false;
};

const clearCanvas = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    deliveryForm.firma_recibo = '';
};

const saveSignature = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    deliveryForm.firma_recibo = canvas.toDataURL('image/png');
};

const submitDelivery = () => {
    saveSignature();
    deliveryForm.put(route('operacion.repartos.hojas.items.update', [props.hoja.id, deliveryItem.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            showDelivery.value = false;
        },
    });
};

const stats = computed(() => {
    const items = props.hoja.items || [];
    const total = items.reduce((acc, it) => acc + Number(it.comprobante?.total || 0), 0);
    const entregados = items.filter((it) => it.estado_entrega === 'entregado').length;
    const noEntregados = items.filter((it) => it.estado_entrega === 'no_entregado').length;
    const pendientes = items.filter((it) => it.estado_entrega === 'pendiente').length;
    return { total: total.toFixed(2), entregados, noEntregados, pendientes, count: items.length };
});

const vehiculoLabel = computed(() => {
    if (!props.hoja.vehiculo) return null;
    const v = props.hoja.vehiculo;
    return `${v.patente}${v.marca ? ' - ' + v.marca : ''}${v.modelo ? ' ' + v.modelo : ''}`;
});
</script>

<template>
    <AppLayout :title="`Operacion / Hoja #${hoja.id}`">
        <Head :title="`Operacion / Hoja #${hoja.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Hoja #{{ hoja.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ hoja.fecha }}
                        · {{ hoja.deposito?.nombre || '-' }}
                        · Estado: {{ hoja.estado }}
                        <template v-if="hoja.chofer"> · Chofer: {{ hoja.chofer.name }}</template>
                        <template v-if="vehiculoLabel"> · Vehiculo: {{ vehiculoLabel }}</template>
                        <template v-if="hoja.zona"> · Zona: {{ hoja.zona.nombre }}</template>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('operacion.repartos.facturas')" class="inline-flex">
                        <SecondaryButton>Volver</SecondaryButton>
                    </Link>
                    <Link :href="route('operacion.repartos.hojas.print', hoja.id)" target="_blank" class="inline-flex">
                        <SecondaryButton>Imprimir</SecondaryButton>
                    </Link>
                    <PrimaryButton v-if="hoja.estado !== 'cerrada'" :disabled="closeForm.processing" @click.prevent="cerrar">Cerrar hoja</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded mb-6">
                {{ flashSuccess }}
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6 mb-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Items</div>
                        <div class="text-sm font-medium text-gray-900">{{ stats.count }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Pendientes</div>
                        <div class="text-sm font-medium text-gray-900">{{ stats.pendientes }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Entregados</div>
                        <div class="text-sm font-medium text-gray-900">{{ stats.entregados }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Total</div>
                        <div class="text-sm font-medium text-gray-900">{{ hoja.items?.[0]?.comprobante?.moneda || 'ARS' }} {{ stats.total }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orden</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrega</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Factura</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="it in hoja.items" :key="it.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">{{ it.orden }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ it.entrega_cuenta?.tercero?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">CUIT {{ it.entrega_cuenta?.tercero?.cuit || '-' }} · Nro {{ it.entrega_cuenta?.numero_cliente || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ it.direccion || it.entrega_cuenta?.direccion || '' }} {{ it.localidad || it.entrega_cuenta?.localidad ? '· ' + (it.localidad || it.entrega_cuenta?.localidad) : '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ it.comprobante?.moneda }} {{ it.comprobante?.total }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-yellow-100 text-yellow-800': it.estado_entrega === 'pendiente',
                                            'bg-green-100 text-green-800': it.estado_entrega === 'entregado',
                                            'bg-red-100 text-red-800': it.estado_entrega === 'no_entregado',
                                        }"
                                    >{{ it.estado_entrega }}</span>
                                    <div v-if="it.estado_entrega === 'entregado'" class="mt-2 space-y-1">
                                        <div v-if="it.recibe_nombre" class="text-xs text-gray-600">Recibe: {{ it.recibe_nombre }} (DNI: {{ it.recibe_dni || '-' }})</div>
                                        <div v-if="it.fecha_entrega" class="text-xs text-gray-400">Entrega: {{ it.fecha_entrega }}</div>
                                        <div v-if="it.firma_recibo" class="mt-1">
                                            <img :src="it.firma_recibo" alt="Firma" class="h-12 border border-gray-200 rounded" />
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <TextInput
                                        :disabled="hoja.estado === 'cerrada'"
                                        :model-value="it.observacion_operador || ''"
                                        type="text"
                                        class="block w-full text-sm"
                                        placeholder="(opcional)"
                                        @change="setObs(it.id, $event.target.value)"
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <div class="flex justify-end gap-2 flex-wrap">
                                        <TextInput
                                            v-if="hoja.estado !== 'cerrada'"
                                            :model-value="it.orden"
                                            type="number"
                                            min="1"
                                            class="w-20 text-xs"
                                            @change="setOrden(it.id, parseInt($event.target.value, 10))"
                                        />
                                        <SecondaryButton class="text-xs" v-if="hoja.estado !== 'cerrada'" @click.prevent="openDelivery(it)">
                                            {{ it.estado_entrega === 'entregado' ? 'Editar' : 'Entregar' }}
                                        </SecondaryButton>
                                        <SecondaryButton class="text-xs" v-if="hoja.estado !== 'cerrada'" @click.prevent="setEntrega(it.id, 'no_entregado')">No entregado</SecondaryButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!hoja.items?.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Sin items.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <DialogModal :show="showDelivery" @close="showDelivery = false">
            <template #title>Registrar entrega</template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Estado" />
                        <select v-model="deliveryForm.estado_entrega" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="entregado">Entregado</option>
                            <option value="no_entregado">No entregado</option>
                        </select>
                    </div>
                    <div v-if="deliveryForm.estado_entrega === 'entregado'">
                        <InputLabel value="Recibe nombre" />
                        <TextInput v-model="deliveryForm.recibe_nombre" type="text" class="mt-1 block w-full" />
                    </div>
                    <div v-if="deliveryForm.estado_entrega === 'entregado'">
                        <InputLabel value="Recibe DNI" />
                        <TextInput v-model="deliveryForm.recibe_dni" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Observacion" />
                        <textarea v-model="deliveryForm.observacion_operador" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2"></textarea>
                    </div>
                    <div v-if="deliveryForm.estado_entrega === 'entregado'">
                        <InputLabel value="Firma digital" />
                        <div class="mt-1 border border-gray-300 rounded-md overflow-hidden">
                            <canvas
                                ref="canvasRef"
                                width="400"
                                height="150"
                                class="w-full touch-none"
                                style="height: 150px;"
                                @mousedown="startDrawing"
                                @mousemove="draw"
                                @mouseup="stopDrawing"
                                @mouseleave="stopDrawing"
                                @touchstart.prevent="startDrawing"
                                @touchmove.prevent="draw"
                                @touchend="stopDrawing"
                            />
                        </div>
                        <SecondaryButton class="mt-2 text-xs" @click="clearCanvas">Limpiar</SecondaryButton>
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showDelivery = false">Cancelar</SecondaryButton>
                <PrimaryButton :disabled="deliveryForm.processing" @click="submitDelivery">Guardar</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
