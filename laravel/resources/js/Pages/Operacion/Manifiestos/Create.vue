<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    empresas: Array,
    depositos: Array,
    defaults: Object,
});

const form = useForm({
    empresa_id: props.empresas?.[0]?.id || null,
    deposito_id: null,
    fecha: props.defaults?.fecha || '',
    transporte: '',
    chofer: '',
    patente_camion: '',
    patente_acoplado: '',
    ciudad_origen: '',
    ciudad_destino: '',
    valor_asegurado: '',
    gastos_envio: '',
});

const depositosFiltrados = computed(() => {
    if (!form.empresa_id) return [];
    return (props.depositos || []).filter((d) => d.empresa_id === form.empresa_id);
});

watch(() => form.empresa_id, () => {
    const first = depositosFiltrados.value[0] || null;
    form.deposito_id = first ? first.id : null;
}, { immediate: true });

const submit = () => {
    form.post(route('operacion.manifiestos.store'));
};
</script>

<template>
    <AppLayout title="Operacion / Nuevo manifiesto">
        <Head title="Operacion / Nuevo manifiesto" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Operacion / Nuevo manifiesto</h2>
                <Link :href="route('operacion.manifiestos.index')">
                    <SecondaryButton>Volver</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="submit">
                    <div>
                        <InputLabel value="Empresa" />
                        <select v-model="form.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.empresa_id" />
                    </div>

                    <div>
                        <InputLabel value="Deposito" />
                        <select v-model="form.deposito_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option v-for="d in depositosFiltrados" :key="d.id" :value="d.id">{{ d.nombre }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.deposito_id" />
                    </div>

                    <div>
                        <InputLabel for="fecha" value="Fecha" />
                        <TextInput id="fecha" v-model="form.fecha" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.fecha" />
                    </div>

                    <div>
                        <InputLabel for="transporte" value="Transporte" />
                        <TextInput id="transporte" v-model="form.transporte" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.transporte" />
                    </div>

                    <div>
                        <InputLabel for="chofer" value="Chofer" />
                        <TextInput id="chofer" v-model="form.chofer" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.chofer" />
                    </div>

                    <div>
                        <InputLabel for="patente_camion" value="Patente camion" />
                        <TextInput id="patente_camion" v-model="form.patente_camion" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.patente_camion" />
                    </div>

                    <div>
                        <InputLabel for="patente_acoplado" value="Patente acoplado" />
                        <TextInput id="patente_acoplado" v-model="form.patente_acoplado" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.patente_acoplado" />
                    </div>

                    <div>
                        <InputLabel for="ciudad_origen" value="Ciudad origen" />
                        <TextInput id="ciudad_origen" v-model="form.ciudad_origen" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.ciudad_origen" />
                    </div>

                    <div>
                        <InputLabel for="ciudad_destino" value="Ciudad destino" />
                        <TextInput id="ciudad_destino" v-model="form.ciudad_destino" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.ciudad_destino" />
                    </div>

                    <div>
                        <InputLabel for="valor_asegurado" value="Valor asegurado" />
                        <TextInput id="valor_asegurado" v-model="form.valor_asegurado" type="number" step="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.valor_asegurado" />
                    </div>

                    <div>
                        <InputLabel for="gastos_envio" value="Gastos envio" />
                        <TextInput id="gastos_envio" v-model="form.gastos_envio" type="number" step="0.01" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.gastos_envio" />
                    </div>

                    <div class="sm:col-span-2 flex items-center justify-end gap-2 pt-2">
                        <Link :href="route('operacion.manifiestos.index')">
                            <SecondaryButton type="button">Cancelar</SecondaryButton>
                        </Link>
                        <PrimaryButton :disabled="form.processing">Crear</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
