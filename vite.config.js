import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/riffraff.css',
            ],
            publicDirectory: 'resources/dist',
        }),
    ],
    server: {
        cors: {
            origin: /https?:\/\/([A-Za-z0-9-.]+)?(localhost|\.test)(?::\d+)?$/
        }
    }
});
