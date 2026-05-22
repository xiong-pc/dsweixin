<script setup lang="ts">
  /**
   * 语言切换器（M11-PR47）：使用 nuxt-i18n 的 switchLocalePath 切换路由前缀。
   *
   * - 简易 select；无需 dropdown 也能 hover 显示全部语言
   * - 触发 navigate 后 nuxt-i18n 会自动重写 URL（保持当前页面 path）
   */
  interface I18nLocale {
    code: string
    name?: string
    iso?: string
  }

  const { locale, locales } = useI18n()
  const switchLocalePath = useSwitchLocalePath()
  const router = useRouter()

  const currentLocales = computed<I18nLocale[]>(() => {
    const raw = Array.isArray(locales.value) ? locales.value : []
    return raw as unknown as I18nLocale[]
  })

  function changeTo(code: string) {
    if (code === locale.value) return
    const path = switchLocalePath(code as 'zh-CN' | 'en' | 'ja' | 'ko')
    if (path) router.push(path)
  }
</script>

<template>
  <select
    :value="locale"
    aria-label="locale"
    class="cursor-pointer rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:border-gray-400 focus:border-primary focus:outline-none"
    @change="changeTo(($event.target as HTMLSelectElement).value)"
  >
    <option v-for="l in currentLocales" :key="l.code" :value="l.code">
      {{ l.name || l.code }}
    </option>
  </select>
</template>
