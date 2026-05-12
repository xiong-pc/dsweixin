import type { App } from 'vue';
// element-plus 组件 + 编程式 API 由 vite.config.ts 的 unplugin-auto-import / unplugin-vue-components 按需注入，
// 这里不再全量 import，仅引入暗黑主题 css 变量（按需 CSS 由 Components 插件自动处理）
import 'element-plus/theme-chalk/dark/css-vars.css';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import i18n from '@/lang';
import pinia from '@/store';
import router from '@/router';
import '@/router/guard';
import { setupDirectives } from '@/directive';

export function setupPlugins(app: App) {
  app.use(pinia);
  app.use(router);
  app.use(i18n);
  setupDirectives(app);

  // 图标：模板里有 <Search /> 直接用 + <component :is="row.icon" /> 动态字符串绑定，
  // 都依赖全局注册，故保留全量注册（图标包小于 element-plus 主体）
  for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
    app.component(key, component);
  }
}
