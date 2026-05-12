import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  css: {
    preprocessorOptions: {
      scss: {
        additionalData: `@use "@/styles/variables.scss" as *;`,
      },
    },
  },
  server: {
    port: 5173,
    open: true,
  },
  build: {
    // 业务代码外的公共依赖拆成独立 chunk，避免改一行业务代码导致 vendor 缓存失效
    rollupOptions: {
      output: {
        manualChunks: {
          'vue-vendor': ['vue', 'vue-router', 'pinia', '@vueuse/core'],
          'element-vendor': ['element-plus', '@element-plus/icons-vue'],
          'utils-vendor': ['axios', 'nprogress', 'vue-i18n'],
        },
      },
    },
    chunkSizeWarningLimit: 600,
  },
});
