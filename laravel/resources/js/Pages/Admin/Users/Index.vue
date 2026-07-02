<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    roles: Array,
    users: Object,
    empresas: Array,
});

const page = usePage();

const createForm = useForm({
    name: '',
    email: '',
});

const tempPassword = computed(() => page.props.tt?.flash?.tempPassword || null);
const tempPasswordEmail = computed(() => page.props.tt?.flash?.tempPasswordEmail || null);
const showingTempPassword = ref(false);

watch(tempPassword, (value) => {
    if (value) {
        showingTempPassword.value = true;
    }
});

const confirmAction = ref({
    show: false,
    title: '',
    message: '',
    action: null,
});

const openConfirm = (title, message, action) => {
    confirmAction.value = { show: true, title, message, action };
};

const closeConfirm = () => {
    confirmAction.value = { show: false, title: '', message: '', action: null };
};

const submitCreate = () => {
    createForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
        },
    });
};

const roleForms = ref({});

const getRoleForm = (user) => {
    if (!roleForms.value[user.id]) {
        roleForms.value[user.id] = useForm({
            roles: [...user.roles],
        });
    }
    return roleForms.value[user.id];
};

const saveRoles = (user) => {
    const form = getRoleForm(user);
    form.put(route('admin.users.roles.update', user.id), {
        preserveScroll: true,
    });
};

const resetPassword = (user) => {
    openConfirm(
        'Resetear password',
        `Se genera un password temporal para ${user.email}. Se mostrara una sola vez.`,
        () => {
            closeConfirm();
            useForm({}).post(route('admin.users.password.reset', user.id), { preserveScroll: true });
        },
    );
};

const blockUser = (user) => {
    openConfirm(
        'Bloquear usuario',
        `Se bloqueara ${user.email} y se desloguea en el proximo request.`,
        () => {
            closeConfirm();
            useForm({}).post(route('admin.users.block', user.id), { preserveScroll: true });
        },
    );
};

const unblockUser = (user) => {
    useForm({}).post(route('admin.users.unblock', user.id), { preserveScroll: true });
};

const editNameForm = useForm({ name: '' });
const editandoUsuario = ref(null);

const abrirEditarNombre = (user) => {
    editNameForm.name = user.name;
    editandoUsuario.value = user.id;
};

const cerrarEditarNombre = () => {
    editandoUsuario.value = null;
    editNameForm.reset();
};

const guardarNombre = () => {
    editNameForm.put(route('admin.users.update', editandoUsuario.value), {
        preserveScroll: true,
        onSuccess: () => cerrarEditarNombre(),
    });
};

const editEmpresasForm = useForm({ empresa_ids: [] });
const editandoEmpresas = ref(null);

const abrirEditarEmpresas = (user) => {
    editEmpresasForm.empresa_ids = [...(user.empresa_ids || [])];
    editandoEmpresas.value = user.id;
};

const cerrarEditarEmpresas = () => {
    editandoEmpresas.value = null;
    editEmpresasForm.reset();
};

const guardarEmpresas = () => {
    editEmpresasForm.put(route('admin.users.empresas.update', editandoEmpresas.value), {
        preserveScroll: true,
        onSuccess: () => cerrarEditarEmpresas(),
    });
};

const toggleEmpresaId = (id) => {
    const idx = editEmpresasForm.empresa_ids.indexOf(id);
    if (idx >= 0) {
        editEmpresasForm.empresa_ids.splice(idx, 1);
    } else {
        editEmpresasForm.empresa_ids.push(id);
    }
};

const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

const editHorariosForm = useForm({ horarios: [] });
const editandoHorarios = ref(null);

const abrirEditarHorarios = (user) => {
    const horarios = user.horarios || [];
    editHorariosForm.horarios = DIAS.map((_, i) => {
        const existente = horarios.find((h) => h.dia_semana === i);
        return {
            dia_semana: i,
            hora_desde: existente?.hora_desde || null,
            hora_hasta: existente?.hora_hasta || null,
        };
    });
    editandoHorarios.value = user.id;
};

const cerrarEditarHorarios = () => {
    editandoHorarios.value = null;
    editHorariosForm.reset();
};

const guardarHorarios = () => {
    editHorariosForm.put(route('admin.users.horarios.update', editandoHorarios.value), {
        preserveScroll: true,
        onSuccess: () => cerrarEditarHorarios(),
    });
};

const canSeeAdminNav = computed(() => (page.props.tt?.roles || []).includes('admin'));
</script>

