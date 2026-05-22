<script setup lang="ts">
  import { useAuthStore } from '~/stores/auth'

  /**
   * 我的中心首页（M11-PR46）：profile 概览 + 入口跳转 + 退出登录。
   */
  definePageMeta({ middleware: ['auth'] })

  const auth = useAuthStore()
  const router = useRouter()
  const localePath = useLocalePath()
  const { t } = useI18n()

  useHead({ title: t('account.title') })

  async function logout() {
    await auth.logout()
    await router.push(localePath('/'))
  }
</script>

<template>
  <section class="mx-auto max-w-3xl px-4 py-10">
    <h1 class="text-2xl font-bold text-gray-900">{{ t('account.title') }}</h1>

    <div class="mt-6 rounded border border-gray-200 p-6">
      <p class="text-lg font-semibold">
        {{ auth.profile?.name || auth.profile?.email || auth.profile?.phone }}
      </p>
      <p v-if="auth.profile?.email" class="mt-1 text-sm text-gray-500">
        {{ auth.profile.email }}
      </p>
      <p v-if="auth.profile?.phone" class="mt-1 text-sm text-gray-500">
        {{ auth.profile.phone }}
      </p>
    </div>

    <nav class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
      <NuxtLink
        :to="localePath('/account/orders')"
        class="rounded border border-gray-200 p-4 text-sm hover:border-primary hover:text-primary"
      >
        <span class="block font-semibold">{{ t('account.orders_link') }}</span>
        <span class="text-xs text-gray-500">{{ t('account.orders_desc') }}</span>
      </NuxtLink>
      <NuxtLink
        :to="localePath('/account/addresses')"
        class="rounded border border-gray-200 p-4 text-sm hover:border-primary hover:text-primary"
      >
        <span class="block font-semibold">{{ t('account.addresses_link') }}</span>
        <span class="text-xs text-gray-500">{{ t('account.addresses_desc') }}</span>
      </NuxtLink>
      <button
        type="button"
        class="rounded border border-gray-200 p-4 text-left text-sm hover:border-red-300 hover:text-red-600"
        @click="logout"
      >
        <span class="block font-semibold">{{ t('account.logout_link') }}</span>
        <span class="text-xs text-gray-500">{{ t('account.logout_desc') }}</span>
      </button>
    </nav>
  </section>
</template>
