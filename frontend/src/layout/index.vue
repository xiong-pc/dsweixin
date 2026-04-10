<template>
  <div :class="classObj" class="app-wrapper">
    <!-- 左侧菜单布局 -->
    <template v-if="appStore.layout === 'left'">
      <Sidebar class="sidebar-container" :class="{ 'is-collapse': !appStore.sidebarOpened }" />
      <div class="main-container" :style="{ marginLeft: sidebarWidth }">
        <Navbar />
        <TagsView />
        <AppMain />
      </div>
    </template>

    <!-- 顶部菜单布局 -->
    <template v-else-if="appStore.layout === 'top'">
      <div class="main-container" style="margin-left: 0">
        <TopNav />
        <TagsView />
        <AppMain />
      </div>
    </template>

    <!-- 混合布局 -->
    <template v-else>
      <div class="main-container" style="margin-left: 0">
        <TopNav />
        <div class="mix-content">
          <Sidebar class="sidebar-container mix-sidebar" :class="{ 'is-collapse': !appStore.sidebarOpened }" />
          <div class="mix-main" :style="{ marginLeft: sidebarWidth }">
            <TagsView />
            <AppMain />
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useAppStore } from '@/store/app';
import { useWatermark } from '@/composables/useWatermark';
import Sidebar from './components/Sidebar.vue';
import Navbar from './components/Navbar.vue';
import TopNav from './components/TopNav.vue';
import TagsView from './components/TagsView.vue';
import AppMain from './components/AppMain.vue';

const appStore = useAppStore();
useWatermark();

const classObj = computed(() => ({
  'sidebar-opened': appStore.sidebarOpened,
  'sidebar-closed': !appStore.sidebarOpened,
  'layout-top': appStore.layout === 'top',
  'layout-mix': appStore.layout === 'mix',
}));

const sidebarWidth = computed(() =>
  appStore.sidebarOpened ? 'var(--sidebar-width)' : 'var(--sidebar-collapsed-width)'
);
</script>

<style lang="scss" scoped>
.app-wrapper {
  position: relative;
  height: 100%;
  width: 100%;
}

.sidebar-container {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1001;
}

.main-container {
  min-height: 100%;
  transition: margin-left 0.28s;
  display: flex;
  flex-direction: column;
}

.mix-content {
  display: flex;
  flex: 1;
  position: relative;
}

.mix-sidebar {
  position: fixed;
  top: var(--navbar-height);
  left: 0;
}

.mix-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  transition: margin-left 0.28s;
  min-height: calc(100vh - var(--navbar-height));
}
</style>
