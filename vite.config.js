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
