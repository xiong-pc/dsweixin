<script setup lang="ts">
  /**
   * 支付方式选择器（M11-PR45）。
   *
   * 当前 P0 仅展示两个内置选项：Stripe / 微信支付。真实 PaymentMethod 列表（M06）来自后端
   * `/api/v1/system/payment-methods` 等管理端点，PR47 之后接入；这里先 hardcode 减少耦合。
   *
   * v-model:method 同步选中标识（"stripe" | "wechat"）。
   */
  type PaymentMethodCode = 'stripe' | 'wechat'

  const method = defineModel<PaymentMethodCode | null>('method', { default: null })

  const options: Array<{ code: PaymentMethodCode; labelKey: string; descKey: string }> = [
    {
      code: 'stripe',
      labelKey: 'payment.stripe.label',
      descKey: 'payment.stripe.desc',
    },
    {
      code: 'wechat',
      labelKey: 'payment.wechat.label',
      descKey: 'payment.wechat.desc',
    },
  ]
</script>

<template>
  <fieldset class="space-y-2">
    <legend class="text-sm font-semibold text-gray-700">{{ $t('payment.title') }}</legend>
    <label
      v-for="opt in options"
      :key="opt.code"
      class="flex cursor-pointer items-start gap-3 rounded border border-gray-300 p-3 transition hover:border-primary"
      :class="method === opt.code ? 'border-primary bg-primary/5' : ''"
    >
      <input
        v-model="method"
        type="radio"
        :value="opt.code"
        :name="'payment-method'"
        class="mt-1 accent-primary"
      />
      <div class="flex-1">
        <p class="text-sm font-medium text-gray-900">{{ $t(opt.labelKey) }}</p>
        <p class="text-xs text-gray-500">{{ $t(opt.descKey) }}</p>
      </div>
    </label>
  </fieldset>
</template>
