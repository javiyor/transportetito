<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    empresa: Object,
    homologacion: Object,
    produccion: Object,
    diagnostic: Array,
    errors: Object,
});

const page = usePage();
const flashSuccess = computed(() => page.props.tt?.flash?.success);
const flashError = computed(() => page.props.tt?.flash?.error);
const csrContent = computed(() => page.props.tt?.flash?.arca_csr_content);

const envForm = useForm({ env: props.empresa?.arca_env || 'homologacion' });
const generateForm = useForm({ env: 'homologacion' });
const uploadForm = useForm({ env: 'homologacion', cert_pem: '' });

const setEnv = () => envForm.post(route('admin.arca.set-env'), { preserveScroll: true });
const generateKey = () => generateForm.post(route('admin.arca.generate-key'), { preserveScroll: true });
const uploadCert = () => uploadForm.post(route('admin.arca.upload-cert'), { preserveScroll: true, onSuccess: () => { uploadForm.cert_pem = ''; } });

const downloadCsr = (env) => window.open(route('admin.arca.download-csr', { env }), '_blank');
const downloadKey = (env) => window.open(route('admin.arca.download-key', { env }), '_blank');

const statusBadge = (status) => {
    if (status === 'ok') return { class: 'bg-green-100 text-green-800', label: 'OK' };
    if (status === 'error') return { class: 'bg-red-100 text-red-800', label: 'Error' };
    return { class: 'bg-yellow-100 text-yellow-800', label: '?' };
};

const certStatus = (env) => {
    const s = env === 'homologacion' ? props.homologacion : props.produccion;
    if (!s) return [];
    const items = [];
    items.push({ label: 'Clave privada (key.pem)', ok: s.hasKey, detail: s.hasKey ? (s.keyInfo?.bits + ' bits') : 'No generada' });
    items.push({ label: 'CSR (csr.pem)', ok: s.hasCsr, detail: s.hasCsr ? 'Generado' : 'No generado' });
    items.push({ label: 'Certificado (cert.pem)', ok: s.hasCert, detail: s.hasCert ? 'Instalado' : 'No instalado' });
    if (s.hasCert && s.hasKey) {
        items.push({ label: 'Certificado <-> Clave', ok: s.keyPairMatch, detail: s.keyPairMatch ? 'Coinciden' : 'NO coinciden' });
    }
    if (s.certInfo) {
        const from = s.certInfo.validFrom ? new Date(s.certInfo.validFrom * 1000).toLocaleDateString('es-AR') : '?';
        const to = s.certInfo.validTo ? new Date(s.certInfo.validTo * 1000).toLocaleDateString('es-AR') : '?';
        items.push({ label: 'Validez', ok: true, detail: from + ' - ' + to });
        items.push({ label: 'Emisor', ok: true, detail: (s.certInfo.issuer?.organizationName || '') + ' (' + (s.certInfo.issuer?.commonName || '') + ')' });
    }
    return items;
};
</script>

<template>
    <AppLayout title="ARCA">
        <Head title="ARCA" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">ARCA - Configuracion</h2>
        </template>

        <div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8 space-y-6">
            <div v-if="flashSuccess" class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">{{ flashSuccess }}</div>
            <div v-if="flashError" class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded text-sm">{{ flashError }}</div>

            <div v-if="empresa" class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Empresa</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div><div class="text-xs text-gray-500">Razon social</div><div class="text-sm font-medium text-gray-900">{{ empresa.razon_social }}</div></div>
                    <div><div class="text-xs text-gray-500">CUIT</div><div class="text-sm font-medium text-gray-900">{{ empresa.cuit }}</div></div>
                    <div><div class="text-xs text-gray-500">Entorno activo</div>
                        <div class="mt-1 flex items-center gap-2">
                            <select v-model="envForm.env" class="border-gray-300 rounded-md shadow-sm text-sm"><option value="homologacion">Homologacion</option><option value="produccion">Produccion</option></select>
                            <PrimaryButton class="!text-xs" :disabled="envForm.processing" @click="setEnv">Cambiar</PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <div v-for="env in ['homologacion', 'produccion']" :key="env" class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900 uppercase">{{ env }}</h3>
                    <div class="flex items-center gap-2">
                        <SecondaryButton class="!text-xs" @click="generateForm.env = env; generateKey()">Generar Key + CSR</SecondaryButton>
                        <SecondaryButton class="!text-xs" @click="downloadCsr(env)">Descargar CSR</SecondaryButton>
                        <SecondaryButton class="!text-xs" @click="downloadKey(env)">Descargar Key</SecondaryButton>
                    </div>
                </div>
                <div class="p-4">
                    <div class="space-y-2">
                        <div v-for="item in certStatus(env)" :key="item.label" class="flex items-center gap-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="item.ok ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">{{ item.ok ? 'OK' : 'X' }}</span>
                            <span class="text-gray-700">{{ item.label }}:</span>
                            <span class="text-gray-500">{{ item.detail }}</span>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-gray-200 pt-4">
                        <h4 class="text-xs font-semibold text-gray-900 uppercase mb-2">Subir certificado firmado ({{ env }})</h4>
                        <div class="flex items-start gap-2">
                            <textarea v-model="uploadForm.cert_pem" class="block w-full border-gray-300 rounded-md shadow-sm text-xs font-mono" rows="4" placeholder="Pegar el contenido del certificado PEM firmado por AFIP..."></textarea>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <select v-model="uploadForm.env" class="border-gray-300 rounded-md shadow-sm text-xs"><option value="homologacion">Homologacion</option><option value="produccion">Produccion</option></select>
                            <PrimaryButton class="!text-xs" :disabled="uploadForm.processing || !uploadForm.cert_pem" @click="uploadCert">Subir certificado</PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-200"><h3 class="text-sm font-semibold text-gray-900">Diagnostico</h3></div>
                <div class="p-4">
                    <div class="space-y-2">
                        <div v-for="check in diagnostic" :key="check.check" class="flex items-center gap-3 text-sm">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="check.status === 'ok' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">{{ check.status === 'ok' ? 'OK' : 'X' }}</span>
                            <span class="text-gray-700">{{ check.check }}:</span>
                            <span class="text-gray-500">{{ check.detail }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
