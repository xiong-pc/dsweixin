<script setup lang="ts">
  /**
   * 规格选择器（M11-PR44 占位）：当前不接 SKU 变体，PR45 接入购物车时再展开。
   *
   * 原因：M11-PR43 的 ShopProductResource 不返回变体；先占位让详情页排版完整。
   */
  defineProps<{
    productId: number
  }>()
</script>

<template>
  <div class="rounded border border-dashed border-gray-300 p-3 text-sm text-gray-500">
    {{ $t('product.spec_selector_placeholder') }}
  </div>
</template>
