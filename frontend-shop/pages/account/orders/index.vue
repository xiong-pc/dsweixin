<script setup lang="ts">
  import type { PaginatedList } from '~/types/catalog'

  /**
   * 我的订单列表（M11-PR46）：复用后端 GET /shop/me/orders。
   */
  definePageMeta({ middleware: ['auth'] })

  const localePath = useLocalePath()
  const { t } = useI18n()

  interface MyOrder {
    id: number
    order_no: string
    status: string
    currency: string
    total: string | number
    paid_at?: string | null
    created_at?: string | null
  }

  const page = useState<number>('account-orders-page', () => 1)
  const pageSize = 20

  const { data, pending } = await useAsyncData<PaginatedList<MyOrder>>(
    () => `account-orders-${page.value}`,
    () =>
      useApi<PaginatedList<MyOrder>>('shop/me/orders', {
        query: { pageNum: page.value, pageSize },
      }),
    { watch: [page], default: () => ({ list: [], total: 0, page: 1, pageSize }) },
  )

  const orders = computed(() => data.value?.list ?? [])
  const totalPages = computed(() => Math.max(1, Math.ceil((data.value?.total ?? 0) / pageSize)))

  useHead({ title: t('account.orders_title') })
</script>

<template>
  <section class="mx-auto max-w-5xl px-4 py-10">
    <header class="mb-6 flex items-baseline justify-between">
      <h1 class="text-2xl font-bold text-gray-900">{{ t('account.orders_title') }}</h1>
      <NuxtLink :to="localePath('/account')" class="text-sm text-gray-500 hover:underline">
        {{ t('account.back') }}
      </NuxtLink>
    </header>

    <p v-if="pending && orders.length === 0" class="text-gray-500">{{ t('common.loading') }}</p>
    <p v-else-if="orders.length === 0" class="text-gray-500">{{ t('account.orders_empty') }}</p>

    <ul v-else class="divide-y divide-gray-200 rounded border border-gray-200">
      <li
        v-for="order in orders"
        :key="order.id"
        class="flex items-center justify-between gap-4 p-4"
      >
        <div>
          <p class="font-mono text-sm font-medium text-gray-900">{{ order.order_no }}</p>
          <p class="mt-1 text-xs text-gray-500">{{ order.status }}</p>
        </div>
        <div class="text-right">
          <p class="text-sm font-semibold">
            {{ order.currency }} {{ Number(order.total).toFixed(2) }}
          </p>
          <NuxtLink
            :to="localePath(`/account/orders/${order.id}`)"
            class="mt-1 inline-block text-xs text-primary hover:underline"
          >
            {{ t('account.view_detail') }}
          </NuxtLink>
        </div>
      </li>
    </ul>

    <nav v-if="totalPages > 1" class="mt-6 flex items-center justify-center gap-4 text-sm">
      <button
        type="button"
        class="rounded border border-gray-300 px-3 py-1 disabled:opacity-50"
        :disabled="page <= 1"
        @click="page = page - 1"
      >
        {{ t('pagination.prev') }}
      </button>
      <span class="text-gray-600">{{ page }} / {{ totalPages }}</span>
      <button
        type="button"
        class="rounded border border-gray-300 px-3 py-1 disabled:opacity-50"
        :disabled="page >= totalPages"
        @click="page = page + 1"
      >
        {{ t('pagination.next') }}
      </button>
    </nav>
  </section>
</template>
