<script setup lang="ts">
  /**
   * 全站底部（M11-PR43）：店铺信息 + 版权年份。
   */
  const shop = useShop()
  const year = new Date().getFullYear()
</script>

<template>
  <footer class="mt-12 border-t border-gray-200 bg-gray-50 py-8 text-sm text-gray-600">
    <div class="mx-auto max-w-7xl px-4">
      <p>© {{ year }} {{ shop?.name || 'Shop' }}. {{ $t('footer.rights') }}</p>
      <p v-if="shop" class="mt-1 text-xs text-gray-400">
        {{ shop.locale }} · {{ shop.currency }} · {{ shop.timezone }}
      </p>
    </div>
  </footer>
</template>
