<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    settings: Object,
});

const page = usePage();
const flashSuccess = computed(() => page.props.tt?.flash?.success || page.props.flash?.success || null);
const flashError = computed(() => page.props.tt?.flash?.error || page.props.flash?.error || null);

const form = useForm({
    scheme: props.settings?.scheme || 'smtp',
    host: props.settings?.host || '',
    port: props.settings?.port || 587,
    username: props.settings?.username || '',
    password: '',
    from_address: props.settings?.from_address || '',
    from_name: props.settings?.from_name || 'TransporteTito',
});

const testForm = useForm({ email: '' });

const guardar = () => {
    form.put(route('admin.correo.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });
};

const enviarPrueba = () => {
    testForm.post(route('admin.correo.test'), { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Configuración de correo">
        <Head title="Configuración de correo" />

        <template #header>
            <h2 class="font-semibold text-lg text-gray-800 leading-tight">Configuración de correo</h2>
        </template>

        <div class="max-w-3xl mx-auto py-4 sm:px-6 lg:px-8 space-y-4">
            <div v-if="flashSuccess" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ flashSuccess }}</div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ flashError }}</div>

            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                Esta configuración SMTP se usa para los correos salientes de la aplicación. La contraseña se guarda cifrada y nunca se vuelve a mostrar.
            </div>

            <div class="bg-white shadow sm:rounded-lg p-5">
                <h3 class="text-sm font-semibold text-gray-900">Servidor SMTP</h3>
                <form class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="guardar">
                    <div class="sm:col-span-2">
                        <InputLabel for="mail_host" value="Servidor SMTP" />
                        <TextInput id="mail_host" v-model="form.host" type="text" class="mt-1 block w-full" placeholder="smtp.gmail.com" required autocomplete="off" />
                        <InputError :message="form.errors.host" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="mail_scheme" value="Seguridad" />
                        <select id="mail_scheme" v-model="form.scheme" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="smtp">STARTTLS (puerto 587)</option>
                            <option value="smtps">SSL/TLS (puerto 465)</option>
                        </select>
                        <InputError :message="form.errors.scheme" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="mail_port" value="Puerto" />
                        <TextInput id="mail_port" v-model="form.port" type="number" min="1" max="65535" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.port" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="mail_username" value="Usuario SMTP" />
                        <TextInput id="mail_username" v-model="form.username" type="text" class="mt-1 block w-full" autocomplete="username" />
                        <InputError :message="form.errors.username" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="mail_password" value="Contraseña SMTP" />
                        <TextInput id="mail_password" v-model="form.password" type="password" class="mt-1 block w-full" autocomplete="new-password" :placeholder="settings?.password_configured ? 'Guardada; vacío conserva la actual' : 'Ingresá la contraseña SMTP'" />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="mail_from_address" value="Dirección remitente" />
                        <TextInput id="mail_from_address" v-model="form.from_address" type="email" class="mt-1 block w-full" placeholder="facturacion@tu-dominio.com" required autocomplete="email" />
                        <InputError :message="form.errors.from_address" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="mail_from_name" value="Nombre remitente" />
                        <TextInput id="mail_from_name" v-model="form.from_name" type="text" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.from_name" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2 flex justify-end">
                        <PrimaryButton :disabled="form.processing">{{ form.processing ? 'Guardando...' : 'Guardar configuración' }}</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-5">
                <h3 class="text-sm font-semibold text-gray-900">Probar el envío</h3>
                <p class="mt-1 text-xs text-gray-500">Primero guardá la configuración; después enviá una prueba a una dirección que puedas revisar.</p>
                <form class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="enviarPrueba">
                    <div class="flex-1">
                        <InputLabel for="test_email" value="Enviar prueba a" />
                        <TextInput id="test_email" v-model="testForm.email" type="email" class="mt-1 block w-full" required autocomplete="email" />
                        <InputError :message="testForm.errors.email" class="mt-1" />
                    </div>
                    <SecondaryButton type="submit" :disabled="testForm.processing || !settings?.host">
                        {{ testForm.processing ? 'Enviando...' : 'Enviar prueba' }}
                    </SecondaryButton>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
