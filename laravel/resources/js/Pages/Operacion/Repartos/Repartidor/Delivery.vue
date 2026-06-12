<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    hojas: Array,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const expandedHojaId = ref(null);

const toggleHoja = (id) => {
    expandedHojaId.value = expandedHojaId.value === id ? null : id;
};

const deliveryForm = useForm({
    estado_entrega: 'entregado',
    recibe_nombre: '',
    recibe_dni: '',
    email_contacto: '',
    observacion_operador: '',
    firma_recibo: '',
    forma_pago: '',
    importe_cobrado: '',
    foto_comprobante_pago: null,
    foto_remito_firmado: null,
});
const activeItem = ref(null);
const showDelivery = ref(false);

const canvasRef = ref(null);
const isDrawing = ref(false);

const openDelivery = (item) => {
    activeItem.value = item;
    deliveryForm.estado_entrega = item.estado_entrega || 'entregado';
    deliveryForm.recibe_nombre = item.recibe_nombre || '';
    deliveryForm.recibe_dni = item.recibe_dni || '';
    deliveryForm.email_contacto = item.entrega_cuenta?.email || '';
    deliveryForm.observacion_operador = item.observacion_operador || '';
    deliveryForm.firma_recibo = '';
    deliveryForm.forma_pago = item.forma_pago || '';
    deliveryForm.importe_cobrado = item.importe_cobrado || '';
    deliveryForm.foto_comprobante_pago = null;
    deliveryForm.foto_remito_firmado = null;
    deliveryForm.clearErrors();
    showDelivery.value = true;
};

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
    deliveryForm.post(route('repartidor.entregar', [activeItem.value.hoja_ruta_id, activeItem.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            showDelivery.value = false;
            activeItem.value = null;
        },
    });
};

