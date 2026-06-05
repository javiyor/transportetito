<script setup>
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref } from 'vue';

const emit = defineEmits(['imported']);

const show = defineModel('show', { type: Boolean, default: false });

const uploading = ref(false);
const error = ref('');
const result = ref(null);
const previewText = ref('');

const handleFileChange = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    error.value = '';
    result.value = null;
    previewText.value = '';
    uploading.value = true;

    const formData = new FormData();
    formData.append('file', file);

    try {
        const res = await fetch(route('compras.proveedores.comprobantes.pdf-import'), {
            method: 'POST',
            body: formData,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await res.json();
        if (!data.success) {
            error.value = data.error || 'Error al procesar el PDF.';
            if (data.texto_extraido) {
                previewText.value = data.texto_extraido;
            }
        } else {
            result.value = data;
            previewText.value = data.texto_extraido || '';
        }
    } catch (e) {
        error.value = 'Error de conexion al procesar el PDF.';
    } finally {
        uploading.value = false;
    }
};

const applyData = () => {
    if (!result.value?.datos) return;
    emit('imported', result.value.datos);
    show.value = false;
    result.value = null;
    error.value = '';
};

const close = () => {
    show.value = false;
    result.value = null;
    error.value = '';
};
</script>

<template>
    <DialogModal :show="show" @close="close">
        <template #title>Importar comprobante desde PDF</template>
        <template #content>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Seleccionar archivo PDF</label>
                    <input type="file" accept=".pdf" class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" @change="handleFileChange" />
                </div>

                <div v-if="uploading" class="flex items-center justify-center py-8">
                    <div class="text-sm text-gray-500">Procesando PDF...</div>
                </div>

                <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 p-4">
                    <p class="text-sm text-red-800">{{ error }}</p>
                    <details v-if="previewText" class="mt-2">
                        <summary class="text-xs text-red-600 cursor-pointer">Ver texto extraido</summary>
                        <pre class="mt-2 text-xs text-gray-600 whitespace-pre-wrap max-h-40 overflow-y-auto">{{ previewText }}</pre>
                    </details>
                </div>

                <div v-if="result?.success" class="rounded-lg bg-green-50 border border-green-200 p-4">
                    <div class="flex items-center gap-2 text-sm font-medium text-green-800 mb-3">
                        <span>Datos reconocidos (confianza: {{ result.confianza }}%)</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <div><dt class="text-xs text-gray-500">CUIT</dt><dd class="font-medium text-gray-900">{{ result.datos.cuit || '-' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Razon social</dt><dd class="font-medium text-gray-900">{{ result.datos.razon_social || '-' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Tipo</dt><dd class="font-medium text-gray-900">{{ result.datos.tipo || '-' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Numero</dt><dd class="font-medium text-gray-900">{{ result.datos.numero || '-' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Fecha emision</dt><dd class="font-medium text-gray-900">{{ result.datos.fecha_emision || '-' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Moneda</dt><dd class="font-medium text-gray-900">{{ result.datos.moneda || 'ARS' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Subtotal</dt><dd class="font-medium text-gray-900">{{ result.datos.subtotal ?? '-' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">IVA total</dt><dd class="font-medium text-gray-900">{{ result.datos.iva_total ?? '-' }}</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-gray-500">Total</dt><dd class="font-medium text-gray-900">{{ result.datos.total ?? '-' }}</dd></div>
                    </dl>
                    <div v-if="result.datos.iva_items?.length" class="mt-3">
                        <div class="text-xs font-medium text-gray-500 mb-1">IVA desglosado</div>
                        <div v-for="(item, i) in result.datos.iva_items" :key="i" class="text-xs text-gray-700">
                            {{ item.alicuota }}% · Base ${{ item.base_imponible }} · IVA ${{ item.importe }}
                        </div>
                    </div>
                    <div v-if="result.datos.percepciones?.length" class="mt-2 text-xs text-gray-700">
                        Percepciones: {{ result.datos.percepciones.length }}
                    </div>
                    <div v-if="result.datos.retenciones?.length" class="text-xs text-gray-700">
                        Retenciones: {{ result.datos.retenciones.length }}
                    </div>
                </div>
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="close">Cancelar</SecondaryButton>
            <PrimaryButton v-if="result?.success" class="ms-3" @click="applyData">Usar datos</PrimaryButton>
        </template>
    </DialogModal>
</template>
