<template>
  <div class="error-page">
    <div class="error-content">
      <h1>404</h1>
      <p>页面不存在</p>
      <el-button type="primary" @click="router.push('/')">返回首页</el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router';
const router = useRouter();
</script>

<style scoped>
.error-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
}
.error-content {
  text-align: center;
}
.error-content h1 {
  font-size: 120px;
  color: var(--el-color-primary);
  margin: 0;
}
.error-content p {
  font-size: 20px;
  color: #999;
}
</style>
