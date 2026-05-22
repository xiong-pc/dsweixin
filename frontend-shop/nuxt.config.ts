// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-05-01',
  devtools: { enabled: true },
  ssr: true,

  modules: ['@nuxt/eslint', '@nuxtjs/i18n', '@nuxtjs/tailwindcss', '@pinia/nuxt', '@vueuse/nuxt'],

  // 通过 runtimeConfig 把环境变量注入运行时；apiBase 控制后端 baseURL
  runtimeConfig: {
    public: {
      // 默认指向本地 Laravel 8000，部署时由 NUXT_PUBLIC_API_BASE 覆盖
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api/v1',
      // 后端 ShopResolverMiddleware 配置：用 X-Shop-Subdomain 兜底（开发环境主域不是真实子域）
      shopHeader: process.env.NUXT_PUBLIC_SHOP_HEADER || 'X-Shop-Subdomain',
      // 平台主域，用于本地开发时拼装子域 URL（生产环境直接用真实 host 解析）
      platformDomain: process.env.NUXT_PUBLIC_PLATFORM_DOMAIN || 'platform.local',
    },
  },

  // i18n：策略 prefix_except_default → 默认 zh-CN 不带前缀，其它语言走 /en /ja /ko
  // locales 文件位于 ./i18n/locales/<code>.json（M11-PR47 实际填充内容）
  i18n: {
    strategy: 'prefix_except_default',
    defaultLocale: 'zh-CN',
    locales: [
      { code: 'zh-CN', iso: 'zh-CN', name: '简体中文', file: 'zh-CN.json' },
      { code: 'en', iso: 'en-US', name: 'English', file: 'en.json' },
      { code: 'ja', iso: 'ja-JP', name: '日本語', file: 'ja.json' },
      { code: 'ko', iso: 'ko-KR', name: '한국어', file: 'ko.json' },
    ],
    langDir: 'locales/',
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'i18n_redirected',
      redirectOn: 'root',
    },
  },

  // TailwindCSS：默认即可，主题色由 useTheme 在运行时通过 CSS 变量动态注入
  tailwindcss: {
    cssPath: '~/assets/css/tailwind.css',
    configPath: '~/tailwind.config',
  },

  // 开发期 typescript 严格模式；shim 关闭以让 vue-tsc 接管
  typescript: {
    strict: true,
    shim: false,
  },

  // SSR + SEO：商品详情页等需要 noscript fallback；route rules 留给 PR43+ 调
  routeRules: {
    '/': { prerender: false },
  },

  // 显式收窄 app 元信息，PR43 商品列表页会通过 useHead 覆盖
  app: {
    head: {
      htmlAttrs: { lang: 'zh-CN' },
      titleTemplate: '%s · Mall',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
    },
  },
})
