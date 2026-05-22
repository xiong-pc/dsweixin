<script setup lang="ts">
  /**
   * 我的订单详情（M11-PR46）：调用 GET /shop/me/orders/{order}。
   *
   * 只读视图：订单号 / 状态 / 商品行 / 收货地址 / 物流（如有）。
   */
  definePageMeta({ middleware: ['auth'] })

  const route = useRoute()
  const localePath = useLocalePath()
  const { t } = useI18n()

  interface OrderDetail {
    id: number
    order_no: string
    status: string
    currency: string
    subtotal: string | number
    total: string | number
    paid_at?: string | null
    created_at?: string | null
    items: Array<{
      id: number
      sku: string | null
      name_snapshot: string | null
      quantity: number
      unit_price: string | number
      line_total: string | number
    }>
    shipping_address?: {
      country_code?: string
      province?: string
      city?: string
      district?: string
      street?: string
      postal_code?: string
      contact_name?: string
      contact_phone?: string
    } | null
    shipments?: Array<{
      id: number
      carrier?: string
      tracking_no?: string
      status?: string
    }>
  }

  const orderId = computed(() => Number(route.params.id))

  const { data, pending, error } = await useAsyncData<OrderDetail | null>(
    () => `account-order-${orderId.value}`,
    async () => {
      try {
        return await useApi<OrderDetail>(`shop/me/orders/${orderId.value}`)
      } catch (_) {
        return null
      }
    },
    { watch: [orderId] },
  )

  useHead({ title: data.value?.order_no || t('account.order_detail') })
</script>

<template>
  <section class="mx-auto max-w-3xl px-4 py-10">
    <NuxtLink :to="localePath('/account/orders')" class="text-sm text-gray-500 hover:underline">
      ← {{ t('account.back_to_orders') }}
    </NuxtLink>

    <p v-if="pending" class="mt-6 text-gray-500">{{ t('common.loading') }}</p>
    <p v-else-if="error || !data" class="mt-6 text-red-500">{{ t('account.order_not_found') }}</p>

    <div v-else class="mt-4 space-y-6">
      <header class="flex items-baseline justify-between gap-4">
        <h1 class="font-mono text-xl font-bold text-gray-900">{{ data.order_no }}</h1>
        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ data.status }}</span>
      </header>

      <!-- 商品明细 -->
      <section class="rounded border border-gray-200">
        <h2 class="border-b border-gray-200 px-4 py-2 text-sm font-semibold">
          {{ t('account.items') }}
        </h2>
        <ul class="divide-y divide-gray-200">
          <li v-for="item in data.items" :key="item.id" class="flex justify-between gap-4 p-4">
            <div>
              <p class="text-sm font-medium">{{ item.name_snapshot || item.sku }}</p>
              <p class="mt-1 text-xs text-gray-500">x {{ item.quantity }}</p>
            </div>
            <p class="text-sm">{{ data.currency }} {{ Number(item.line_total).toFixed(2) }}</p>
          </li>
        </ul>
        <div class="flex justify-between border-t border-gray-200 px-4 py-3 text-sm font-semibold">
          <span>{{ t('account.total') }}</span>
          <span>{{ data.currency }} {{ Number(data.total).toFixed(2) }}</span>
        </div>
      </section>

      <!-- 收货地址 -->
      <section v-if="data.shipping_address" class="rounded border border-gray-200 p-4 text-sm">
        <h2 class="text-sm font-semibold">{{ t('account.shipping_address') }}</h2>
        <p class="mt-2 text-gray-700">
          {{ data.shipping_address.contact_name }} · {{ data.shipping_address.contact_phone }}
        </p>
        <p class="mt-1 text-gray-700">
          {{ data.shipping_address.country_code }} {{ data.shipping_address.province }}
          {{ data.shipping_address.city }} {{ data.shipping_address.district }}
        </p>
        <p class="text-gray-700">
          {{ data.shipping_address.street }} {{ data.shipping_address.postal_code }}
        </p>
      </section>

      <!-- 物流 -->
      <section
        v-if="data.shipments && data.shipments.length > 0"
        class="rounded border border-gray-200 p-4 text-sm"
      >
        <h2 class="text-sm font-semibold">{{ t('account.shipments') }}</h2>
        <ul class="mt-2 space-y-1 text-gray-700">
          <li v-for="ship in data.shipments" :key="ship.id">
            {{ ship.carrier || '-' }} · {{ ship.tracking_no || '-' }} ·
            <span class="text-xs text-gray-500">{{ ship.status }}</span>
          </li>
        </ul>
      </section>
    </div>
  </section>
</template>
