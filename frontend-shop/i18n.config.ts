/**
 * Vue I18n 运行时默认值。具体翻译文案在 `i18n/locales/{code}.json` 内。
 * `@nuxtjs/i18n` 在 build 时把 file 字段映射到 messages，因此这里只放兜底配置。
 */
export default defineI18nConfig(() => ({
  legacy: false,
  fallbackLocale: 'zh-CN',
  // 缺失键时静默回退到 fallbackLocale（不打印 warning，避免 SSR 噪音）
  missingWarn: false,
  fallbackWarn: false,
}))
