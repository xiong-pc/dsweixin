<script setup lang="ts">
  import type { CheckoutAddress } from '~/types/cart'

  /**
   * 地址簿管理（M11-PR46）：复用 GET/POST/PUT/DELETE /shop/me/addresses。
   *
   * - 列表 + inline 表单（新建 / 编辑共用 AddressForm 组件）
   * - 设置默认地址通过 PUT { ...address, is_default: true } 实现
   * - 后端按 is_default desc / id desc 排序
   */
  definePageMeta({ middleware: ['auth'] })

  const localePath = useLocalePath()
  const { t } = useI18n()

  interface AddressRow extends CheckoutAddress {
    id: number
    label: string
    is_default: number
  }

  const list = ref<AddressRow[]>([])
  const loading = ref(false)
  const errorMsg = ref<string | null>(null)
  const editingId = ref<number | null>(null) // null = 新建；number = 编辑
  const showForm = ref(false)

  const formAddress = ref<CheckoutAddress>(emptyAddress())
  const formIsDefault = ref(false)
  const formLabel = ref('')

  function emptyAddress(): CheckoutAddress {
    return {
      country_code: '',
      province: '',
      city: '',
      district: '',
      street: '',
      postal_code: '',
      contact_name: '',
      contact_phone: '',
      contact_email: '',
    }
  }

  async function fetchList() {
    loading.value = true
    errorMsg.value = null
    try {
      list.value = await useApi<AddressRow[]>('shop/me/addresses')
    } catch (e) {
      errorMsg.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  function openCreate() {
    editingId.value = null
    formAddress.value = emptyAddress()
    formIsDefault.value = false
    formLabel.value = ''
    showForm.value = true
  }

  function openEdit(row: AddressRow) {
    editingId.value = row.id
    formAddress.value = {
      country_code: row.country_code,
      province: row.province ?? '',
      city: row.city ?? '',
      district: row.district ?? '',
      street: row.street,
      postal_code: row.postal_code ?? '',
      contact_name: row.contact_name,
      contact_phone: row.contact_phone,
      contact_email: row.contact_email ?? '',
    }
    formLabel.value = row.label
    formIsDefault.value = row.is_default === 1
    showForm.value = true
  }

  function closeForm() {
    showForm.value = false
    editingId.value = null
  }

  async function submit() {
    errorMsg.value = null
    const body = {
      ...formAddress.value,
      label: formLabel.value || undefined,
      is_default: formIsDefault.value,
    }
    try {
      if (editingId.value === null) {
        await useApi('shop/me/addresses', { method: 'POST', body })
      } else {
        await useApi(`shop/me/addresses/${editingId.value}`, { method: 'PUT', body })
      }
      await fetchList()
      closeForm()
    } catch (e) {
      errorMsg.value = (e as Error).message
    }
  }

  async function setDefault(id: number) {
    errorMsg.value = null
    try {
      await useApi(`shop/me/addresses/${id}`, {
        method: 'PUT',
        body: { is_default: true },
      })
      await fetchList()
    } catch (e) {
      errorMsg.value = (e as Error).message
    }
  }

  async function remove(id: number) {
    if (!confirm(t('account.confirm_delete_address'))) return
    errorMsg.value = null
    try {
      await useApi(`shop/me/addresses/${id}`, { method: 'DELETE' })
      await fetchList()
    } catch (e) {
      errorMsg.value = (e as Error).message
    }
  }

  onMounted(() => {
    fetchList()
  })

  useHead({ title: t('account.addresses_title') })
</script>

<template>
  <section class="mx-auto max-w-3xl px-4 py-10">
    <header class="mb-6 flex items-baseline justify-between">
      <h1 class="text-2xl font-bold text-gray-900">{{ t('account.addresses_title') }}</h1>
      <NuxtLink :to="localePath('/account')" class="text-sm text-gray-500 hover:underline">
        {{ t('account.back') }}
      </NuxtLink>
    </header>

    <p v-if="errorMsg" class="mb-4 text-sm text-red-500">{{ errorMsg }}</p>

    <button
      v-if="!showForm"
      type="button"
      class="mb-4 rounded bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
      @click="openCreate"
    >
      {{ t('account.add_address') }}
    </button>

    <!-- 表单 -->
    <form v-if="showForm" class="mb-6 space-y-4" @submit.prevent="submit">
      <CheckoutAddressForm v-model:address="formAddress" :title="t('account.address_form')" />

      <div class="flex flex-wrap items-center gap-4">
        <label class="flex items-center gap-2 text-sm">
          <input
            v-model="formLabel"
            type="text"
            :placeholder="t('account.address_label')"
            class="rounded border border-gray-300 px-2 py-1 text-sm focus:border-primary focus:outline-none"
          />
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="formIsDefault" type="checkbox" />
          {{ t('account.set_default') }}
        </label>
      </div>

      <div class="flex justify-end gap-3">
        <button
          type="button"
          class="rounded border border-gray-300 px-4 py-2 text-sm hover:border-gray-400"
          @click="closeForm"
        >
          {{ t('common.cancel') }}
        </button>
        <button
          type="submit"
          class="rounded bg-primary px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
        >
          {{ t('common.save') }}
        </button>
      </div>
    </form>

    <p v-if="loading && list.length === 0" class="text-gray-500">{{ t('common.loading') }}</p>
    <p v-else-if="list.length === 0" class="text-gray-500">
      {{ t('account.addresses_empty') }}
    </p>

    <ul v-else class="space-y-3">
      <li v-for="addr in list" :key="addr.id" class="rounded border border-gray-200 p-4">
        <div class="flex items-baseline justify-between">
          <p class="font-semibold">
            <span v-if="addr.label">{{ addr.label }} · </span>
            {{ addr.contact_name }} · {{ addr.contact_phone }}
            <span
              v-if="addr.is_default === 1"
              class="ml-2 rounded bg-primary/10 px-2 py-0.5 text-xs text-primary"
            >
              {{ t('account.is_default') }}
            </span>
          </p>
          <div class="flex shrink-0 gap-3 text-xs">
            <button
              v-if="addr.is_default !== 1"
              type="button"
              class="text-primary hover:underline"
              @click="setDefault(addr.id)"
            >
              {{ t('account.set_default') }}
            </button>
            <button type="button" class="text-gray-500 hover:text-gray-700" @click="openEdit(addr)">
              {{ t('account.edit') }}
            </button>
            <button type="button" class="text-gray-500 hover:text-red-500" @click="remove(addr.id)">
              {{ t('account.delete') }}
            </button>
          </div>
        </div>
        <p class="mt-2 text-sm text-gray-700">
          {{ addr.country_code }} {{ addr.province }} {{ addr.city }} {{ addr.district }}
        </p>
        <p class="text-sm text-gray-700">{{ addr.street }} {{ addr.postal_code }}</p>
        <p v-if="addr.contact_email" class="text-xs text-gray-500">{{ addr.contact_email }}</p>
      </li>
    </ul>
  </section>
</template>
