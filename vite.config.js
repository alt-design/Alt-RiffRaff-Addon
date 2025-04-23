import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue2';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/spam-addon.js',
                'resources/css/spam-addon.css',
            ],
            publicDirectory: 'resources/dist',
        }),
        vue(),
    ],
    server: {
        cors: {
            origin: /https?:\/\/([A-Za-z0-9-.]+)?(localhost|\.test)(?::\d+)?$/
        }
    }
});
