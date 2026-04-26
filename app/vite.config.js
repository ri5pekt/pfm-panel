// pfm-panel/app/vite.config.js
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import path from "path";
import { readFileSync } from "fs";

const pkg = JSON.parse(readFileSync(new URL("./package.json", import.meta.url), "utf-8"));

export default defineConfig({
    root: path.resolve(__dirname),
    plugins: [vue()],
    define: {
        __APP_VERSION__: JSON.stringify(pkg.version),
    },
    server: {
        proxy: {
            "/wp-json": {
                target: "https://www.particleformen.com",
                changeOrigin: true,
                secure: false,
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
        // Inline all assets smaller than 100KB (includes all SVG logos)
        assetsInlineLimit: 102400, // 100KB in bytes
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
