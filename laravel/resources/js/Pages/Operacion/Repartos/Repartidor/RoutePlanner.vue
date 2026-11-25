<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ hoja: { type: Object, required: true } });

const mapElement = ref(null);
const stops = ref([]);
const coordinates = ref({});
const geocoding = ref(false);
const saving = ref(false);
const error = ref('');
let map = null;
let markers = [];
let routeLine = null;
let draggedId = null;

const pendingCount = computed(() => stops.value.filter((stop) => stop.estado_entrega === 'pendiente').length);

const addressFor = (stop) => [
    stop.direccion || stop.entrega_cuenta?.direccion,
    stop.localidad || stop.entrega_cuenta?.localidad,
    'Argentina',
].filter(Boolean).join(', ').trim();

const cacheKey = (address) => `tt-route-geocode:${address.toLocaleLowerCase()}`;

const cachedCoordinates = (address) => {
    try {
        const value = JSON.parse(localStorage.getItem(cacheKey(address)) || 'null');
        return Array.isArray(value) && value.length === 2 ? value : null;
    } catch {
        return null;
    }
};

const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

const geocodeStops = async () => {
    geocoding.value = true;
    const addressEntries = stops.value.map((stop) => [stop.id, addressFor(stop)]).filter(([, address]) => address);
    let lastRequestAt = 0;

    for (const [id, address] of addressEntries) {
        const cached = cachedCoordinates(address);
        if (cached) {
            coordinates.value[id] = cached;
            continue;
        }

        const delay = Math.max(0, 1100 - (Date.now() - lastRequestAt));
        if (lastRequestAt) await wait(delay);

        try {
            const params = new URLSearchParams({ format: 'jsonv2', limit: '1', countrycodes: 'ar', q: address });
            const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
                headers: { Accept: 'application/json' },
            });
            lastRequestAt = Date.now();
            if (!response.ok) continue;
            const results = await response.json();
            if (!results.length) continue;

            const point = [Number(results[0].lat), Number(results[0].lon)];
            if (point.every(Number.isFinite)) {
                coordinates.value[id] = point;
                localStorage.setItem(cacheKey(address), JSON.stringify(point));
            }
        } catch {
            // Las paradas sin geocodificar siguen visibles en la lista.
        }
    }

    geocoding.value = false;
    renderMap();
};

const loadLeaflet = () => new Promise((resolve, reject) => {
    if (window.L?.map) return resolve(window.L);

    if (!document.querySelector('link[data-route-leaflet]')) {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        css.dataset.routeLeaflet = 'true';
        document.head.appendChild(css);
    }

    const existingScript = document.querySelector('script[data-route-leaflet]');
    if (existingScript) {
        existingScript.addEventListener('load', () => resolve(window.L), { once: true });
        existingScript.addEventListener('error', reject, { once: true });
        return;
    }

    const script = document.createElement('script');
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    script.dataset.routeLeaflet = 'true';
    script.onload = () => resolve(window.L);
    script.onerror = reject;
    document.head.appendChild(script);
});

const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
}[char]));

const renderMap = () => {
    if (!map || !window.L) return;
    markers.forEach((marker) => marker.remove());
    markers = [];
    if (routeLine) routeLine.remove();

    const points = [];
    stops.value.forEach((stop, index) => {
        const point = coordinates.value[stop.id];
        if (!point) return;
        const marker = window.L.marker(point, {
            icon: window.L.divIcon({
                className: '',
                html: `<span style="display:grid;place-items:center;width:28px;height:28px;border:2px solid white;border-radius:50%;background:${stop.estado_entrega === 'pendiente' ? '#4f46e5' : '#6b7280'};color:white;font-weight:700;box-shadow:0 1px 4px #555">${index + 1}</span>`,
                iconSize: [28, 28],
                iconAnchor: [14, 14],
            }),
        }).addTo(map);
        marker.bindPopup(`<strong>${index + 1}. ${escapeHtml(stop.entrega_cuenta?.tercero?.razon_social || 'Entrega')}</strong><br>${escapeHtml(addressFor(stop))}`);
        markers.push(marker);
        points.push(point);
    });

    if (points.length) {
        routeLine = window.L.polyline(points, { color: '#4f46e5', weight: 3, opacity: 0.7 }).addTo(map);
        map.fitBounds(points, { padding: [24, 24], maxZoom: 14 });
    }
};

