<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';

const props = defineProps({
    pasivos: Array,
    totalPendiente: Number,
    bancos: Array,
});

const formatNum = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const showPagar = ref(false);
const pagarCuenta = ref(null);
const pagarForm = useForm({ importe: '', fecha: new Date().toISOString().slice(0,10), banco_id: '', observacion: '' });

const openPagar = (p) => {
    pagarCuenta.value = p;
    pagarForm.importe = p.saldo;
    pagarForm.fecha = new Date().toISOString().slice(0,10);
    pagarForm.banco_id = '';
    pagarForm.observacion = '';
    pagarForm.clearErrors();
    showPagar.value = true;
};

const submitPagar = () => {
    if (!pagarCuenta.value) return;
    pagarForm.post(route('finanzas.pasivos.pagar', pagarCuenta.value.id), {
        preserveScroll: true,
        onSuccess: () => { showPagar.value = false; },
    });
};
</script>

<template>
    <AppLayout title="Pasivos pendientes">
        <Head title="Pasivos pendientes" />
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pasivos pendientes de pago</h2>
                <Link :href="route('finanzas.egresos.index')" class="text-sm text-indigo-600 hover:text-indigo-800">Volver a Egresos</Link>
            </div>
        </template>
        <div class="max-w-7xl mx-auto py-4 sm:px-6 lg:px-8 space-y-3">
            <div class="bg-white shadow sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><div class="text-xs text-gray-500">Cuentas con saldo</div><div class="text-sm font-medium text-gray-900">{{ pasivos.length }}</div></div>
                <div><div class="text-xs text-gray-500">Total pendiente</div><div class="text-sm font-medium text-red-700">$ {{ formatNum(totalPendiente) }}</div></div>
            </div>
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-[11px]">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1.5 text-left font-medium text-gray-500 uppercase">Cuenta</th>
                                <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase">Debe</th>
                                <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase">Haber</th>
                                <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase">Saldo pendiente</th>
                                <th class="px-2 py-1.5 text-right font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in pasivos" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-2 py-1 whitespace-nowrap"><span class="font-mono text-gray-900">{{ p.codigo }}</span> <span class="text-gray-700">{{ p.nombre }}</span></td>
                                <td class="px-2 py-1 text-right font-mono text-gray-700">{{ formatNum(p.debe) }}</td>
                                <td class="px-2 py-1 text-right font-mono text-green-700">{{ formatNum(p.haber) }}</td>
                                <td class="px-2 py-1 text-right font-mono font-semibold text-red-700">{{ formatNum(p.saldo) }}</td>
                                <td class="px-2 py-1 text-right whitespace-nowrap">
                                    <Link :href="route('finanzas.libro-mayor', { cuenta_contable_id: p.id })" class="text-indigo-600 hover:text-indigo-800">Ver mayor</Link>
                                    <button @click="openPagar(p)" class="ml-2 text-xs bg-green-600 text-white px-2 py-0.5 rounded hover:bg-green-700">Pagar</button>
                                </td>
                            </tr>
                            <tr v-if="!pasivos.length"><td colspan="5" class="px-2 py-4 text-center text-xs text-gray-500">Sin pasivos pendientes.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-200 text-xs text-gray-500">Saldo = Haber - Debe. Solo cuentas <b>pasivo</b> con saldo &gt; 0.01. Detalle de movimientos en Libro Mayor.</div>
            </div>
        </div>

        <DialogModal :show="showPagar" max-width="lg" @close="showPagar = false">
            <template #title>Pagar pasivo {{ pagarCuenta?.codigo }} - {{ pagarCuenta?.nombre }}</template>
            <template #content>
                <div class="space-y-3">
                    <div><InputLabel value="Importe" /><TextInput v-model="pagarForm.importe" type="number" min="0.01" step="0.01" class="mt-1 block w-full" /><InputError class="mt-1" :message="pagarForm.errors.importe" /></div>
                    <div><InputLabel value="Fecha" /><TextInput v-model="pagarForm.fecha" type="date" class="mt-1 block w-full" /><InputError class="mt-1" :message="pagarForm.errors.fecha" /></div>
                    <div><InputLabel value="Banco (opcional)" /><select v-model="pagarForm.banco_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Sin banco (caja)</option><option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option></select><InputError class="mt-1" :message="pagarForm.errors.banco_id" /></div>
                    <div><InputLabel value="Observación" /><TextInput v-model="pagarForm.observacion" type="text" class="mt-1 block w-full" placeholder="Pago pasivo" /><InputError class="mt-1" :message="pagarForm.errors.observacion" /></div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showPagar = false">Cancelar</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="pagarForm.processing" @click="submitPagar">Confirmar pago</PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
