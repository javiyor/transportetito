<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    cuentas: Array,
});

const form = useForm({
    tercero_cuenta_id: '',
    tercero_destino_id: '',
    origen: '',
    destino: '',
    items: [{ descripcion: '', cantidad: 1, tipo: 'bultos', valor_declarado: '' }],
    observacion: '',
});

const agregarItem = () => {
    form.items.push({ descripcion: '', cantidad: 1, tipo: 'bultos', valor_declarado: '' });
};

const quitarItem = (idx) => {
    if (form.items.length > 1) {
        form.items.splice(idx, 1);
    }
};

const submit = () => form.post(route('facturacion.cotizaciones.pedido.store'), { preserveScroll: true });
</script>

<template>
    <AppLayout title="Facturacion / Cotizaciones / Pedido">
        <Head title="Facturacion / Cotizaciones / Pedido" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cotizaciones / Pedido</h2>
                <div class="flex items-center gap-3">
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('facturacion.cotizaciones.pendientes')">Pendientes</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('facturacion.cotizaciones.consultas')">Consultas</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Nuevo pedido de cotizacion</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Remitente (Origen)" />
                            <select v-model="form.tercero_cuenta_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar...</option>
                                <option v-for="c in cuentas" :key="c.id" :value="c.id">{{ c.tercero?.razon_social || c.nombre_cuenta }} ({{ c.localidad || '-' }})</option>
                            </select>
                            <InputError :message="form.errors.tercero_cuenta_id" />
                        </div>
                        <div>
                            <InputLabel value="Destinatario (Destino)" />
                            <select v-model="form.tercero_destino_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Seleccionar...</option>
                                <option v-for="c in cuentas" :key="c.id" :value="c.id">{{ c.tercero?.razon_social || c.nombre_cuenta }} ({{ c.localidad || '-' }})</option>
                            </select>
                            <InputError :message="form.errors.tercero_destino_id" />
                        </div>
                        <div>
                            <InputLabel value="Origen (libre)" />
                            <TextInput v-model="form.origen" type="text" class="mt-1 block w-full" placeholder="Ciudad / direccion" />
                        </div>
                        <div>
                            <InputLabel value="Destino (libre)" />
                            <TextInput v-model="form.destino" type="text" class="mt-1 block w-full" placeholder="Ciudad / direccion" />
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-gray-900">Items</h4>
                            <SecondaryButton type="button" class="!text-xs !px-3 !py-1.5" @click="agregarItem">+ Agregar item</SecondaryButton>
                        </div>
                        <div v-for="(item, idx) in form.items" :key="idx" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end mb-2 p-2 border border-gray-100 rounded">
                            <div class="sm:col-span-2">
                                <InputLabel :value="'Descripcion ' + (idx + 1)" />
                                <TextInput v-model="item.descripcion" type="text" class="mt-1 block w-full text-sm" placeholder="Descripcion" />
                            </div>
                            <div>
                                <InputLabel value="Cantidad" />
                                <TextInput v-model="item.cantidad" type="number" min="0" step="1" class="mt-1 block w-full text-sm" />
                            </div>
                            <div>
                                <InputLabel value="Tipo" />
                                <select v-model="item.tipo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="bultos">Bultos</option>
                                    <option value="palets">Palets</option>
                                </select>
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <InputLabel value="Valor declarado" />
                                    <TextInput v-model="item.valor_declarado" type="number" min="0" step="0.01" class="mt-1 block w-full text-sm" />
                                </div>
                                <button v-if="form.items.length > 1" type="button" class="text-red-500 text-lg leading-none font-bold mb-1" @click="quitarItem(idx)">&times;</button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Observacion" />
                        <textarea v-model="form.observacion" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="2"></textarea>
                        <InputError :message="form.errors.observacion" />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">Guardar pedido</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>