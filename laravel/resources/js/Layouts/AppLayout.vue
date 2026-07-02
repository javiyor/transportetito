<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const logout = () => {
    router.post(route('logout'));
};

const switchEmpresa = (empresaId) => {
    router.post(route('admin.current-empresa.update'), { empresa_id: empresaId }, { preserveScroll: true });
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white border-b border-gray-100">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationMark class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Inicio
                                </NavLink>

                                <NavLink :href="route('operacion.manifiestos.index')" :active="route().current('operacion.manifiestos.*')">
                                    Control pedidos
                                </NavLink>

                                <NavLink
                                    v-if="($page.props.tt?.roles || []).some((r) => ['facturacion', 'admin'].includes(r))"
                                    :href="route('facturacion.manifiestos.index')"
                                    :active="route().current('facturacion.*') || route().current('operacion.comprobantes.*')"
                                >
                                    Facturacion
                                </NavLink>
                                <NavLink
                                    v-if="($page.props.tt?.roles || []).some((r) => ['facturacion', 'admin'].includes(r))"
                                    :href="route('facturacion.importar.index')"
                                    :active="route().current('facturacion.importar.*')"
                                >
                                    Importar
                                </NavLink>

                                <NavLink
                                    v-if="($page.props.tt?.roles || []).includes('operaciones')"
                                    :href="route('operacion.repartos.hojas.index')"
                                    :active="route().current('operacion.repartos.*')"
                                >
                                    Repartos
                                </NavLink>

                                <NavLink
                                    v-if="($page.props.tt?.roles || []).some((r) => ['operaciones', 'facturacion', 'admin'].includes(r))"
                                    :href="route('operacion.fletes.index')"
                                    :active="route().current('operacion.fletes.*')"
                                >
                                    Fletes
                                </NavLink>

                                <NavLink
                                    v-if="($page.props.tt?.roles || []).includes('chofer')"
                                    :href="route('repartidor.index')"
                                    :active="route().current('repartidor.*')"
                                >
                                    Repartidor
                                </NavLink>

                                <NavLink
                                    v-if="($page.props.tt?.roles || []).some((r) => ['cobranzas', 'cobranzas_admin', 'cobrador'].includes(r))"
                                    :href="route('cobranzas.pre-recibos.index')"
                                    :active="route().current('cobranzas.*') && !route().current('cobranzas.cierre.*')"
                                >
                                    Cobranzas
                                </NavLink>

                                <NavLink
                                    v-if="($page.props.tt?.roles || []).some((r) => ['cobranzas', 'cobranzas_admin', 'cobrador'].includes(r))"
                                    :href="route('cobranzas.cierre.index')"
                                    :active="route().current('cobranzas.cierre.*')"
                                >
                                    Cierre
                                </NavLink>

                                <NavLink
                                    v-if="($page.props.tt?.roles || []).includes('admin')"
                                    :href="route('compras.proveedores.comprobantes.index')"
                                    :active="route().current('compras.*')"
                                >
                                    Compras
                                </NavLink>

                                <div v-if="($page.props.tt?.roles || []).includes('admin')" class="hidden sm:flex sm:items-center sm:ms-3">
                                    <Dropdown align="left" width="56">
                                        <template #trigger>
                                            <button type="button" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out" :class="route().current('admin.*') ? 'border-indigo-400 text-gray-900 focus:outline-none focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300'">
                                                Configuracion
                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </template>

                                        <template #content>
                                            <DropdownLink :href="route('admin.users.index')">Usuarios</DropdownLink>
                                            <DropdownLink :href="route('admin.empresas.index')">Empresas</DropdownLink>
                                            <DropdownLink :href="route('admin.depositos.index')">Depositos</DropdownLink>
                                            <DropdownLink :href="route('admin.terceros.index')">Clientes/Proveedores</DropdownLink>
                                            <DropdownLink :href="route('admin.cheques.index')">Cheques</DropdownLink>
                                            <DropdownLink :href="route('admin.tarifas.index')">Tarifas</DropdownLink>
                                            <DropdownLink :href="route('admin.cotizaciones.index')">Cotizaciones</DropdownLink>
                                            <DropdownLink :href="route('admin.vehiculos.index')">Vehiculos</DropdownLink>
                                            <DropdownLink :href="route('admin.reportes.seguro')">Informe seguro</DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <!-- Settings Dropdown -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                            <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </button>

                                        <span v-else class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-gray-400">
                                            Cuenta
                                        </div>

                                        <DropdownLink :href="route('profile.show')">
                                            Perfil
                                        </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            API Tokens
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Cerrar sesion
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                <svg
                                    class="size-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Inicio
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('operacion.manifiestos.index')" :active="route().current('operacion.manifiestos.*')">
                            Control pedidos
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).some((r) => ['facturacion', 'admin'].includes(r))"
                            :href="route('facturacion.manifiestos.index')"
                            :active="route().current('facturacion.*') || route().current('operacion.comprobantes.*')"
                        >
                            Facturacion
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).some((r) => ['facturacion', 'admin'].includes(r))"
                            :href="route('facturacion.importar.index')"
                            :active="route().current('facturacion.importar.*')"
                        >
                            Importar
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('operaciones')"
                            :href="route('operacion.repartos.hojas.index')"
                            :active="route().current('operacion.repartos.*')"
                        >
                            Repartos
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).some((r) => ['operaciones', 'facturacion', 'admin'].includes(r))"
                            :href="route('operacion.fletes.index')"
                            :active="route().current('operacion.fletes.*')"
                        >
                            Fletes
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('chofer')"
                            :href="route('repartidor.index')"
                            :active="route().current('repartidor.*')"
                        >
                            Repartidor
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).some((r) => ['cobranzas', 'cobranzas_admin', 'cobrador'].includes(r))"
                            :href="route('cobranzas.pre-recibos.index')"
                            :active="route().current('cobranzas.*') && !route().current('cobranzas.cierre.*')"
                        >
                            Cobranzas
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).some((r) => ['cobranzas', 'cobranzas_admin', 'cobrador'].includes(r))"
                            :href="route('cobranzas.cierre.index')"
                            :active="route().current('cobranzas.cierre.*')"
                        >
                            Cierre
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('compras.proveedores.comprobantes.index')"
                            :active="route().current('compras.*')"
                        >
                            Compras
                        </ResponsiveNavLink>

                        <div v-if="($page.props.tt?.roles || []).includes('admin')" class="px-4 pt-3 text-xs uppercase tracking-wider text-gray-400">
                            Configuracion
                        </div>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('admin.users.index')"
                            :active="route().current('admin.users.*')"
                        >
                            Usuarios
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('admin.empresas.index')"
                            :active="route().current('admin.empresas.*')"
                        >
                            Empresas
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('admin.depositos.index')"
                            :active="route().current('admin.depositos.*')"
                        >
                            Depositos
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('admin.terceros.index')"
                            :active="route().current('admin.terceros.*')"
                        >
                            Clientes/Proveedores
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('admin.cheques.index')"
                            :active="route().current('admin.cheques.*')"
                        >
                            Cheques
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('admin.tarifas.index')"
                            :active="route().current('admin.tarifas.*')"
                        >
                            Tarifas
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('admin.cotizaciones.index')"
                            :active="route().current('admin.cotizaciones.*')"
                        >
                            Cotizaciones
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('admin.vehiculos.index')"
                            :active="route().current('admin.vehiculos.*')"
                        >
                            Vehiculos
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="($page.props.tt?.roles || []).includes('admin')"
                            :href="route('admin.reportes.seguro')"
                            :active="route().current('admin.reportes.seguro')"
                        >
                            Informe seguro
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>

                            <div>
                                <div class="font-medium text-base text-gray-800">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-gray-500">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <div v-if="($page.props.tt?.roles || []).includes('admin')" class="px-4 pb-2">
                                <div class="text-xs text-gray-400">Empresa activa</div>
                                <select
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    :value="$page.props.tt?.currentEmpresa?.id || ''"
                                    @change="switchEmpresa(parseInt($event.target.value, 10))"
                                >
                                    <option v-for="e in ($page.props.tt?.empresasDisponibles || [])" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                                </select>
                            </div>

                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Perfil
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
                                API Tokens
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    Cerrar sesion
                                </ResponsiveNavLink>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>

            <div v-if="($page.props.tt?.roles || []).includes('admin') && $page.props.tt?.empresasDisponibles?.length" class="hidden sm:block bg-white border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3 h-10">
                        <span class="text-xs text-gray-500 shrink-0">Empresa:</span>
                        <select
                            class="block w-64 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            :value="$page.props.tt?.currentEmpresa?.id || ''"
                            @change="switchEmpresa(parseInt($event.target.value, 10))"
                        >
                            <option v-for="e in ($page.props.tt?.empresasDisponibles || [])" :key="e.id" :value="e.id">{{ e.razon_social }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
