<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    empresa: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head :title="empresa?.razon_social || 'Transporte'" />

    <div class="min-h-screen bg-[radial-gradient(1200px_600px_at_20%_-10%,#fde68a33,transparent),radial-gradient(900px_500px_at_110%_20%,#bfdbfe66,transparent),linear-gradient(to_bottom,#f8fafc,#ffffff)] text-gray-900">
        <div class="max-w-6xl mx-auto px-6 py-10">
            <header class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-amber-100 ring-1 ring-amber-200 flex items-center justify-center">
                        <div class="size-4 rounded bg-amber-600" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold tracking-wide">{{ empresa?.razon_social || 'Transporte' }}</div>
                        <div class="text-xs text-gray-600" v-if="empresa?.cuit">CUIT {{ empresa.cuit }} <span v-if="empresa.condicion_iva">· {{ empresa.condicion_iva }}</span></div>
                    </div>
                </div>

                <nav v-if="canLogin" class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-gray-800"
                    >
                        Entrar al sistema
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm hover:bg-gray-800"
                        >
                            Ingresar
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="px-4 py-2 rounded-lg bg-white text-gray-900 text-sm border border-gray-200 hover:bg-gray-50"
                        >
                            Registrarse
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="mt-10 grid grid-cols-1 lg:grid-cols-12 gap-6">
                <section class="lg:col-span-7 bg-white/80 backdrop-blur rounded-2xl border border-gray-200 shadow-sm p-7">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Presentacion</div>
                    <h1 class="mt-2 text-3xl font-semibold leading-tight">Transporte y logistica</h1>
                    <p class="mt-4 text-sm text-gray-700 leading-relaxed">
                        Datos publicos de la empresa cargada en el sistema (la primera creada). Para gestionar manifiestos, pedidos, repartos y cobranzas, ingresa al sistema.
                    </p>

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-xs text-gray-500">Razon social</div>
                            <div class="mt-1 text-sm font-medium text-gray-900">{{ empresa?.razon_social || '-' }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-xs text-gray-500">CUIT</div>
                            <div class="mt-1 text-sm font-medium text-gray-900">{{ empresa?.cuit || '-' }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-xs text-gray-500">Condicion IVA</div>
                            <div class="mt-1 text-sm font-medium text-gray-900">{{ empresa?.condicion_iva || '-' }}</div>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-xs text-gray-500">Depositos</div>
                            <div class="mt-1 text-sm font-medium text-gray-900">{{ (empresa?.depositos || []).length }}</div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5">
                        <div class="text-xs uppercase tracking-wider text-gray-500">Contacto</div>
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div class="text-gray-700"><span class="text-gray-500">Telefono:</span> {{ empresa?.telefono || '-' }}</div>
                            <div class="text-gray-700"><span class="text-gray-500">WhatsApp:</span> {{ empresa?.whatsapp || '-' }}</div>
                            <div class="text-gray-700"><span class="text-gray-500">Email:</span> <a v-if="empresa?.email" class="text-indigo-700 hover:text-indigo-900" :href="`mailto:${empresa.email}`">{{ empresa.email }}</a><span v-else>-</span></div>
                            <div class="text-gray-700"><span class="text-gray-500">Web:</span> <a v-if="empresa?.sitio_web" class="text-indigo-700 hover:text-indigo-900" :href="empresa.sitio_web" target="_blank" rel="noopener">{{ empresa.sitio_web }}</a><span v-else>-</span></div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a v-if="empresa?.instagram_url" class="px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-sm hover:bg-gray-100" :href="empresa.instagram_url" target="_blank" rel="noopener">Instagram</a>
                            <a v-if="empresa?.facebook_url" class="px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-sm hover:bg-gray-100" :href="empresa.facebook_url" target="_blank" rel="noopener">Facebook</a>
                            <a v-if="empresa?.linkedin_url" class="px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-50 text-sm hover:bg-gray-100" :href="empresa.linkedin_url" target="_blank" rel="noopener">LinkedIn</a>
                        </div>
                    </div>
                </section>

                <section class="lg:col-span-5 bg-white/80 backdrop-blur rounded-2xl border border-gray-200 shadow-sm p-7">
                    <div class="text-xs uppercase tracking-wider text-gray-500">Depositos</div>
                    <h2 class="mt-2 text-lg font-semibold">Puntos operativos</h2>

                    <div class="mt-4 space-y-3">
                        <div v-for="d in (empresa?.depositos || [])" :key="d.id" class="rounded-xl border border-gray-200 bg-white p-4">
                            <div class="text-sm font-medium text-gray-900">{{ d.nombre }}</div>
                            <div class="mt-1 text-xs text-gray-600">{{ d.direccion || 'Direccion no informada' }}</div>
                        </div>

                        <div v-if="!(empresa?.depositos || []).length" class="rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-600">
                            Todavia no hay depositos cargados.
                        </div>
                    </div>
                </section>
            </main>

            <footer class="mt-10 text-xs text-gray-500">Sistema de gestion de transporte.</footer>
        </div>
    </div>
</template>
