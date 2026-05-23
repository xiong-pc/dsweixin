<script setup lang="ts">
  // 全局 tenant 中间件已在 middleware/tenant.global.ts 内解析了店铺配置；
  // 这里仅渲染当前路由对应页面，layouts/pages 由 PR43+ 填充。
</script>

<template>
  <div>
    <NuxtPage />
  </div>
</template>
