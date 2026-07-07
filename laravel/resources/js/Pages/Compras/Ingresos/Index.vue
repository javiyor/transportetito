<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { computed } from 'vue';

const props = defineProps({
    ingresos: Object,
    cuentasContables: Array,
    totales: Object,
});

const form = useForm({
    fecha: new Date().toISOString().slice(0, 10),
    cuenta_contable_id: '',
    medio: 'efectivo',
    detalle: {
        banco: '',
        numero: '',
        fecha_emision: '',
        fecha_cobro: '',
    },
    moneda: 'ARS',
    importe: '',
    referencia: '',
    observacion: '',
});

const esCheque = computed(() => form.medio === 'cheque');

const submit = () => form.post(route('compras.ingresos.store'), { preserveScroll: true });

const medioLabel = (m) => ({ efectivo: 'Efectivo', cheque: 'Cheque', transferencia: 'Transferencia' }[m] || m);

const detalleResumen = (g) => {
    if (g.medio !== 'cheque' || !g.detalle) return '';
    const d = g.detalle;
    return [d.banco, d.numero].filter(Boolean).join(' / ');
};
</script>

<template>
    <AppLayout title="Compras / Ingresos varios">
        <Head title="Compras / Ingresos varios" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compras / Ingresos varios</h2>
                <div class="flex items-center gap-3">
                    <a class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.ingresos.export')">Exportar CSV</a>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.gastos.index')">Gastos</Link>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('compras.proveedores.comprobantes.index')">Volver a compras</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><div class="text-xs text-gray-500">Registros</div><div class="text-sm font-medium text-gray-900">{{ totales?.cantidad || 0 }}</div></div>
                <div><div class="text-xs text-gray-500">Total estimado en ARS</div><div class="text-sm font-medium text-gray-900">{{ totales?.importe_total_ars || 0 }}</div></div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nuevo ingreso</h3>
                <form class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4" @submit.prevent="submit">
                    <div><InputLabel value="Fecha" /><TextInput v-model="form.fecha" type="date" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.fecha" /></div>
                    <div>
                        <InputLabel value="Categoria" />
                        <select v-model="form.cuenta_contable_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Seleccionar...</option>
                            <option v-for="c in cuentasContables" :key="c.id" :value="c.id">{{ c.codigo }} - {{ c.nombre }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.cuenta_contable_id" />
                    </div>
                    <div>
                        <InputLabel value="Medio" />
                        <select v-model="form.medio" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="efectivo">Efectivo</option>
                            <option value="cheque">Cheque</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.medio" />
                    </div>

                    <template v-if="esCheque">
                        <div>
                            <InputLabel value="Banco" />
                            <TextInput v-model="form.detalle.banco" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors['detalle.banco']" />
                        </div>
                        <div>
                            <InputLabel value="Nro. Cheque" />
                            <TextInput v-model="form.detalle.numero" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors['detalle.numero']" />
                        </div>
                        <div>
                            <InputLabel value="Fecha emision" />
                            <TextInput v-model="form.detalle.fecha_emision" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors['detalle.fecha_emision']" />
                        </div>
                        <div>
                            <InputLabel value="Fecha cobro" />
                            <TextInput v-model="form.detalle.fecha_cobro" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors['detalle.fecha_cobro']" />
                        </div>
                    </template>

                    <div><InputLabel value="Moneda" /><select v-model="form.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"><option>ARS</option><option>USD</option><option>EUR</option><option>BRL</option></select><InputError class="mt-2" :message="form.errors.moneda" /></div>
                    <div><InputLabel value="Importe" /><TextInput v-model="form.importe" type="number" min="0.01" step="0.01" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.importe" /></div>
                    <div><InputLabel value="Referencia" /><TextInput v-model="form.referencia" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.referencia" /></div>
                    <div class="sm:col-span-3"><InputLabel value="Observacion" /><TextInput v-model="form.observacion" type="text" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.observacion" /></div>
                    <div class="sm:col-span-3 flex justify-end"><PrimaryButton :disabled="form.processing">Guardar</PrimaryButton></div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200"><h3 class="text-base font-semibold text-gray-900">Ingresos registrados</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalle</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referencia</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Obs.</th></tr></thead><tbody class="bg-white divide-y divide-gray-200"><tr v-for="g in ingresos.data" :key="g.id"><td class="px-6 py-4 text-sm text-gray-700">{{ String(g.fecha || '').slice(0,10) }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.cuenta_contable?.nombre || g.categoria }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ medioLabel(g.medio) }}</td><td class="px-6 py-4 text-sm text-gray-500">{{ detalleResumen(g) || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.referencia || '-' }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.moneda }} {{ g.importe }}</td><td class="px-6 py-4 text-sm text-gray-700">{{ g.observacion || '-' }}</td></tr></tbody></table></div>
            </div>
        </div>
    </AppLayout>
</template>
