import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            // localhost por defecto; usa VITE_DEV_HOST solo para prueba en LAN/móvil
            host: process.env.VITE_DEV_HOST || 'localhost',
            port: 5173,
        },
    },
});
