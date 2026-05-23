<script setup lang="ts">
  import type { ShopProduct } from '~/types/catalog'

  /**
   * 商品卡片（M11-PR43）：列表页 / 首页公共构件。
   *
   * - 链接到 PR44 的商品详情页（slug 优先，回退 id）
   * - 价格使用店铺基础货币展示，币种切换由 PR47 处理
   * - 图片懒加载 + alt 文本（SEO 友好）
   */
  const props = defineProps<{
    product: ShopProduct
  }>()

  const localePath = useLocalePath()

  const detailLink = computed(() => {
    const path = props.product.slug
      ? `/product/${props.product.slug}`
      : `/product/${props.product.id}`
    return localePath(path)
  })

  const formattedPrice = computed(() => {
    const num = Number(props.product.base_price)
    if (!Number.isFinite(num)) return ''
    // 简单格式化；PR47 会引入 Intl.NumberFormat({ style: 'currency' })
    return `${props.product.base_currency} ${num.toFixed(2)}`
  })
</script>

<template>
  <NuxtLink
    :to="detailLink"
    class="group block overflow-hidden rounded border border-gray-200 transition hover:shadow-md"
  >
    <div class="aspect-square w-full overflow-hidden bg-gray-100">
      <img
        v-if="product.cover_image"
        :src="product.cover_image"
        :alt="product.name"
        loading="lazy"
        class="h-full w-full object-cover transition group-hover:scale-105"
      />
      <div v-else class="flex h-full items-center justify-center text-gray-400">
        {{ $t('common.no_image') }}
      </div>
    </div>
    <div class="space-y-1 p-3">
      <h3 class="line-clamp-2 text-sm font-medium text-gray-900 group-hover:text-primary">
        {{ product.name || product.slug }}
      </h3>
      <p class="text-base font-semibold text-primary">{{ formattedPrice }}</p>
    </div>
  </NuxtLink>
</template>
