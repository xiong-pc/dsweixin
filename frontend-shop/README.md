# dsweixin · 商城前台（Nuxt 3 SSR）

跨境电商 SaaS 前台，由 Nuxt 3 SSR 渲染，多语言/多币种/多店铺隔离。

## 技术栈

- **Nuxt 3** + Vue 3 + TypeScript
- **@nuxtjs/i18n** — 多语言（zh-CN / en / ja / ko）+ `prefix_except_default` 路由策略
- **@nuxtjs/tailwindcss** — 原子化样式 + 主题色 CSS 变量
- **@pinia/nuxt** — 状态管理（购物车 / 用户态）
- **@vueuse/nuxt** — 响应式工具

## 本地启动

```bash
# 1. 装依赖（自动触发 `nuxt prepare` 生成 .nuxt/tsconfig.json）
npm install

# 2. 复制环境变量
cp .env.example .env

# 3. 后端启动（另起终端）
cd ../backend && php artisan serve

# 4. 启动前台
npm run dev
# → http://localhost:3000
```

## 多店铺解析

启动期由 `middleware/tenant.global.ts` 调用后端 `GET /api/v1/shop/config`：

| 优先级 | 解析方式                                  | 适用场景 |
| ------ | ----------------------------------------- | -------- |
| 1      | host 子域（`acme.platform.local`）        | 生产部署 |
| 2      | `NUXT_PUBLIC_FALLBACK_SUBDOMAIN` 环境变量 | 本地开发 |

后端通过 `X-Shop-Subdomain` header 接收解析后的子域，由 `ShopResolverMiddleware` 反查 Shop + Tenant。

## 目录结构

```
frontend-shop/
├── assets/css/tailwind.css     # tailwind 入口（含主题色变量）
├── composables/
│   ├── useApi.ts               # 统一 API 客户端（baseURL / token / X-Tenant-Id）
│   └── useShop.ts              # 当前店铺配置（SSR 期填充）
├── i18n/locales/               # 4 语言占位 JSON
├── middleware/tenant.global.ts # 全局店铺解析
├── pages/index.vue             # 首页占位（PR43 真实首页）
├── types/shop.ts               # ShopConfig / ApiResponse 类型
├── app.vue                     # 根组件
├── i18n.config.ts              # vue-i18n 运行时
├── nuxt.config.ts              # Nuxt 主配置
├── tailwind.config.ts          # tailwind 配置
└── tsconfig.json               # 继承 .nuxt/tsconfig.json
```

## 后续 PR 路线（M11）

- **PR43**：首页 + 类目页（SEO meta）
- **PR44**：商品详情页（OG + JSON-LD + hreflang）
- **PR45**：购物车 + 结账（Stripe / 微信）
- **PR46**：我的中心（订单 / 地址 / 登录）
- **PR47**：语言/币种切换 + 主题色动态注入

## 脚本

| 命令                   | 说明                          |
| ---------------------- | ----------------------------- |
| `npm run dev`          | 本地开发（HMR + SSR）         |
| `npm run build`        | 生产构建（输出到 `.output/`） |
| `npm run preview`      | 预览构建产物                  |
| `npm run typecheck`    | vue-tsc 类型检查              |
| `npm run lint:check`   | ESLint 检查                   |
| `npm run format:check` | Prettier 格式检查             |
