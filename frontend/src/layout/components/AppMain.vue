<template>
  <section class="app-main">
    <router-view v-slot="{ Component }">
      <transition name="fade-transform" mode="out-in">
        <keep-alive>
          <component :is="Component" :key="route.path" />
        </keep-alive>
      </transition>
    </router-view>
  </section>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
const route = useRoute();
</script>

<style lang="scss" scoped>
.app-main {
  flex: 1;
  padding: 0;
  overflow: auto;
  min-height: calc(100vh - var(--navbar-height) - var(--tags-view-height));
  background: var(--el-bg-color-page);
}

.fade-transform-enter-active,
.fade-transform-leave-active {
  transition: all 0.25s ease;
}

.fade-transform-enter-from {
  opacity: 0;
  transform: translateX(-20px);
}

.fade-transform-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>