<template>
    <AppLayout title="Admin / Usuarios">
        <Head title="Admin / Usuarios" />

        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Admin / Usuarios
                </h2>
                <div v-if="!canSeeAdminNav" class="text-sm text-gray-500">
                    (Solo admins)
                </div>
            </div>
        </template>

        <div v-if="page.props.tt?.flash?.success" class="max-w-7xl mx-auto mt-4 px-4 sm:px-6 lg:px-8">
            <div class="bg-green-100 border border-green-200 text-green-800 px-4 py-2 rounded text-sm">{{ page.props.tt.flash.success }}</div>
        </div>

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900">Crear usuario</h3>

                <form class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel for="name" value="Nombre" />
                        <TextInput id="name" v-model="createForm.name" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="createForm.email" type="email" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="createForm.errors.email" />
                    </div>

                    <div class="flex items-end">
                        <PrimaryButton :disabled="createForm.processing">Crear</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="mt-6 bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Usuarios</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Roles multiples, bloqueo y reset password.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roles</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="user in users.data" :key="user.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                                    <div class="text-sm text-gray-500">{{ user.email }}</div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div>
                                        <span v-if="user.blocked_at" class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Bloqueado</span>
                                        <span v-else class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Activo</span>
                                    </div>
                                    <div class="mt-2 flex gap-2">
                                        <span v-if="user.email_verified_at" class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">Verificado</span>
                                        <span v-else class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">No verificado</span>

                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <div class="grid grid-cols-1 gap-2">
                                        <div v-for="role in roles" :key="role" class="flex items-center gap-2">
                                            <Checkbox v-model:checked="getRoleForm(user).roles" :value="role" />
                                            <span class="font-mono text-xs">{{ role }}</span>
                                        </div>
                                        <InputError class="mt-2" :message="getRoleForm(user).errors.roles" />
                                    </div>
                                    <div class="mt-3">
                                        <PrimaryButton class="text-xs" :disabled="getRoleForm(user).processing" @click.prevent="saveRoles(user)">
                                            Guardar roles
                                        </PrimaryButton>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <div class="flex justify-end gap-2">
                                        <SecondaryButton class="text-xs" @click.prevent="abrirEditarNombre(user)">
                                            Editar
                                        </SecondaryButton>
                                        <SecondaryButton class="text-xs" @click.prevent="abrirEditarEmpresas(user)">
                                            Empresas
                                        </SecondaryButton>
                                        <SecondaryButton class="text-xs" @click.prevent="abrirEditarHorarios(user)">
                                            Horarios
                                        </SecondaryButton>
                                        <SecondaryButton class="text-xs" @click.prevent="resetPassword(user)">
                                            Reset password
                                        </SecondaryButton>
                                        <DangerButton v-if="!user.blocked_at" class="text-xs" @click.prevent="blockUser(user)">
                                            Bloquear
                                        </DangerButton>
                                        <SecondaryButton v-else class="text-xs" @click.prevent="unblockUser(user)">
                                            Desbloquear
                                        </SecondaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="confirmAction.show" @close="closeConfirm">
            <template #title>
                {{ confirmAction.title }}
            </template>
            <template #content>
                {{ confirmAction.message }}
            </template>
            <template #footer>
                <SecondaryButton @click="closeConfirm">Cancelar</SecondaryButton>
                <DangerButton class="ms-3" @click="confirmAction.action">Confirmar</DangerButton>
            </template>
        </ConfirmationModal>

        <DialogModal :show="!!editandoHorarios" @close="cerrarEditarHorarios" max-width="2xl">
            <template #title>
                Horarios de acceso para usuario
            </template>
            <template #content>
                <p class="text-sm text-gray-500 mb-4">Dejar ambos campos vacíos para deshabilitar el acceso ese día.</p>
                <div class="space-y-3">
                    <div v-for="(h, i) in editHorariosForm.horarios" :key="i" class="flex items-center gap-4">
                        <span class="w-24 text-sm font-medium text-gray-700">{{ DIAS[i] }}</span>
                        <label class="text-xs text-gray-500">Desde</label>
                        <input v-model="h.hora_desde" type="time" class="block w-32 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        <label class="text-xs text-gray-500">Hasta</label>
                        <input v-model="h.hora_hasta" type="time" class="block w-32 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                </div>
                <InputError class="mt-2" :message="editHorariosForm.errors.horarios" />
            </template>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <SecondaryButton @click="cerrarEditarHorarios">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="editHorariosForm.processing" @click="guardarHorarios">Guardar</PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <DialogModal :show="!!editandoEmpresas" @close="cerrarEditarEmpresas">
            <template #title>
                Asignar empresas a usuario
            </template>
            <template #content>
                <div class="space-y-2 max-h-80 overflow-y-auto">
                    <div v-for="e in empresas" :key="e.id" class="flex items-center gap-2">
                        <Checkbox :checked="editEmpresasForm.empresa_ids.includes(e.id)" @click="toggleEmpresaId(e.id)" />
                        <span class="text-sm text-gray-700">{{ e.razon_social }}</span>
                    </div>
                    <div v-if="!empresas.length" class="text-sm text-gray-500">No hay empresas disponibles.</div>
                </div>
                <InputError class="mt-2" :message="editEmpresasForm.errors.empresa_ids" />
            </template>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <SecondaryButton @click="cerrarEditarEmpresas">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="editEmpresasForm.processing" @click="guardarEmpresas">Guardar</PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <DialogModal :show="!!editandoUsuario" @close="cerrarEditarNombre">
            <template #title>
                Editar nombre de usuario
            </template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel value="Nombre" />
                        <TextInput v-model="editNameForm.name" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editNameForm.errors.name" />
                    </div>
                </div>
            </template>
            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <SecondaryButton @click="cerrarEditarNombre">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="editNameForm.processing" @click="guardarNombre">Guardar</PrimaryButton>
                </div>
            </template>
        </DialogModal>

        <DialogModal :show="showingTempPassword" @close="showingTempPassword = false">
            <template #title>
                Password temporal
            </template>
            <template #content>
                <div class="text-sm text-gray-700">
                    <p>
                        Email: <span class="font-mono">{{ tempPasswordEmail }}</span>
                    </p>
                    <p class="mt-2">
                        Contrasena: <span class="font-mono">{{ tempPassword }}</span>
                    </p>
                    <p class="mt-3 text-xs text-gray-500">
                        Se muestra una sola vez. El usuario debe cambiarlo al ingresar.
                    </p>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="showingTempPassword = false">Cerrar</SecondaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
