import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Self-hosted at build time (no Google Fonts request at runtime).
                bunny('Barlow', { weights: [400, 500, 600, 700, 800] }),
                bunny('Barlow Condensed', { weights: [700, 800] }),
                bunny('Barlow Semi Condensed', { weights: [500, 600, 700] }),
                bunny('IBM Plex Mono', { weights: [400, 500, 600] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
