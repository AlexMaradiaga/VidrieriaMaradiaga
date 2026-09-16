import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/primevue') || id.includes('node_modules/@primevue') || id.includes('node_modules/@primeuix')) {
                        return 'primevue';
                    }

                    if (id.includes('node_modules/vue') || id.includes('node_modules/@vue') || id.includes('node_modules/@inertiajs')) {
                        return 'vue';
                    }

                    if (id.includes('node_modules/axios')) {
                        return 'http';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        vue(),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: [
                'favicon.ico',
                'icons/vidrieria-maradiaga-192.png',
                'icons/vidrieria-maradiaga-512.png',
            ],
            manifest: {
                id: '/',
                name: 'Vidriería Maradiaga ERP',
                short_name: 'Maradiaga ERP',
                description: 'Gestión empresarial e inventario de Vidriería Maradiaga.',
                lang: 'es',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                orientation: 'any',
                background_color: '#f4f7f6',
                theme_color: '#059669',
                categories: ['business', 'productivity'],
                icons: [
                    {
                        src: '/icons/vidrieria-maradiaga-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/vidrieria-maradiaga-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable',
                    },
                ],
            },
            workbox: {
                navigateFallback: null,
                cleanupOutdatedCaches: true,
                runtimeCaching: [
                    {
                        urlPattern: ({ request }) =>
                            request.destination === 'style' ||
                            request.destination === 'script' ||
                            request.destination === 'font',
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'vidrieria-static-assets',
                            expiration: {
                                maxEntries: 80,
                                maxAgeSeconds: 60 * 60 * 24 * 30,
                            },
                        },
                    },
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
});
