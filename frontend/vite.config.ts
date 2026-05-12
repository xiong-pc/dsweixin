import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';

export default defineConfig({
  plugins: [
    vue(),
    // 自动 import：ElMessage / ElLoading / ElMessageBox / ElNotification 等编程式 API
    AutoImport({
      resolvers: [ElementPlusResolver()],
      dts: 'src/types/auto-imports.d.ts',
    }),
    // 自动 import：模板里 <el-button> / <el-table> 等组件，按需打包 + 按需引入 CSS
    Components({
      resolvers: [ElementPlusResolver()],
      dts: 'src/types/components.d.ts',
    }),
  ],
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
