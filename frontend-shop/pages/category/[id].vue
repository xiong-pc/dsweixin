<script setup lang="ts">
  import type { PaginatedList, ShopCategory, ShopProduct } from '~/types/catalog'

  /**
   * 类目商品列表（M11-PR43）：根据 :id 过滤当前店铺的商品。
   *
   * - useFetch / useAsyncData 触发 SSR；翻页通过 watch query 重新拉
   * - 找不到类目时展示通用空态 + 404 状态码（搜索引擎不收录）
   * - SEO：类目名 → title，类目描述 → description
   */
  const route = useRoute()
  const { t } = useI18n()

  const categoryId = computed(() => Number(route.params.id))

  const page = useState<number>(`category-${categoryId.value}-page`, () => 1)
  const pageSize = 24

  const { data: categoriesData } = await useAsyncData<ShopCategory[]>('shop-categories', () =>
    useApi<ShopCategory[]>('shop/categories'),
  )
  const category = computed(
    () => categoriesData.value?.find((c) => c.id === categoryId.value) ?? null,
  )

  const { data, pending } = await useAsyncData<PaginatedList<ShopProduct>>(
    () => `category-${categoryId.value}-products-${page.value}`,
    () =>
      useApi<PaginatedList<ShopProduct>>('shop/products', {
        query: { category_id: categoryId.value, pageNum: page.value, pageSize },
      }),
    {
      watch: [categoryId, page],
      default: () => ({ list: [], total: 0, page: 1, pageSize }),
    },
  )

  const products = computed(() => data.value?.list ?? [])
  const totalPages = computed(() => Math.max(1, Math.ceil((data.value?.total ?? 0) / pageSize)))

  // 类目缺失或被禁用 → 标记 404 状态码（不影响渲染）
  if (!category.value) {
    setResponseStatus(404)
  }

  const title = computed(() => category.value?.name || t('category.unknown'))
  const description = computed(() => category.value?.description || t('category.fallback_desc'))

  useHead({
    title,
    meta: [
      { name: 'description', content: description },
      { property: 'og:title', content: title },
      { property: 'og:description', content: description },
      { property: 'og:type', content: 'website' },
    ],
  })

  function nextPage() {
    if (page.value < totalPages.value) page.value += 1
  }
  function prevPage() {
    if (page.value > 1) page.value -= 1
  }
</script>

<template>
  <section class="mx-auto max-w-7xl px-4 py-10">
    <header class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">{{ title }}</h1>
      <p v-if="description" class="mt-2 max-w-3xl text-gray-600">{{ description }}</p>
    </header>

    <p v-if="pending" class="text-gray-500">{{ t('common.loading') }}</p>
    <p v-else-if="!category" class="text-red-500">{{ t('category.unknown') }}</p>
    <p v-else-if="products.length === 0" class="text-gray-500">{{ t('category.empty') }}</p>

    <div
      v-else
      class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 lg:gap-6"
    >
      <ProductCard v-for="p in products" :key="p.id" :product="p" />
    </div>

    <!-- 分页：极简 prev/next，PR45 之前不需要花哨的页码 -->
    <nav v-if="totalPages > 1" class="mt-8 flex items-center justify-center gap-4 text-sm">
      <button
        type="button"
        class="rounded border border-gray-300 px-3 py-1 disabled:opacity-50"
        :disabled="page <= 1"
        @click="prevPage"
      >
        {{ t('pagination.prev') }}
      </button>
      <span class="text-gray-600">{{ page }} / {{ totalPages }}</span>
      <button
        type="button"
        class="rounded border border-gray-300 px-3 py-1 disabled:opacity-50"
        :disabled="page >= totalPages"
        @click="nextPage"
      >
        {{ t('pagination.next') }}
      </button>
    </nav>
  </section>
</template>
