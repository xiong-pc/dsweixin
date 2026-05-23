<script setup lang="ts">
  import type { CheckoutAddress } from '~/types/cart'

  /**
   * 收货 / 账单地址表单（M11-PR45）。
   *
   * - v-model:address 双向绑定
   * - 必填字段对齐 backend/PlaceOrderRequest 验证规则
   *   * country_code (2 字母)、street、contact_name、contact_phone
   *   * province / city / district / postal_code 选填
   *   * contact_email 选填，校验 email 格式
   * - 错误展示交给父组件，子表单仅做轻量本地校验提示
   */
  defineProps<{
    title?: string
  }>()

  const address = defineModel<CheckoutAddress>('address', {
    default: () => ({
      country_code: '',
      province: '',
      city: '',
      district: '',
      street: '',
      postal_code: '',
      contact_name: '',
      contact_phone: '',
      contact_email: '',
    }),
  })
</script>

<template>
  <fieldset class="space-y-3 rounded border border-gray-200 p-4">
    <legend v-if="title" class="px-2 text-sm font-semibold text-gray-700">{{ title }}</legend>

    <div class="grid grid-cols-2 gap-3">
      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ $t('address.country') }} *</span>
        <input
          v-model="address.country_code"
          type="text"
          maxlength="2"
          required
          autocomplete="country"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>
      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ $t('address.postal_code') }}</span>
        <input
          v-model="address.postal_code"
          type="text"
          autocomplete="postal-code"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>
      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ $t('address.province') }}</span>
        <input
          v-model="address.province"
          type="text"
          autocomplete="address-level1"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>
      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ $t('address.city') }}</span>
        <input
          v-model="address.city"
          type="text"
          autocomplete="address-level2"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>
      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ $t('address.district') }}</span>
        <input
          v-model="address.district"
          type="text"
          autocomplete="address-level3"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>
    </div>

    <label class="block">
      <span class="mb-1 block text-xs text-gray-600">{{ $t('address.street') }} *</span>
      <input
        v-model="address.street"
        type="text"
        required
        autocomplete="street-address"
        class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
      />
    </label>

    <div class="grid grid-cols-2 gap-3">
      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ $t('address.contact_name') }} *</span>
        <input
          v-model="address.contact_name"
          type="text"
          required
          autocomplete="name"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>
      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ $t('address.contact_phone') }} *</span>
        <input
          v-model="address.contact_phone"
          type="tel"
          required
          autocomplete="tel"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>
    </div>

    <label class="block">
      <span class="mb-1 block text-xs text-gray-600">{{ $t('address.contact_email') }}</span>
      <input
        v-model="address.contact_email"
        type="email"
        autocomplete="email"
        class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
      />
    </label>
  </fieldset>
</template>
