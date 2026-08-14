<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
    choferes: Array,
    choferesError: Boolean,
    tileUrl: String,
    tileAttribution: String,
});

const mapContainer = ref(null);
let map = null;
let tileLayer = null;
const L = ref(null);
let pollTimer = null;

const choferesData = ref(props.choferes);
const ultimaActualizacion = ref(null);
const cargando = ref(false);

const loadLeaflet = () => new Promise((resolve) => {
    if (window.L && window.L.map) {
        L.value = window.L;
        return resolve(window.L);
    }
    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(css);

    const js = document.createElement('script');
    js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    js.onload = () => {
        delete window.L.Icon.Default.prototype._getIconUrl;
        window.L.Icon.Default.mergeOptions({
            iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
            iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        });
        L.value = window.L;
        resolve(window.L);
    };
    document.head.appendChild(js);
});

let markerByChofer = new Map();

const popupContent = (chofer, u) => {
    if (!u) {
        return `<div class="text-xs"><span class="font-semibold">${chofer.name}</span><br><span class="text-gray-500">Sin ubicación en este momento</span></div>`;
    }
    const fecha = u.created_at ? new Date(u.created_at).toLocaleString('es-AR') : '-';
    return `<div class="text-xs">
        <div class="font-semibold">${chofer.name}</div>
        <div class="text-gray-500">${chofer.email || ''}</div>
        <div class="text-gray-400">Última: ${fecha}</div>
        ${u.accuracy ? `<div class="text-gray-400">Precisión: ${u.accuracy} m</div>` : ''}
        ${u.hoja_ruta_id ? `<div class="text-gray-400">Hoja de ruta: ${u.hoja_ruta_id}</div>` : ''}
    </div>`;
};

const renderMarkers = async () => {
    if (!map || !window.L) return;
    const Leaflet = window.L;

    markerByChofer.forEach((m) => m.removeFrom(map));
    markerByChofer.clear();

    const bounds = [];
    choferesData.value.forEach((c) => {
        const u = c.ultima_ubicacion;
        if (!u) return;
        const lat = parseFloat(u.lat);
        const lng = parseFloat(u.lng);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;
        const m = Leaflet.marker([lat, lng]).addTo(map).bindPopup(popupContent(c, u));
        markerByChofer.set(c.id, m);
        bounds.push([lat, lng]);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [20, 20], maxZoom: 12 });
    }
};

const fetchUbicaciones = async () => {
    cargando.value = true;
    try {
        const res = await fetch(route('admin.reparto.ubicaciones.json'), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (!res.ok) throw new Error('http ' + res.status);
        const data = await res.json();
        if (Array.isArray(data)) {
            choferesData.value = data;
        }
        ultimaActualizacion.value = new Date();
        await renderMarkers();
    } catch (e) {
        // mantener últimos datos; fallos son transitorios
    } finally {
        cargando.value = false;
    }
};

const initMap = async () => {
    await loadLeaflet();
    await nextTick();
    const container = mapContainer.value;
    if (!container || map) return;

    map = window.L.map(container, {
        center: [-34.6037, -58.3816],
        zoom: 6,
        zoomControl: true,
    });

    tileLayer = window.L.tileLayer(props.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: props.tileAttribution || '&copy; OpenStreetMap contributors',
    }).addTo(map);

    await renderMarkers();

    pollTimer = setInterval(fetchUbicaciones, 5000);
    await fetchUbicaciones();
};

onMounted(initMap);

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
    markerByChofer.forEach((m) => m.removeFrom(map));
    markerByChofer.clear();
    if (map) map.remove();
});
</script>

<template>
    <AppLayout title="Mapa de reparto">
        <Head title="Mapa de reparto" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mapa de reparto en tiempo real</h2>
                <button
                    v-if="cargando"
                    class="text-xs text-gray-500"
                >Actualizando…</button>
            </div>
        </template>

        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <p v-if="!$page.props.tt?.roles?.includes('admin')" class="text-sm text-red-600">
                Solo usuarios administradores pueden ver este mapa.
            </p>

            <div v-if="props.choferesError" class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">No se pudieron cargar las ubicaciones.</p>
                <p class="mt-1">Falta aplicar las migraciones en la base de datos. Ejecutá:</p>
                <code class="mt-1 block break-all rounded bg-red-100/60 px-2 py-1 text-xs">docker compose exec -T app php artisan migrate --force</code>
                <p class="mt-1">Luego recargá la página.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-8">
                    <div
                        ref="mapContainer"
                        class="w-full bg-gray-100 rounded-lg shadow ring-1 ring-black ring-opacity-5"
                        style="height: 560px;"
                    ></div>
                </div>

                <div class="lg:col-span-4">
                    <div class="bg-white rounded-lg shadow ring-1 ring-black ring-opacity-5 p-4">
                        <div class="flex items-baseline justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">Choferes con tracking</h3>
                            <span v-if="ultimaActualizacion" class="text-xs text-gray-400">
                                Actualizado: {{ ultimaActualizacion.toLocaleTimeString('es-AR') }}
                            </span>
                        </div>

                        <div v-if="choferesData.length === 0" class="text-sm text-gray-500">
                            No hay choferes con envío de ubicación activo.
                        </div>

                        <ul v-else class="space-y-2">
                            <li
                                v-for="c in choferesData"
                                :key="c.id"
                                class="flex items-center justify-between text-sm"
                            >
                                <div class="truncate">
                                    <span class="font-medium text-gray-800">{{ c.name }}</span>
                                    <span v-if="c.email" class="block text-xs text-gray-500 truncate">{{ c.email }}</span>
                                </div>
                                <span
                                    :class="c.ultima_ubicacion ? 'bg-green-500' : 'bg-gray-400'"
                                    class="inline-block w-2.5 h-2.5 rounded-full"
                                    :title="c.ultima_ubicacion ? 'Online' : 'Sin ubicación'"
                                ></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
