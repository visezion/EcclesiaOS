import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (! id.includes('node_modules')) {
                        return undefined;
                    }

                    if (id.includes('livekit-client')) {
                        return 'vendor-livekit';
                    }

                    if (id.includes('chart.js')) {
                        return 'vendor-charts';
                    }

                    if (id.includes('lucide')) {
                        return 'vendor-icons';
                    }

                    if (id.includes('alpinejs')) {
                        return 'vendor-alpine';
                    }

                    return 'vendor';
                },
            },
        },
    },
});
