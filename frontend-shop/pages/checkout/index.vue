<script setup lang="ts">
  import type { CheckoutAddress } from '~/types/cart'
  import { useCartStore } from '~/stores/cart'

  /**
   * 结账第 1 步：填写收货地址（M11-PR45）。
   *
   * - 把 address 存入 useState('checkout.address') 跨页传递
   * - 提交后 push 到 /checkout/payment
   * - 购物车空时直接重定向首页
   */
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

  useHead({ title: t('checkout.address_title') })

  const address = useState<CheckoutAddress>('checkout.address', () => ({
    country_code: '',
    province: '',
    city: '',
    district: '',
    street: '',
    postal_code: '',
    contact_name: '',
    contact_phone: '',
    contact_email: '',
  }))

  const errorMsg = ref<string | null>(null)

  function isValid(): boolean {
    return Boolean(
      address.value.country_code &&
      address.value.country_code.length === 2 &&
      address.value.street &&
      address.value.contact_name &&
      address.value.contact_phone,
    )
  }

  async function submit() {
    errorMsg.value = null
    if (!isValid()) {
      errorMsg.value = t('checkout.address_invalid')
      return
    }
    await router.push(localePath('/checkout/payment'))
  }
</script>

<template>
  <section class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900">{{ t('checkout.address_title') }}</h1>
    <p class="mt-2 text-sm text-gray-500">{{ t('checkout.address_subtitle') }}</p>

    <form class="mt-6 space-y-6" @submit.prevent="submit">
      <CheckoutAddressForm v-model:address="address" :title="t('checkout.shipping_address')" />

      <p v-if="errorMsg" class="text-sm text-red-500">{{ errorMsg }}</p>

      <div class="flex justify-end gap-3">
        <NuxtLink
          :to="localePath('/cart')"
          class="rounded border border-gray-300 px-4 py-2 text-sm hover:border-gray-400"
        >
          {{ t('checkout.back_to_cart') }}
        </NuxtLink>
        <button
          type="submit"
          class="rounded bg-primary px-6 py-2 text-sm font-semibold text-white hover:opacity-90"
        >
          {{ t('checkout.continue_to_payment') }}
        </button>
      </div>
    </form>
  </section>
</template>
