<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref, onMounted, onBeforeUnmount, nextTick } from 'vue';

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
const choferesActivos = computed(() => choferesData.value.filter((chofer) => chofer.activo).length);
const formatHora = (value) => value ? new Date(value).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }) : '-';

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
let recorridoByChofer = new Map();

const popupContent = (chofer, u) => {
    if (!u) {
        return `<div class="text-xs"><span class="font-semibold">${chofer.name}</span><br><span class="text-gray-500">Sin ubicación en este momento</span></div>`;
    }
    const fecha = u.created_at ? new Date(u.created_at).toLocaleString('es-AR') : '-';
    return `<div class="text-xs">
        <div class="font-semibold">${chofer.name}</div>
        <div class="text-gray-500">${chofer.email || ''}</div>
        <div class="${chofer.activo ? 'text-green-700' : 'text-gray-500'}">${chofer.activo ? 'Celular activo' : 'Último registro'}</div>
        <div class="text-gray-400">Última: ${fecha}</div>
        ${u.accuracy ? `<div class="text-gray-400">Precisión: ${u.accuracy} m</div>` : ''}
        ${u.hoja_ruta_id ? `<div class="text-gray-400">Hoja de ruta: ${u.hoja_ruta_id}</div>` : ''}
        <div class="text-indigo-700">Recorrido: ${chofer.resumen_recorrido?.puntos || 0} puntos · ${Number(chofer.resumen_recorrido?.distancia_km || 0).toLocaleString('es-AR')} km</div>
        <div class="text-gray-500">Desde ${chofer.resumen_recorrido?.desde ? new Date(chofer.resumen_recorrido.desde).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }) : '-'} hasta ${chofer.resumen_recorrido?.hasta ? new Date(chofer.resumen_recorrido.hasta).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }) : '-'}</div>
    </div>`;
};

const colorRecorrido = (id) => ['#4f46e5', '#059669', '#d97706', '#db2777', '#0891b2', '#7c3aed'][Number(id) % 6];

const renderMarkers = async () => {
    if (!map || !window.L) return;
    const Leaflet = window.L;

    markerByChofer.forEach((m) => m.removeFrom(map));
    markerByChofer.clear();
    recorridoByChofer.forEach((layers) => layers.forEach((layer) => layer.removeFrom(map)));
    recorridoByChofer.clear();

    choferesData.value.forEach((c) => {
        const u = c.ultima_ubicacion;
        const color = colorRecorrido(c.id);
        const puntos = (c.recorrido || []).filter((punto) => Number.isFinite(Number(punto.lat)) && Number.isFinite(Number(punto.lng)));
        const layers = [];

        if (puntos.length > 1) {
            layers.push(Leaflet.polyline(puntos.map((punto) => [Number(punto.lat), Number(punto.lng)]), {
                color,
                weight: 4,
                opacity: 0.75,
            }).addTo(map));

            const salto = Math.max(1, Math.ceil(puntos.length / 100));
            puntos.filter((_, index) => index % salto === 0 || index === puntos.length - 1).forEach((punto) => {
                layers.push(Leaflet.circleMarker([Number(punto.lat), Number(punto.lng)], {
                    radius: 3,
                    color,
                    weight: 1,
                    fillOpacity: 0.7,
                }).addTo(map));
            });
        }
        recorridoByChofer.set(c.id, layers);

        if (u) {
            const lat = parseFloat(u.lat);
            const lng = parseFloat(u.lng);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                const m = Leaflet.circleMarker([lat, lng], {
                    radius: 9,
                    color: '#ffffff',
                    weight: 3,
                    fillColor: c.activo ? '#16a34a' : '#6b7280',
                    fillOpacity: 1,
                }).addTo(map).bindPopup(popupContent(c, u));
                markerByChofer.set(c.id, m);
            }
        }
    });
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
        console.error('[mapa] error fetch:', e);
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
        center: [-29.1176, -59.6583],
        zoom: 13,
        zoomControl: true,
    });

    tileLayer = window.L.tileLayer(props.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: props.tileAttribution || '&copy; OpenStreetMap contributors',
    }).addTo(map);

    await renderMarkers();

    pollTimer = setInterval(fetchUbicaciones, 30000);
    await fetchUbicaciones();
};

onMounted(initMap);

const centrarEnChofer = (chofer) => {
    if (!map || !chofer.ultima_ubicacion) return;
    const puntos = (chofer.recorrido || [])
        .filter((punto) => Number.isFinite(Number(punto.lat)) && Number.isFinite(Number(punto.lng)))
        .map((punto) => [Number(punto.lat), Number(punto.lng)]);
    if (puntos.length > 1) {
        map.fitBounds(puntos, { padding: [30, 30], maxZoom: 14 });
    }
    const u = chofer.ultima_ubicacion;
    const lat = parseFloat(u.lat);
    const lng = parseFloat(u.lng);
    if (Number.isNaN(lat) || Number.isNaN(lng)) return;
    if (puntos.length <= 1) map.setView([lat, lng], 15, { animate: true });
    const marker = markerByChofer.get(chofer.id);
    if (marker) marker.openPopup();
};

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
    markerByChofer.forEach((m) => m.removeFrom(map));
    markerByChofer.clear();
    recorridoByChofer.forEach((layers) => layers.forEach((layer) => layer.removeFrom(map)));
    recorridoByChofer.clear();
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

        <div class="max-w-7xl mx-auto py-3 sm:px-6 lg:px-8">
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
                            <h3 class="text-sm font-semibold text-gray-700">Recorridos últimas 12 h · {{ choferesActivos }} activos</h3>
                            <span v-if="ultimaActualizacion" class="text-xs text-gray-400">
                                Actualizado: {{ ultimaActualizacion.toLocaleTimeString('es-AR') }}
                            </span>
                        </div>

                        <div v-if="choferesData.length === 0" class="text-sm text-gray-500">
                            No hay ubicaciones guardadas en las últimas 12 horas.
                        </div>

<ul v-else class="space-y-2">
    <li
        v-for="c in choferesData"
        :key="c.id"
        class="flex items-center justify-between text-sm cursor-pointer hover:bg-gray-50 rounded px-2 py-1"
        @click="centrarEnChofer(c)"
    >
        <div class="truncate">
            <span class="inline-flex items-center gap-1 font-medium text-gray-800"><span class="inline-block h-1 w-4 rounded" :style="{ backgroundColor: colorRecorrido(c.id) }"></span>{{ c.name }}</span>
            <span class="block text-xs truncate" :class="c.activo ? 'text-green-700' : 'text-gray-500'">{{ c.activo ? 'Activo ahora' : 'Sin ping reciente' }} · {{ c.resumen_recorrido?.puntos || 0 }} puntos · {{ Number(c.resumen_recorrido?.distancia_km || 0).toLocaleString('es-AR') }} km</span>
            <span class="block text-[10px] text-gray-400">{{ formatHora(c.resumen_recorrido?.desde) }}–{{ formatHora(c.resumen_recorrido?.hasta) }}</span>
            <span v-if="c.ultima_ubicacion?.hoja_ruta_id" class="block text-xs text-gray-500 truncate">Hoja #{{ c.ultima_ubicacion.hoja_ruta_id }}</span>
        </div>
        <span
            :class="c.activo ? 'bg-green-500' : 'bg-gray-400'"
            class="inline-block w-2.5 h-2.5 rounded-full"
            :title="c.activo ? 'Celular activo' : 'Sin ping reciente'"
        ></span>
    </li>
</ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
