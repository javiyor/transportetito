import './bootstrap';
import '../css/app.css';

if ('serviceWorker' in navigator && import.meta.env.MODE === 'production') {
    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (refreshing) return;
        refreshing = true;
        window.location.reload();
    });

    const notifyUpdate = () => window.dispatchEvent(new CustomEvent('pwa:update-available'));

    navigator.serviceWorker.register('/sw.js').then((reg) => {
        // Detect waiting worker (update already downloaded)
        if (reg.waiting) notifyUpdate();

        reg.addEventListener('updatefound', () => {
            const sw = reg.installing;
            if (!sw) return;
            sw.addEventListener('statechange', () => {
                if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                    notifyUpdate();
                }
            });
        });

        // Chequeo periódico + al volver a la pestaña
        const checkUpdate = () => reg.update().catch(() => {});
        setInterval(checkUpdate, 60 * 60 * 1000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') checkUpdate();
        });
    }).catch(() => {});
}

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