const statusClass = (estado) => {
    if (estado === 'entregado') return 'bg-green-100 text-green-800 border-green-200';
    if (estado === 'entregado_con_diferencia') return 'bg-blue-100 text-blue-800 border-blue-200';
    if (estado === 'no_entregado') return 'bg-red-100 text-red-800 border-red-200';
    return 'bg-yellow-100 text-yellow-800 border-yellow-200';
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Head title="Repartidor" />

        <div class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Repartidor</h1>
                    <p class="text-xs text-gray-500">{{ page.props.auth.user.name }}</p>
                </div>
                <div class="text-xs text-gray-500">{{ hojas.length }} hoja(s) asignada(s)</div>
            </div>
        </div>

        <div v-if="flashSuccess" class="max-w-3xl mx-auto mt-4 px-4">
            <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">
                {{ flashSuccess }}
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-6 space-y-4">
            <div v-if="!hojas.length" class="text-center py-16">
                <div class="text-gray-400 text-5xl mb-4">&#128666;</div>
                <h2 class="text-lg font-medium text-gray-700">No tenes hojas de ruta asignadas</h2>
                <p class="text-sm text-gray-500 mt-1">Cuando te asignen una hoja, aparecera aqui.</p>
            </div>

            <div v-for="hoja in hojas" :key="hoja.id" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <button class="w-full px-4 py-3 flex items-center justify-between gap-3 text-left hover:bg-gray-50" @click="toggleHoja(hoja.id)">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900">Hoja #{{ hoja.id }}</span>
                            <span class="text-xs text-gray-500">{{ hoja.fecha }}</span>
                        </div>
                        <div class="text-xs text-gray-600 mt-0.5 truncate">
                            {{ hoja.deposito?.nombre || '-' }}
                            <template v-if="hoja.vehiculo"> · {{ hoja.vehiculo.patente }}</template>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800">
                            {{ hoja.items.filter(i => i.estado_entrega === 'entregado' || i.estado_entrega === 'entregado_con_diferencia').length }}/{{ hoja.items.length }}
                        </span>
                        <svg class="size-5 text-gray-400 transition-transform" :class="{ 'rotate-180': expandedHojaId === hoja.id }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </button>

                <div v-if="expandedHojaId === hoja.id" class="border-t border-gray-100 divide-y divide-gray-100">
                    <div v-for="item in hoja.items" :key="item.id" class="px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900">{{ item.entrega_cuenta?.tercero?.razon_social || 'Sin datos' }}</span>
                                    <span class="text-xs text-gray-500">#{{ item.orden }}</span>
                                </div>
                                <div class="text-xs text-gray-600 mt-0.5">
                                    {{ item.direccion || item.entrega_cuenta?.direccion || 'Sin direccion' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ item.localidad || item.entrega_cuenta?.localidad || '' }}
                                    {{ item.telefono || item.entrega_cuenta?.telefono ? '· Tel: ' + (item.telefono || item.entrega_cuenta?.telefono) : '' }}
                                </div>
                                <div v-if="item.comprobante" class="text-xs text-gray-500 mt-0.5">
                                    Factura: {{ item.comprobante.moneda }} {{ item.comprobante.total }}
                                </div>
                                <div v-if="(item.estado_entrega === 'entregado' || item.estado_entrega === 'entregado_con_diferencia') && item.recibe_nombre" class="text-xs text-green-700 mt-1">
                                    Recibio: {{ item.recibe_nombre }} (DNI: {{ item.recibe_dni || '-' }})
                                </div>
                                <div v-if="(item.estado_entrega === 'entregado' || item.estado_entrega === 'entregado_con_diferencia') && item.forma_pago" class="text-xs text-gray-600 mt-0.5">
                                    Pago: {{ item.forma_pago }} <template v-if="item.importe_cobrado">- ${{ item.importe_cobrado }}</template>
                                </div>
                                <div v-if="item.foto_comprobante_pago" class="mt-1">
                                    <a :href="'/storage/' + item.foto_comprobante_pago" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 underline">Ver comprobante de pago</a>
                                </div>
                                <div v-if="item.foto_remito_firmado" class="mt-1">
                                    <a :href="'/storage/' + item.foto_remito_firmado" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 underline">Ver remito firmado</a>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium border" :class="statusClass(item.estado_entrega)">
                                    {{ item.estado_entrega }}
                                </span>
                                <button
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 active:bg-indigo-200 transition-colors"
                                    @click="openDelivery(item)"
                                >
                                    {{ item.estado_entrega === 'entregado' || item.estado_entrega === 'entregado_con_diferencia' ? 'Editar' : 'Entregar' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showDelivery" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
            <div class="fixed inset-0 bg-black/40" @click="showDelivery = false" />
            <div class="relative bg-white w-full sm:max-w-lg sm:rounded-lg shadow-xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Registrar entrega</h2>
                    <button class="text-gray-400 hover:text-gray-600" @click="showDelivery = false">
                        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Estado</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                class="px-3 py-2 text-sm font-medium rounded-md border text-center transition-colors"
                                :class="deliveryForm.estado_entrega === 'entregado' ? 'bg-green-100 border-green-400 text-green-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                @click="deliveryForm.estado_entrega = 'entregado'"
                            >Entregado</button>
                            <button
                                class="px-3 py-2 text-sm font-medium rounded-md border text-center transition-colors"
                                :class="deliveryForm.estado_entrega === 'entregado_con_diferencia' ? 'bg-blue-100 border-blue-400 text-blue-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                @click="deliveryForm.estado_entrega = 'entregado_con_diferencia'"
                            >C/ Diferencia</button>
                            <button
                                class="px-3 py-2 text-sm font-medium rounded-md border text-center transition-colors"
                                :class="deliveryForm.estado_entrega === 'no_entregado' ? 'bg-red-100 border-red-400 text-red-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                                @click="deliveryForm.estado_entrega = 'no_entregado'"
                            >No entregado</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Email del destinatario</label>
                        <div v-if="activeItem?.entrega_cuenta?.email" class="flex items-center gap-2 text-sm">
                            <span class="text-gray-900">{{ activeItem.entrega_cuenta.email }}</span>
                            <span class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">Verificar</span>
                        </div>
                        <div v-else class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-sm text-blue-800">
                            Solicitar email al cliente
                        </div>
                        <input v-model="deliveryForm.email_contacto" type="email" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Email de contacto (opcional)" />
                    </div>

                    <template v-if="deliveryForm.estado_entrega === 'entregado' || deliveryForm.estado_entrega === 'entregado_con_diferencia'">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Recibe nombre</label>
                            <input v-model="deliveryForm.recibe_nombre" type="text" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Nombre y apellido" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Recibe DNI</label>
                            <input v-model="deliveryForm.recibe_dni" type="text" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="DNI" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Forma de pago</label>
                            <select v-model="deliveryForm.forma_pago" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">Seleccionar...</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="cheque">Cheque</option>
                                <option value="cuenta_corriente">Cuenta corriente</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Importe cobrado</label>
                            <input v-model="deliveryForm.importe_cobrado" type="number" step="0.01" min="0" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="0.00" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Foto comprobante de pago</label>
                            <input type="file" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" @input="deliveryForm.foto_comprobante_pago = $event.target.files[0]" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Foto remito firmado</label>
                            <input type="file" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" @input="deliveryForm.foto_remito_firmado = $event.target.files[0]" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Firma digital</label>
                            <p class="text-xs text-gray-500 mb-2">Firme en el recuadro con el dedo o mouse</p>
                            <div class="border border-gray-300 rounded-md overflow-hidden bg-white">
                                <canvas
                                    ref="canvasRef"
                                    width="400"
                                    height="160"
                                    class="w-full touch-none"
                                    style="height: 160px;"
                                    @mousedown="startDrawing"
                                    @mousemove="draw"
                                    @mouseup="stopDrawing"
                                    @mouseleave="stopDrawing"
                                    @touchstart.prevent="startDrawing"
                                    @touchmove.prevent="draw"
                                    @touchend="stopDrawing"
                                />
                            </div>
                            <button class="mt-2 text-xs text-gray-500 hover:text-gray-700 underline" @click="clearCanvas">Limpiar firma</button>
                        </div>
                    </template>

                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Observacion</label>
                        <textarea v-model="deliveryForm.observacion_operador" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" rows="2" placeholder="(opcional)"></textarea>
                    </div>
                </div>

                <div class="sticky bottom-0 bg-white border-t border-gray-200 px-4 py-3 flex items-center justify-end gap-2">
                    <button class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900" @click="showDelivery = false">Cancelar</button>
                    <button
                        class="px-4 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="deliveryForm.processing"
                        @click="submitDelivery"
                    >
                        {{ deliveryForm.processing ? 'Guardando...' : 'Confirmar entrega' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
