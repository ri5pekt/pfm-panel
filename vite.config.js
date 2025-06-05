// pfm-panel/app/vite.config.js
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import path from "path";

export default defineConfig({
    root: path.resolve(__dirname),
    plugins: [vue()],
    server: {
        proxy: {
            "/wp-json": {
                target: "https://particlestage.wpengine.com",
                changeOrigin: true,
                secure: false,
                configure: (proxy) => {
                    proxy.on("proxyReq", (proxyReq, req, res) => {
                        // You can even manually forward your cookies here if needed
                    });
                },
            },
        },
    },
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "src"),
        },
    },
    build: {
        outDir: path.resolve(__dirname, "../dist"),
        emptyOutDir: true,
        rollupOptions: {
            input: path.resolve(__dirname, "index.html"),
            output: {
                format: "iife",
                entryFileNames: "assets/app.js",
                assetFileNames: "assets/app.css",
            },
        },
    },
});