const initializeMap = async () => {
    try {
        await loadLeaflet();
        if (!mapElement.value) return;
        map = window.L.map(mapElement.value).setView([-34.6037, -58.3816], 6);
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
        await geocodeStops();
    } catch {
        error.value = 'No se pudo cargar el mapa. Verificá tu conexión e intentá nuevamente.';
    }
};

const moveStop = (from, to) => {
    if (to < 0 || to >= stops.value.length) return;
    const [stop] = stops.value.splice(from, 1);
    stops.value.splice(to, 0, stop);
    renderMap();
};

const dropStop = (targetId) => {
    if (draggedId === null || draggedId === targetId) return;
    const from = stops.value.findIndex((stop) => stop.id === draggedId);
    const to = stops.value.findIndex((stop) => stop.id === targetId);
    if (from >= 0 && to >= 0) moveStop(from, to);
    draggedId = null;
};

const saveOrder = () => {
    error.value = '';
    saving.value = true;
    router.post(route('repartidor.hojas.orden', props.hoja.id), {
        items: stops.value.map((stop) => stop.id),
    }, {
        preserveScroll: true,
        onError: () => { error.value = 'No se pudo guardar el orden de paradas.'; },
        onFinish: () => { saving.value = false; },
    });
};

watch(() => props.hoja.items, (items) => {
    stops.value = [...(items || [])].sort((a, b) => a.orden - b.orden);
}, { immediate: true });

onMounted(initializeMap);
onBeforeUnmount(() => {
    markers.forEach((marker) => marker.remove());
    map?.remove();
});
</script>

<template>
    <section class="border-t border-gray-100 p-3 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Mapa y orden de entregas</h3>
                <p class="text-xs text-gray-500">{{ pendingCount }} paradas pendientes · Arrastrá para cambiar el orden.</p>
            </div>
            <button
                type="button"
                class="rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                :disabled="saving || hoja.estado !== 'borrador' || !stops.length"
                @click="saveOrder"
            >{{ saving ? 'Guardando…' : 'Guardar orden' }}</button>
        </div>

        <p v-if="error" class="rounded-md bg-red-50 p-2 text-xs text-red-700">{{ error }}</p>
        <p v-if="geocoding" class="text-xs text-gray-500">Buscando ubicaciones para las paradas…</p>
        <p v-else-if="stops.length && !Object.keys(coordinates).length" class="text-xs text-amber-700">No se encontraron ubicaciones para mostrar. Revisá que las direcciones incluyan calle y localidad.</p>

        <div ref="mapElement" class="h-72 w-full rounded-lg bg-gray-100 sm:h-96"></div>

        <ol class="space-y-2">
            <li
                v-for="(stop, index) in stops"
                :key="stop.id"
                draggable="true"
                class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-2"
                @dragstart="draggedId = stop.id"
                @dragover.prevent
                @drop.prevent="dropStop(stop.id)"
                @dragend="draggedId = null"
            >
                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">{{ index + 1 }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-gray-900">{{ stop.entrega_cuenta?.tercero?.razon_social || 'Entrega' }}</p>
                    <p class="truncate text-xs text-gray-500">{{ addressFor(stop) || 'Sin dirección' }}</p>
                    <p v-if="!coordinates[stop.id] && addressFor(stop)" class="text-[10px] text-amber-700">Ubicación no encontrada</p>
                </div>
                <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-medium" :class="stop.estado_entrega === 'pendiente' ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600'">
                    {{ stop.estado_entrega === 'pendiente' ? 'Pendiente' : stop.estado_entrega }}
                </span>
                <div class="flex shrink-0 flex-col gap-1">
                    <button type="button" class="rounded border px-1.5 text-xs disabled:opacity-30" :disabled="index === 0" aria-label="Subir parada" @click="moveStop(index, index - 1)">▲</button>
                    <button type="button" class="rounded border px-1.5 text-xs disabled:opacity-30" :disabled="index === stops.length - 1" aria-label="Bajar parada" @click="moveStop(index, index + 1)">▼</button>
                </div>
            </li>
        </ol>
        <p class="text-[10px] text-gray-400">Mapa © OpenStreetMap · Ubicaciones por Nominatim</p>
    </section>
</template>
