<script setup lang="ts">
  import type { CheckoutAddress, OrderSummary } from '~/types/cart'
  import { useCartStore } from '~/stores/cart'

  /**
   * 结账第 2 步：选择支付方式 + 下单（M11-PR45）。
   *
   * - 从 useState('checkout.address') 取上一步填的地址
   * - 调用 cart.placeOrder(payload)，成功后 push /checkout/success
   * - 真实支付通道接入（Stripe Checkout 重定向 / 微信 H5）由 PR47 完善
   */
  type PaymentMethodCode = 'stripe' | 'wechat'

  const cart = useCartStore()
  const localePath = useLocalePath()
  const router = useRouter()
  const { t } = useI18n()

  onMounted(async () => {
    await cart.fetch()
    if (cart.items.length === 0) {
      await router.replace(localePath('/'))
    }
  })

  useHead({ title: t('checkout.payment_title') })

  const shippingAddress = useState<CheckoutAddress | null>('checkout.address')
  const method = ref<PaymentMethodCode | null>(null)
  const submitting = ref(false)
  const errorMsg = ref<string | null>(null)

  // 兜底：第二步直接刷新进来时缺地址 → 退回第一步
  onMounted(async () => {
    if (!shippingAddress.value || !shippingAddress.value.street) {
      await router.replace(localePath('/checkout'))
    }
  })

  async function submit() {
    if (!shippingAddress.value) return
    if (!method.value) {
      errorMsg.value = t('checkout.payment_required')
      return
    }
    submitting.value = true
    errorMsg.value = null
    try {
      const order: OrderSummary = await cart.placeOrder({
        shipping_address: shippingAddress.value,
        payment_method: method.value,
      })
      // 把订单号传给 success 页面
      const successOrder = useState<OrderSummary | null>('checkout.last_order')
      successOrder.value = order
      await router.replace(localePath(`/checkout/success?order=${order.order_no}`))
    } catch (e) {
      errorMsg.value = (e as Error).message
    } finally {
      submitting.value = false
    }
  }
</script>

<template>
  <section class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900">{{ t('checkout.payment_title') }}</h1>
    <p class="mt-2 text-sm text-gray-500">{{ t('checkout.payment_subtitle') }}</p>

    <div class="mt-6 space-y-6">
      <!-- 已填写的地址回显，方便最后复核 -->
      <section
        v-if="shippingAddress"
        class="rounded border border-gray-200 p-4 text-sm text-gray-700"
      >
        <p class="font-semibold">{{ t('checkout.shipping_address') }}</p>
        <p class="mt-1 text-xs text-gray-500">
          {{ shippingAddress.contact_name }} · {{ shippingAddress.contact_phone }}
        </p>
        <p class="mt-1">
          {{ shippingAddress.country_code }} {{ shippingAddress.province }}
          {{ shippingAddress.city }} {{ shippingAddress.district }}
        </p>
        <p>{{ shippingAddress.street }} {{ shippingAddress.postal_code }}</p>
      </section>

      <CheckoutPaymentSelector v-model:method="method" />

      <!-- 简易合计；真实金额由 backend 在 place-order 时计算 -->
      <section class="rounded border border-gray-200 p-4 text-sm">
        <div class="flex justify-between">
          <span>{{ t('cart.subtotal') }}</span>
          <span>{{ cart.currency }} {{ cart.subtotal.toFixed(2) }}</span>
        </div>
        <p class="mt-2 text-xs text-gray-500">{{ t('checkout.total_note') }}</p>
      </section>

      <p v-if="errorMsg" class="text-sm text-red-500">{{ errorMsg }}</p>

      <div class="flex justify-end gap-3">
        <NuxtLink
          :to="localePath('/checkout')"
          class="rounded border border-gray-300 px-4 py-2 text-sm hover:border-gray-400"
        >
          {{ t('checkout.back_to_address') }}
        </NuxtLink>
        <button
          type="button"
          :disabled="submitting"
          class="rounded bg-primary px-6 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
          @click="submit"
        >
          <span v-if="submitting">{{ t('common.loading') }}</span>
          <span v-else>{{ t('checkout.place_order') }}</span>
        </button>
      </div>
    </div>
  </section>
</template>
