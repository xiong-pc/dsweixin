<script setup lang="ts">
  /**
   * 加入购物车按钮（M11-PR44 占位）：PR45 接入 Pinia cart store + Shop\CartController。
   *
   * 当前 emit('add-to-cart') 让父组件感知点击；不发请求避免破坏未实现链路。
   */
  defineProps<{
    productId: number
    disabled?: boolean
  }>()

  const emit = defineEmits<{ (event: 'add-to-cart', productId: number): void }>()

  function handleClick(productId: number) {
    emit('add-to-cart', productId)
  }
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    class="w-full rounded-md bg-primary px-6 py-3 text-base font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
    @click="handleClick(productId)"
  >
    {{ $t('product.add_to_cart') }}
  </button>
</template>
