<script setup lang="ts">
  import type { PaginatedList, ShopProduct } from '~/types/catalog'

  /**
   * 商城首页（M11-PR43）：SSR + 商品列表 + SEO meta。
   *
   * - useAsyncData 让 SSR 阶段就拿到商品列表；客户端 hydration 复用，避免重复请求
   * - useHead 注入 title / description / og:* 三件套
   */
  const shop = useShop()
  const { t } = useI18n()

  const { data, pending, error } = await useAsyncData<PaginatedList<ShopProduct>>(
    'home-products',
    () => useApi<PaginatedList<ShopProduct>>('products', { query: { pageSize: 12 } }),
    { default: () => ({ list: [], total: 0, page: 1, pageSize: 12 }) },
  )

  const products = computed(() => data.value?.list ?? [])

  // SEO：店铺名作为标题前缀，避免与 nuxt.config 的 titleTemplate 冲突
  const seoTitle = computed(() => shop.value?.name ?? t('home.title'))
  const seoDescription = computed(() => `${shop.value?.name ?? ''} · ${t('home.subtitle')}`.trim())

  useHead({
    title: seoTitle.value,
    meta: [
      { name: 'description', content: seoDescription.value },
      { property: 'og:title', content: seoTitle.value },
      { property: 'og:description', content: seoDescription.value },
      { property: 'og:type', content: 'website' },
    ],
  })
</script>

<template>
  <div>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-primary/10 to-secondary/10">
      <div class="mx-auto max-w-7xl px-4 py-16 text-center">
        <h1 class="text-4xl font-bold text-gray-900 sm:text-5xl">
          {{ shop?.name || t('home.title') }}
        </h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600">
          {{ t('home.subtitle') }}
        </p>
      </div>
    </section>

    <!-- 最新商品 -->
    <section class="mx-auto max-w-7xl px-4 py-10">
      <div class="mb-6 flex items-baseline justify-between">
        <h2 class="text-2xl font-bold text-gray-900">{{ t('home.latest') }}</h2>
        <span class="text-sm text-gray-500">
          {{ t('home.total', { count: data?.total ?? 0 }) }}
        </span>
      </div>

      <p v-if="pending" class="text-gray-500">{{ t('common.loading') }}</p>
      <p v-else-if="error" class="text-red-500">{{ t('common.error') }}</p>
      <p v-else-if="products.length === 0" class="text-gray-500">
        {{ t('home.empty') }}
      </p>
      <div
        v-else
        class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 lg:gap-6"
      >
        <ProductCard v-for="p in products" :key="p.id" :product="p" />
      </div>
    </section>
  </div>
</template>
