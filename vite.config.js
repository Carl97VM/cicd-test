import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            vue: "vue/dist/vue.esm-bundler.js",
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        // watch: {
        //     ignored: ["**/storage/framework/views/**"],
        // },
        watch: {
            ignored: [
                "**/storage/**",
                "**/vendor/**",
                "**/node_modules/.vite/**",
                "**/database/**",
                "**/tests/**"
            ],
            // NECESARIO PARA DOCKER EN WINDOWS:
            // Permite detectar cambios mediante sondeo de archivos
            usePolling: true,
            interval: 100,
        },
    },
});
