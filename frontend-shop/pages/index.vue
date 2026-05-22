<script setup lang="ts">
  /**
   * 占位首页（M11-PR42）：仅验证脚手架可启动 SSR + i18n + tailwind + tenant 中间件。
   * 真正的首页（首屏 SEO + 类目导航 + 商品 grid）由 M11-PR43 实现。
   */
  const shop = useShop()
  const { t } = useI18n()

  useHead({
    title: t('home.title'),
  })
</script>

<template>
  <main class="min-h-screen bg-white p-8 font-sans text-gray-900">
    <h1 class="text-3xl font-bold text-primary">
      {{ t('home.title') }}
    </h1>

    <section class="mt-6 rounded border border-gray-200 p-4">
      <h2 class="text-lg font-semibold">Shop scaffolding ready</h2>
      <p v-if="shop" class="mt-2 text-sm text-gray-700">
        Tenant: <code>{{ shop.tenant_id }}</code> · Shop: <code>{{ shop.name }}</code> · Locale:
        <code>{{ shop.locale }}</code> · Currency: <code>{{ shop.currency }}</code>
      </p>
      <p v-else class="mt-2 text-sm text-gray-500">
        No shop resolved (set <code>NUXT_PUBLIC_FALLBACK_SUBDOMAIN</code> or visit via subdomain
        host).
      </p>
    </section>
  </main>
</template>
