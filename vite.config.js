import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/dashboard.css',
                'resources/js/dashboard.js',
            ],
            refresh: [`resources/views/**/*`],
        }),
        tailwindcss(),
    ],
    server: {
        // A named host keeps the hot file on http://localhost:5173: the
        // Content-Security-Policy cannot list a bracketed IPv6 address.
        host: 'localhost',
        cors: true,
        // host:'192.168.8.78',
        // port:'8002'
    },
});