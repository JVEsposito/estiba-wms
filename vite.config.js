import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/office.css',
                'resources/js/office-cameras.js',
                'resources/css/office-loads.css',
                'resources/js/office-loads.js',
                'resources/css/office-admin.css',
                'resources/js/office-admin.js',
                'resources/css/office-materials.css',
                'resources/js/office-materials.js',
                'resources/css/office-validation.css',
                'resources/js/office-validation.js',
                'resources/css/office-validation-catalog.css',
                'resources/js/office-validation-catalog.js',
                'resources/css/office-prefrio.css',
                'resources/js/office-prefrio.js',
                'resources/css/office-management.css',
                'resources/js/office-management.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
