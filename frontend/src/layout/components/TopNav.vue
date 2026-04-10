<template>
  <div class="top-nav">
    <div class="top-nav-left">
      <div class="logo-area">
        <img src="@/assets/logo.svg" alt="Logo" style="width: 28px; height: 28px" />
        <span class="logo-title">Vue3 Admin</span>
      </div>
      <el-menu
        mode="horizontal"
        :default-active="activeMenu"
        :ellipsis="false"
        router
        class="top-menu"
      >
        <template v-for="route in menuRoutes" :key="route.path">
          <el-menu-item v-if="!route.meta?.hidden" :index="route.path">
            <el-icon v-if="route.meta?.icon"><component :is="route.meta.icon" /></el-icon>
            <span>{{ route.meta?.title }}</span>
          </el-menu-item>
        </template>
      </el-menu>
    </div>
    <div class="top-nav-right">
      <el-tooltip :content="appStore.isDark ? '浅色模式' : '暗黑模式'" placement="bottom">
        <el-icon class="right-menu-item" @click="appStore.isDark = !appStore.isDark">
          <Moon v-if="!appStore.isDark" />
          <Sunny v-else />
        </el-icon>
      </el-tooltip>

      <el-tooltip content="全屏" placement="bottom">
        <el-icon class="right-menu-item" @click="toggleFullscreen">
          <FullScreen />
        </el-icon>
      </el-tooltip>

      <Settings />

      <el-dropdown trigger="click">
        <div class="avatar-wrapper">
          <el-avatar :size="28" :src="userStore.avatar || undefined">
            {{ userStore.nickname?.charAt(0) || 'U' }}
          </el-avatar>
          <span class="username">{{ userStore.nickname }}</span>
          <el-icon><CaretBottom /></el-icon>
        </div>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item @click="router.push('/dashboard')">仪表盘</el-dropdown-item>
            <el-dropdown-item divided @click="userStore.logout()">退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useFullscreen } from '@vueuse/core';
import { useAppStore } from '@/store/app';
import { useUserStore } from '@/store/user';
import { usePermissionStore } from '@/store/permission';
import { constantRoutes } from '@/router';
import Settings from './Settings.vue';

const route = useRoute();
const router = useRouter();
const appStore = useAppStore();
const userStore = useUserStore();
const permissionStore = usePermissionStore();
const { toggle: toggleFullscreen } = useFullscreen();

const activeMenu = computed(() => {
  const matched = route.matched;
  if (matched.length > 1) return matched[1]?.path ?? route.path;
  return route.path;
});

const menuRoutes = computed(() => {
  const dynamicRoutes = permissionStore.addedRoutes.map((r) => ({
    path: r.path,
    meta: (r as any).meta || {},
  }));
  const staticRoutes = constantRoutes
    .filter((r) => r.path === '/' && r.children?.length)
    .flatMap((r) =>
      (r.children || []).map((child) => ({
        path: '/' + child.path,
        meta: child.meta || {},
      }))
    );
  return [...staticRoutes, ...dynamicRoutes];
});
</script>

<style lang="scss" scoped>
.top-nav {
  height: var(--navbar-height);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 15px;
  background: var(--el-bg-color);
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.08);
}

.top-nav-left {
  display: flex;
  align-items: center;
  flex: 1;
  overflow: hidden;
}

.logo-area {
  display: flex;
  align-items: center;
  margin-right: 20px;
  white-space: nowrap;
  .logo-title {
    font-size: 16px;
    font-weight: 600;
    margin-left: 8px;
  }
}

.top-menu {
  border-bottom: none !important;
  flex: 1;
  overflow: hidden;
}

.top-nav-right {
  display: flex;
  align-items: center;
  gap: 8px;
  .right-menu-item {
    font-size: 18px;
    cursor: pointer;
    padding: 6px;
    border-radius: 4px;
    &:hover { background: var(--el-fill-color-light); }
  }
  .avatar-wrapper {
    display: flex;
    align-items: center;
    cursor: pointer;
    gap: 6px;
    .username { font-size: 14px; }
  }
}
</style>
