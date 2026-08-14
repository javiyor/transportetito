/* TransporteTito - Service Worker (root scope)
 * Offline shell (+ cache dinámico de tiles OSM y ubicaciones de reparto).
 * NO depende de workbox; lee /build/manifest.json al instalar para precachear assets.
 */

const ASSETS_CACHE = 'transportetito-v1';
const TILE_CACHE = 'tt-osm-tiles';
const UBI_CACHE = 'tt-reparto-ubicaciones';

const CORE_URLS = [
    '/manifest.webmanifest',
    '/',
    '/login',
    '/favicon.ico',
    '/pwa-32.png',
    '/pwa-16.png',
    '/apple-touch-icon.png',
    '/android-chrome-192x192.png',
    '/android-chrome-512x512.png',
];

const isOsmTile = (href) => /tile\.openstreetmap\.org/.test(href);

async function precacheBuildAssets(cache) {
    try {
        const resp = await fetch('/build/manifest.json', { cache: 'no-store' });
        if (!resp.ok) return;
        const manifest = await resp.json();
        const urls = [];
        for (const entry of Object.values(manifest)) {
            if (typeof entry !== 'object' || entry === null) continue;
            if (entry.file) urls.push('/build/' + entry.file);
            if (Array.isArray(entry.css)) entry.css.forEach((c) => urls.push('/build/' + c));
            if (Array.isArray(entry.assets)) entry.assets.forEach((a) => urls.push('/build/' + a));
        }
        await cache.addAll(urls);
    } catch (e) {
        // si el manifest no está disponible, seguimos con CORE_URLS
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(ASSETS_CACHE);
            await precacheBuildAssets(cache);
            await cache.addAll(CORE_URLS);
            await self.skipWaiting();
        })()
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Ubicaciones de reparto: network-first, fallback a cache (datos stale vale la pena)
    if (url.pathname.endsWith('/admin/reparto/ubicaciones.json')) {
        event.respondWith(
            fetch(request, { credentials: 'include' })
                .then((resp) => {
                    const copy = resp.clone();
                    caches.open(UBI_CACHE).then((c) => c.put(request, copy)).catch(() => {});
                    return resp;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    // Tiles OSM: cache-first (con expiración implícita por re-fetch)
    if (isOsmTile(url.href)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((resp) => {
                    if (resp.ok) {
                        caches.open(TILE_CACHE).then((c) => c.put(request, resp.clone())).catch(() => {});
                    }
                    return resp;
                });
            })
        );
        return;
    }

    // Assets estáticos del build: cache-first
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(caches.match(request));
        return;
    }

    // Navegaciones (HTML): network-first, fallback al shell cacheado (offline)
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request, { credentials: 'include' }).catch(() => caches.match(request).then((r) => r || caches.match('/')))
        );
        return;
    }
});
