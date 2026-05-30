<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    empresas: Array,
    empresaId: [Number, null],
    cuentas: Array,
});

const form = useForm({
    empresa_id: props.empresaId || props.empresas?.[0]?.id || null,
    numero_cliente: '',
    cuit: '',
    razon_social: '',
    nombre_cuenta: '',
    es_cliente: true,
    es_proveedor: false,
});

const submit = () => {
    form.post(route('admin.terceros.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('numero_cliente', 'cuit', 'razon_social', 'nombre_cuenta'),
    });
};

const changeEmpresa = (id) => {
    router.get(route('admin.terceros.index'), { empresa_id: id || null }, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <AppLayout title="Admin / Terceros">
        <Head title="Admin / Terceros" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Admin / Terceros</h2>
                <div class="w-72">
                    <select
                        class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        :value="empresaId || ''"
                        @change="changeEmpresa($event.target.value ? parseInt($event.target.value, 10) : null)"
                    >
                        <option value="">Todas las empresas</option>
                        <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Nueva cuenta (Numero de cliente)</h3>

                <form class="mt-4 grid grid-cols-1 sm:grid-cols-6 gap-4" @submit.prevent="submit">
                    <div class="sm:col-span-2">
                        <InputLabel value="Empresa" />
                        <select v-model="form.empresa_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            <option v-for="e in empresas" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.empresa_id" />
                    </div>

                    <div>
                        <InputLabel value="Numero de cliente" />
                        <TextInput v-model="form.numero_cliente" type="number" min="1" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.numero_cliente" />
                    </div>

                    <div>
                        <InputLabel value="CUIT" />
                        <TextInput v-model="form.cuit" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.cuit" />
                    </div>

                    <div class="sm:col-span-2">
                        <InputLabel value="Razon social" />
                        <TextInput v-model="form.razon_social" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.razon_social" />
                    </div>

                    <div class="sm:col-span-3">
                        <InputLabel value="Nombre cuenta (opcional)" />
                        <TextInput v-model="form.nombre_cuenta" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.nombre_cuenta" />
                    </div>

                    <div class="sm:col-span-3 flex items-center gap-6 pt-6">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="form.es_cliente" />
                            Cliente
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <Checkbox v-model:checked="form.es_proveedor" />
                            Proveedor
                        </label>
                    </div>

                    <div class="sm:col-span-6 flex justify-end">
                        <PrimaryButton :disabled="form.processing">Crear</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Cuentas</h3>
                    <p class="mt-1 text-sm text-gray-600">Cada cuenta es un Numero de cliente dentro de una empresa.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empresa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nro cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CUIT</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Razon social</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flags</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="c in cuentas" :key="c.id">
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.empresa?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900 font-mono">{{ c.numero_cliente }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 font-mono">{{ c.tercero?.cuit || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ c.tercero?.razon_social || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ c.nombre_cuenta || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <span v-if="c.es_cliente" class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Cliente</span>
                                    <span v-if="c.es_proveedor" class="ms-2 inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">Proveedor</span>
                                    <span v-if="!c.es_cliente && !c.es_proveedor" class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">Sin flags</span>
                                </td>
                            </tr>
                            <tr v-if="!cuentas.length">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">Sin cuentas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
