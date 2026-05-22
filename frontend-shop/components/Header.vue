<script setup lang="ts">
  /**
   * 全站头部（M11-PR43）：店铺名 + 主导航 + 简易语言切换占位。
   * 真正的语言/币种切换器在 PR47 实现。
   */
  const shop = useShop()
  const localePath = useLocalePath()
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
        <NuxtLink :to="localePath('/account')" class="hover:text-primary">
          {{ $t('nav.account') }}
        </NuxtLink>
      </nav>
    </div>
  </header>
</template>
