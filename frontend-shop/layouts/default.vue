<script setup lang="ts">
  import type { ShopCategory } from '~/types/catalog'

  /**
   * 默认布局（M11-PR43）：Header + CategoryNav + 内容 slot + Footer。
   *
   * 类目数据通过 useAsyncData 在 SSR 阶段拉取一次，全站复用（key='shop-categories'）。
   */
  const { data: categoriesData } = await useAsyncData<ShopCategory[]>('shop-categories', () =>
    useApi<ShopCategory[]>('categories'),
  )
  const categories = computed(() => categoriesData.value ?? [])
</script>

<template>
  <div class="flex min-h-screen flex-col bg-white text-gray-900">
    <Header />
    <CategoryNav :categories="categories" />
    <main class="flex-1">
      <slot />
    </main>
    <Footer />
  </div>
</template>
