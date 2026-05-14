<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    manifiesto: Object,
});

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
</script>

<template>
    <AppLayout :title="`Operacion / Manifiesto #${manifiesto.id}`">
        <Head :title="`Operacion / Manifiesto #${manifiesto.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Manifiesto #{{ manifiesto.id }}</h2>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ manifiesto.fecha }} · {{ manifiesto.empresa?.razon_social || '-' }} · {{ manifiesto.deposito?.nombre || '-' }}
                    </div>
                </div>
                <Link :href="route('operacion.manifiestos.index')">
                    <SecondaryButton>Volver</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
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

                <div class="mt-8 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remitente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatario</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paga</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bultos</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Palets</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CR</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="p in (manifiesto.pedidos || [])" :key="p.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ p.id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ p.remitente?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ p.remitente?.cuit || '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ p.destinatario?.razon_social || '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ p.destinatario?.cuit || '' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.paga }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.bultos }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.palets }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.valor_declarado }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ p.cr_importe || '-' }}</td>
                            </tr>

                            <tr v-if="!(manifiesto.pedidos || []).length">
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500">Todavia no hay pedidos cargados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
