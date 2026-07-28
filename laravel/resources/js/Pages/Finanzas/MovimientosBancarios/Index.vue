<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';

const props = defineProps({
    movimientos: Object,
    bancos: Array,
    saldosPorBanco: Array,
    filtros: Object,
});

const gastoForm = useForm({
    banco_id: '',
    fecha: new Date().toISOString().slice(0, 10),
    concepto: '',
    importe: '',
    moneda: 'ARS',
});

const gastoDialog = ref(false);
const mostrarGasto = () => {
    gastoForm.reset();
    gastoForm.fecha = new Date().toISOString().slice(0, 10);
    gastoDialog.value = true;
};
const submitGasto = () => {
    gastoForm.post(route('finanzas.movimientos-bancarios.gasto'), {
        preserveScroll: true,
        onSuccess: () => { gastoDialog.value = false; },
    });
};

const tipoLabel = (t) => ({ ingreso: 'Ingreso', egreso: 'Egreso', gasto_bancario: 'Gasto bancario' }[t] || t);

const formatNum = (n) => Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 });
const formatFecha = (v) => v ? String(v).slice(0, 10) : '-';

const filtrar = () => {
    router.get(route('finanzas.movimientos-bancarios.index'), {
        banco_id: props.filtros.banco_id,
        tipo: props.filtros.tipo,
        desde: props.filtros.desde,
        hasta: props.filtros.hasta,
    }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Finanzas / Movimientos bancarios">
        <Head title="Finanzas / Movimientos bancarios" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Movimientos bancarios</h2>
                <div class="flex items-center gap-3">
                    <SecondaryButton class="!text-xs !px-3 !py-1.5" @click="mostrarGasto">+ Gasto bancario</SecondaryButton>
                    <Link class="text-sm text-indigo-600 hover:text-indigo-800" :href="route('finanzas.egresos.index')">Egresos</Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-4">
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                    <div>
                        <label class="text-xs text-gray-500">Banco</label>
                        <select v-model="filtros.banco_id" @change="filtrar" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todos</option>
                            <option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Tipo</label>
                        <select v-model="filtros.tipo" @change="filtrar" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Todos</option>
                            <option value="ingreso">Ingreso</option>
                            <option value="egreso">Egreso</option>
                            <option value="gasto_bancario">Gasto bancario</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Desde</label>
                        <TextInput v-model="filtros.desde" type="date" class="mt-1 block w-full text-sm" @change="filtrar" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Hasta</label>
                        <TextInput v-model="filtros.hasta" type="date" class="mt-1 block w-full text-sm" @change="filtrar" />
                    </div>
                </div>
            </div>

            <div v-if="saldosPorBanco?.length" class="bg-white shadow sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div v-for="s in saldosPorBanco" :key="s.banco_nombre">
                    <div class="text-xs text-gray-500">{{ s.banco_nombre }}</div>
                    <div class="text-sm font-semibold" :class="s.saldo >= 0 ? 'text-green-700' : 'text-red-700'">$ {{ formatNum(s.saldo) }}</div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Movimientos ({{ movimientos.total }})</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Banco</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Concepto</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Importe</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Contab.</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="m in movimientos.data" :key="m.id">
                                <td class="px-4 py-2 text-sm text-gray-700">{{ formatFecha(m.fecha) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ m.banco?.nombre || '-' }}</td>
                                <td class="px-4 py-2 text-sm" :class="m.tipo === 'gasto_bancario' ? 'text-red-600' : m.tipo === 'ingreso' ? 'text-green-700' : 'text-gray-700'">{{ tipoLabel(m.tipo) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ m.concepto }}</td>
                                <td class="px-4 py-2 text-sm text-right font-mono" :class="m.tipo === 'ingreso' ? 'text-green-700' : 'text-red-600'">{{ m.moneda }} {{ formatNum(m.importe) }}</td>
                                <td class="px-4 py-2 text-sm text-center">
                                    <span :class="m.contabilizado ? 'text-green-600' : 'text-gray-400'">{{ m.contabilizado ? 'Si' : 'No' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Gasto bancario dialog -->
        <div v-if="gastoDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="gastoDialog = false">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Registrar gasto bancario</h3>
                <form @submit.prevent="submitGasto" class="space-y-3">
                    <div>
                        <InputLabel value="Banco" />
                        <select v-model="gastoForm.banco_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">Seleccionar...</option>
                            <option v-for="b in bancos" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                        </select>
                        <InputError :message="gastoForm.errors.banco_id" />
                    </div>
                    <div>
                        <InputLabel value="Fecha" />
                        <TextInput v-model="gastoForm.fecha" type="date" class="mt-1 block w-full" />
                        <InputError :message="gastoForm.errors.fecha" />
                    </div>
                    <div>
                        <InputLabel value="Concepto" />
                        <TextInput v-model="gastoForm.concepto" type="text" class="mt-1 block w-full" placeholder="Comision, mantenimiento, etc." />
                        <InputError :message="gastoForm.errors.concepto" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Importe" />
                            <TextInput v-model="gastoForm.importe" type="number" min="0.01" step="0.01" class="mt-1 block w-full" />
                            <InputError :message="gastoForm.errors.importe" />
                        </div>
                        <div>
                            <InputLabel value="Moneda" />
                            <select v-model="gastoForm.moneda" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option>ARS</option><option>USD</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton type="button" @click="gastoDialog = false">Cancelar</SecondaryButton>
                        <PrimaryButton :disabled="gastoForm.processing">Guardar y contabilizar</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>