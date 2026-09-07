import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: false,
            fonts: [
                bunny('Tajawal', {
                    weights: [400, 500, 700],
                }),
                bunny('Cairo', {
                    weights: [400, 600, 700],
                }),
            ],
        }),
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: (tag) => tag.startsWith('x-'),
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        // Split heavy third-party code into independent chunks so the initial
        // page load only downloads what it needs. Page components are already
        // code-split automatically by the import.meta.glob in app.js; these
        // manual chunks keep big vendors out of every page chunk without
        // changing any runtime behaviour (import graph untouched).
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                // Rolldown (Vite 8) accepts only the function form here.
                manualChunks(id) {
                    if (!id.includes('node_modules')) return undefined;
                    if (id.includes('node_modules/chart.js')) return 'charts';
                    if (id.includes('node_modules/@inertiajs') || id.includes('node_modules/ziggy-js')) return 'inertia-vendor';
                    if (id.includes('node_modules/laravel-echo') || id.includes('node_modules/pusher-js')) return 'realtime';
                    if (id.includes('node_modules/axios') || id.includes('node_modules/mitt')) return 'http';
                    if (id.includes('node_modules/vue/')) return 'vue-vendor';
                    return undefined;
                },
            },
        },
    },
    server: {
        watch: {
            // Runtime logs and the ADMS SQLite queue change continuously while
            // fingerprint devices are connected. They must never trigger HMR.
            ignored: [
                '**/storage/**',
                '**/zkteco-service/logs/**',
                '**/*.sqlite3',
                '**/*.sqlite3-shm',
                '**/*.sqlite3-wal',
            ],
        },
    },
});
