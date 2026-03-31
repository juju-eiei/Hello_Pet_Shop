import { defineConfig } from 'vite';
import fullReload from 'vite-plugin-full-reload';

export default defineConfig({
  root: './',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost/Hello_Pet_Shop',
        changeOrigin: true,
      },
    },
  },
  plugins: [
    fullReload(['../src/**/*']),
  ],
});
