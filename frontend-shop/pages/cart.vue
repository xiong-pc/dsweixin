<script setup lang="ts">
  import { useCartStore } from '~/stores/cart'

  /**
   * 购物车页（M11-PR45）：列表 + 数量调整 + 删除 + 跳转结账。
   *
   * SSR 不预拉（购物车随会话不同），onMounted 触发 fetch；中间状态展示骨架。
   */
  const cart = useCartStore()
  const { t, locale: localeRef } = useI18n()
  const localePath = useLocalePath()

  onMounted(() => {
    cart.fetch()
  })

  useHead({
    title: t('cart.title'),
  })

  function nameOf(item: {
    product: { translations: Array<{ locale: string; name: string }> } | null
  }): string {
    if (!item.product) return ''
    const tr =
      item.product.translations.find((x) => x.locale === localeRef.value) ??
      item.product.translations[0]
    return tr?.name ?? ''
  }

  function specSummary(item: {
    variant: {
      specification_values: Array<{ translations: Array<{ locale: string; name: string }> }>
    } | null
  }): string {
    if (!item.variant) return ''
    return item.variant.specification_values
      .map((sv) => {
        const tr = sv.translations.find((x) => x.locale === localeRef.value) ?? sv.translations[0]
        return tr?.name ?? ''
      })
      .filter(Boolean)
      .join(' / ')
  }

  function lineTotal(item: {
    quantity: number
    variant: { price: string | number } | null
  }): string {
    if (!item.variant) return '0.00'
    const num = Number(item.variant.price) * item.quantity
    return Number.isFinite(num) ? num.toFixed(2) : '0.00'
  }

  async function changeQty(itemId: number, qty: number) {
    if (qty < 1) return
    await cart.updateItem(itemId, qty)
  }
</script>

<template>
  <section class="mx-auto max-w-5xl px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900">{{ t('cart.title') }}</h1>

    <p v-if="cart.loading && cart.items.length === 0" class="mt-6 text-gray-500">
      {{ t('common.loading') }}
    </p>
    <p v-else-if="cart.lastError" class="mt-6 text-red-500">{{ cart.lastError }}</p>
    <div v-else-if="cart.items.length === 0" class="mt-10 text-center">
      <p class="text-gray-500">{{ t('cart.empty') }}</p>
      <NuxtLink :to="localePath('/')" class="mt-4 inline-block text-primary hover:underline">
        {{ t('cart.go_shopping') }}
      </NuxtLink>
    </div>

    <ul v-else class="mt-6 divide-y divide-gray-200 rounded border border-gray-200">
      <li v-for="item in cart.items" :key="item.id" class="flex gap-4 p-4">
        <img
          v-if="item.product?.cover_image"
          :src="item.product.cover_image"
          :alt="nameOf(item)"
          class="h-20 w-20 shrink-0 rounded object-cover"
        />
        <div v-else class="h-20 w-20 shrink-0 rounded bg-gray-100" />

        <div class="flex flex-1 flex-col gap-1">
          <p class="font-medium text-gray-900">{{ nameOf(item) }}</p>
          <p v-if="specSummary(item)" class="text-xs text-gray-500">{{ specSummary(item) }}</p>
          <p class="text-sm text-gray-700">
            {{ cart.currency }} {{ Number(item.variant?.price ?? 0).toFixed(2) }}
          </p>
        </div>

        <div class="flex shrink-0 flex-col items-end justify-between">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="h-7 w-7 rounded border border-gray-300 hover:border-gray-400"
              :disabled="item.quantity <= 1 || cart.loading"
              aria-label="decrease"
              @click="changeQty(item.id, item.quantity - 1)"
            >
              −
            </button>
            <span class="w-8 text-center text-sm">{{ item.quantity }}</span>
            <button
              type="button"
              class="h-7 w-7 rounded border border-gray-300 hover:border-gray-400"
              :disabled="cart.loading"
              aria-label="increase"
              @click="changeQty(item.id, item.quantity + 1)"
            >
              +
            </button>
          </div>
          <button
            type="button"
            class="text-xs text-gray-400 hover:text-red-500"
            :disabled="cart.loading"
            @click="cart.removeItem(item.id)"
          >
            {{ t('cart.remove') }}
          </button>
          <p class="text-sm font-semibold">{{ cart.currency }} {{ lineTotal(item) }}</p>
        </div>
      </li>
    </ul>

    <div v-if="cart.items.length > 0" class="mt-6 flex items-center justify-between">
      <p class="text-lg font-semibold">
        {{ t('cart.subtotal') }}：{{ cart.currency }} {{ cart.subtotal.toFixed(2) }}
      </p>
      <NuxtLink
        :to="localePath('/checkout')"
        class="rounded-md bg-primary px-6 py-3 text-base font-semibold text-white transition hover:opacity-90"
      >
        {{ t('cart.checkout') }}
      </NuxtLink>
    </div>
  </section>
</template>
