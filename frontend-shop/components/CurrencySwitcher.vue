<script setup lang="ts">
  /**
   * 币种切换器（M11-PR47）。
   *
   * P0 仅展示常用 5 种币种；持久化到 cookie；汇率换算等待 M02 ExchangeRate 接入。
   * 当前页面价格仍以 shop.currency 显示（避免给用户错误印象），切换主要为后续 PR 准备。
   */
  const currency = useCurrency()
  const shop = useShop()

  const SUPPORTED = ['USD', 'CNY', 'JPY', 'KRW', 'EUR'] as const

  const value = computed({
    get: () => currency.value || shop.value?.currency || 'USD',
    set: (v: string) => {
      currency.value = v
    },
  })
</script>

<template>
  <select
    v-model="value"
    aria-label="currency"
    class="cursor-pointer rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:border-gray-400 focus:border-primary focus:outline-none"
  >
    <option v-for="code in SUPPORTED" :key="code" :value="code">
      {{ code }}
    </option>
  </select>
</template>
