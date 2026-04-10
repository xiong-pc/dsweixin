import type { App } from 'vue';
import ElementPlus from 'element-plus';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import 'element-plus/dist/index.css';
import 'element-plus/theme-chalk/dark/css-vars.css';
import i18n from '@/lang';
import pinia from '@/store';
import router from '@/router';
import '@/router/guard';
import { setupDirectives } from '@/directive';

export function setupPlugins(app: App) {
  app.use(pinia);
  app.use(router);
  app.use(i18n);
  app.use(ElementPlus);
  setupDirectives(app);

  for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
    app.component(key, component);
  }
}
