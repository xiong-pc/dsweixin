<script setup lang="ts">
  import { useAuthStore } from '~/stores/auth'

  /**
   * 全站头部（M11-PR43 + PR46 加登录态）。
   *
   * - 店铺名 + 主导航
   * - 已登录展示用户名 + 我的中心入口；未登录展示登录 / 注册链接
   * - 语言/币种切换器在 PR47 实现
   */
  const shop = useShop()
  const auth = useAuthStore()
  const localePath = useLocalePath()

  // 首次挂载时尝试拉取个人资料（仅当 cookie 里已有 token 时）
  onMounted(() => {
    if (auth.token && !auth.profile) {
      auth.fetchMe()
    }
  })
</script>

<template>
  <header class="border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
      <NuxtLink :to="localePath('/')" class="text-xl font-bold text-primary">
        {{ shop?.name || 'Shop' }}
      </NuxtLink>

      <nav class="flex items-center gap-6 text-sm text-gray-700">
        <NuxtLink :to="localePath('/')" class="hover:text-primary">{{ $t('nav.home') }}</NuxtLink>
        <NuxtLink :to="localePath('/cart')" class="hover:text-primary">
          {{ $t('nav.cart') }}
        </NuxtLink>
        <NuxtLink v-if="auth.isLoggedIn" :to="localePath('/account')" class="hover:text-primary">
          {{ auth.profile?.name || auth.profile?.email || $t('nav.account') }}
        </NuxtLink>
        <template v-else>
          <NuxtLink :to="localePath('/login')" class="hover:text-primary">
            {{ $t('nav.login') }}
          </NuxtLink>
          <NuxtLink :to="localePath('/register')" class="hover:text-primary">
            {{ $t('nav.register') }}
          </NuxtLink>
        </template>
      </nav>
    </div>
  </header>
</template>
