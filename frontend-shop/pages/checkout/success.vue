<script setup lang="ts">
  import type { OrderSummary } from '~/types/cart'

  /**
   * 结账完成页（M11-PR45）。
   *
   * 数据源：useState('checkout.last_order')（第 2 步下单后写入）
   * 兜底：从 query.order 读取 order_no，但当前不主动反查后端（PR46 我的订单页可看详情）。
   */
  const route = useRoute()
  const localePath = useLocalePath()
  const { t } = useI18n()

  const order = useState<OrderSummary | null>('checkout.last_order')
  const orderNo = computed(() => {
    const fromQuery = String(route.query.order ?? '')
    return order.value?.order_no || fromQuery || ''
  })

  useHead({
    title: t('checkout.success_title'),
    meta: [{ name: 'robots', content: 'noindex,nofollow' }],
  })
</script>

<template>
  <section class="mx-auto max-w-2xl px-4 py-16 text-center">
    <h1 class="text-3xl font-bold text-gray-900">{{ t('checkout.success_title') }}</h1>
    <p class="mt-3 text-gray-600">{{ t('checkout.success_subtitle') }}</p>

    <p v-if="orderNo" class="mt-6 inline-block rounded bg-gray-100 px-4 py-2 text-sm font-mono">
      {{ orderNo }}
    </p>

    <div class="mt-8 flex justify-center gap-3">
      <NuxtLink
        :to="localePath('/')"
        class="rounded border border-gray-300 px-5 py-2 text-sm hover:border-gray-400"
      >
        {{ t('checkout.continue_shopping') }}
      </NuxtLink>
      <NuxtLink
        :to="localePath('/account/orders')"
        class="rounded bg-primary px-5 py-2 text-sm font-semibold text-white hover:opacity-90"
      >
        {{ t('checkout.view_orders') }}
      </NuxtLink>
    </div>
  </section>
</template>
