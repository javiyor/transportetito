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
    arcaTipos: Array,
});

const search = ref('');
const showDropdown = ref(false);

const filteredCuentas = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) return props.cuentas;
    return props.cuentas.filter(c => {
        const nombre = (c.nombre_cuenta || c.tercero?.razon_social || '').toLowerCase();
        const cuit = (c.tercero?.cuit || '').toLowerCase();
        return nombre.includes(q) || cuit.includes(q);
    });
});

const selectedNombre = ref('');

const selectCuenta = (cuenta) => {
    form.facturar_cuenta_id = cuenta.id;
    selectedNombre.value = cuenta.nombre_cuenta || cuenta.tercero?.razon_social || '';
    search.value = selectedNombre.value;
    showDropdown.value = false;
};

const tipoSeleccionado = computed(() => {
    return props.arcaTipos.find(t => String(t.codigo) === form.arca_tipo_cbte);
});

const discrimina = computed(() => tipoSeleccionado.value?.discrimina ?? false);

const form = useForm({
    facturar_cuenta_id: '',
    fecha_emision: new Date().toISOString().slice(0, 10),
    moneda: 'ARS',
    total: '',
    subtotal: '',
    iva: '',
    discrimina: false,
    disponible_para_hoja_ruta: true,
    arca_punto_venta: props.empresa?.arca_pv_default ?? '',
    arca_tipo_cbte: '',
    arca_numero: '',
    arca_cae: '',
    arca_cae_vto: '',
    observacion: '',
});

const submit = () => {
    form.discrimina = discrimina.value;
    form.post(route('facturacion.manual.store'));
};

const onTipoChange = (e) => {
    form.arca_tipo_cbte = e.target.value;
    if (!discrimina.value) {
        form.subtotal = '';
        form.iva = '';
    }
};
</script>

<template>
    <AppLayout title="Facturacion / Carga manual">
        <Head title="Facturacion / Carga manual" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Facturacion / Carga manual de factura</h2>
                <Link :href="route('facturacion.manifiestos.index')"><SecondaryButton>Volver</SecondaryButton></Link>
            </div>
        </template>

        <div class="max-w-3xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="relative">
                        <InputLabel value="Cliente" />
                        <TextInput
                            v-model="search"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Buscar por nombre o CUIT..."
                            @focus="showDropdown = true"
                            @blur="setTimeout(() => showDropdown = false, 200)"
                        />
                        <input type="hidden" v-model="form.facturar_cuenta_id" />
                        <InputError :message="form.errors.facturar_cuenta_id" />
                        <ul
                            v-if="showDropdown && filteredCuentas.length"
                            class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto text-sm"
                        >
                            <li
                                v-for="c in filteredCuentas"
                                :key="c.id"
                                class="px-3 py-2 hover:bg-indigo-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                                @mousedown.prevent="selectCuenta(c)"
                            >
                                <div class="font-medium text-gray-900">{{ c.nombre_cuenta || c.tercero?.razon_social }}</div>
                                <div class="text-xs text-gray-500">{{ c.tercero?.cuit || 'Sin CUIT' }}</div>
                            </li>
                        </ul>
                        <div v-if="form.facturar_cuenta_id && !showDropdown" class="mt-1 text-xs text-green-700">
                            Seleccionado: {{ selectedNombre }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                        <div>
                            <InputLabel for="arca_punto_venta" value="Punto de venta" />
                            <TextInput id="arca_punto_venta" v-model="form.arca_punto_venta" type="number" min="1" max="9999" class="mt-1 block w-full" />
                            <InputError :message="form.errors.arca_punto_venta" />
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Datos ARCA</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="arca_tipo_cbte" value="Tipo comprobante" />
                                <select
                                    id="arca_tipo_cbte"
                                    :value="form.arca_tipo_cbte"
                                    @change="onTipoChange"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Seleccionar...</option>
                                    <option v-for="t in arcaTipos" :key="t.codigo" :value="t.codigo">
                                        {{ t.codigo }} - {{ t.descripcion }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.arca_tipo_cbte" />
                            </div>
                            <div>
                                <InputLabel for="arca_numero" value="Numero" />
                                <TextInput id="arca_numero" v-model="form.arca_numero" type="number" min="1" class="mt-1 block w-full" />
                                <InputError :message="form.errors.arca_numero" />
                            </div>
                            <div>
                                <InputLabel for="arca_cae" value="CAE" />
                                <TextInput id="arca_cae" v-model="form.arca_cae" type="text" class="mt-1 block w-full" />
                                <InputError :message="form.errors.arca_cae" />
                            </div>
                            <div>
                                <InputLabel for="arca_cae_vto" value="CAE vencimiento" />
                                <TextInput id="arca_cae_vto" v-model="form.arca_cae_vto" type="date" class="mt-1 block w-full" />
                                <InputError :message="form.errors.arca_cae_vto" />
                            </div>
                        </div>
                    </div>

                    <div v-if="discrimina" class="border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Importes discriminados</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <InputLabel for="subtotal" value="Subtotal (neto gravado)" />
                                <TextInput id="subtotal" v-model="form.subtotal" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="0.00" />
                                <InputError :message="form.errors.subtotal" />
                            </div>
                            <div>
                                <InputLabel for="iva" value="IVA" />
                                <TextInput id="iva" v-model="form.iva" type="number" min="0" step="0.01" class="mt-1 block w-full" placeholder="0.00" />
                                <InputError :message="form.errors.iva" />
                            </div>
                            <div>
                                <InputLabel for="total" value="Total" />
                                <TextInput id="total" v-model="form.total" type="number" min="0.01" step="0.01" class="mt-1 block w-full" placeholder="0.00" />
                                <InputError :message="form.errors.total" />
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="sm:col-span-2 sm:col-start-3">
                            <InputLabel for="total" value="Total" />
                            <TextInput id="total" v-model="form.total" type="number" min="0.01" step="0.01" class="mt-1 block w-full" placeholder="0.00" />
                            <InputError :message="form.errors.total" />
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <label class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                v-model="form.disponible_para_hoja_ruta"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-700">Disponible para hoja de ruta (reparto)</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500">Si esta desmarcado, la factura solo generara el movimiento en cuenta corriente pero no estara disponible para asignar a una hoja de ruta.</p>
                        <InputError :message="form.errors.disponible_para_hoja_ruta" />
                    </div>

                    <div>
                        <InputLabel for="observacion" value="Observacion" />
                        <textarea
                            id="observacion"
                            v-model="form.observacion"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            rows="2"
                            placeholder="Motivo de la carga manual (opcional)"
                        ></textarea>
                        <InputError :message="form.errors.observacion" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                        <Link :href="route('facturacion.manifiestos.index')"><SecondaryButton type="button">Cancelar</SecondaryButton></Link>
                        <PrimaryButton :disabled="form.processing">Cargar factura</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
