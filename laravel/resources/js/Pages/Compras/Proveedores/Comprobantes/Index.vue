<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    proveedores: Array,
    comprobantes: Object,
});

const form = useForm({
    tercero_cuenta_id: '',
    tipo: 'factura',
    numero: '',
    moneda: 'ARS',
    subtotal: '',
    iva_total: '',
    tributos_total: '',
    fecha_emision: new Date().toISOString().slice(0, 10),
    fecha_vencimiento: '',
    observacion: '',
});

const submit = () => form.post(route('compras.proveedores.comprobantes.store'), { preserveScroll: true });
</script>

<template>
    <AppLayout title="Compras / Proveedores / Comprobantes">
        <Head title="Compras / Proveedores / Comprobantes" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compras / Proveedores / Comprobantes</h2>
                <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.ctacte.index')">Cta. cte. proveedores</Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nuevo comprobante proveedor</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-4" @submit.prevent="submit">
                    <div class="sm:col-span-2">
                        <InputLabel value="Proveedor" />
                        <select v-model="form.tercero_cuenta_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">(seleccionar)</option>
                            <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.tercero?.razon_social || p.nombre_cuenta || ('#' + p.id) }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.tercero_cuenta_id" />
                    </div>
                    <div><InputLabel value="Tipo" /><TextInput v-model="form.tipo" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.tipo" /></div>
                    <div><InputLabel value="Numero" /><TextInput v-model="form.numero" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.numero" /></div>
                    <div><InputLabel value="Moneda" /><select v-model="form.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-2" :message="form.errors.moneda" /></div>
                    <div><InputLabel value="Subtotal" /><TextInput v-model="form.subtotal" type="number" min="0" step="0.01" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.subtotal" /></div>
                    <div><InputLabel value="IVA" /><TextInput v-model="form.iva_total" type="number" min="0" step="0.01" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.iva_total" /></div>
                    <div><InputLabel value="Tributos" /><TextInput v-model="form.tributos_total" type="number" min="0" step="0.01" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.tributos_total" /></div>
                    <div><InputLabel value="Fecha emision" /><TextInput v-model="form.fecha_emision" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.fecha_emision" /></div>
                    <div><InputLabel value="Fecha vencimiento" /><TextInput v-model="form.fecha_vencimiento" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.fecha_vencimiento" /></div>
                    <div class="sm:col-span-3"><InputLabel value="Observacion" /><TextInput v-model="form.observacion" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.observacion" /></div>
                    <div class="sm:col-span-4 flex justify-end"><PrimaryButton :disabled="form.processing">Guardar</PrimaryButton></div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Comprobantes cargados</h3></div>
                <div class="space-y-4 p-4 sm:hidden">
                    <div v-for="c in comprobantes.data" :key="c.id" class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ c.cuenta?.tercero?.razon_social || '-' }}</div>
                                <div class="text-xs text-gray-500">{{ String(c.fecha_emision || '').slice(0,10) }} · {{ c.tipo }}</div>
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ c.moneda }} {{ c.total }}</div>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-3 text-sm">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Numero</div>
                                <div class="font-medium text-gray-900">{{ c.numero || '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hidden sm:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numero</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200"><tr v-for="c in comprobantes.data" :key="c.id"><td class="px-6 py-4 text-sm text-gray-700">{{ String(c.fecha_emision || '').slice(0,10) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.cuenta?.tercero?.razon_social || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.tipo }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.numero || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ c.moneda }} {{ c.total }}</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
