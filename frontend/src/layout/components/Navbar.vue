<template>
  <div class="navbar">
    <div class="left-menu">
      <el-icon class="hamburger" @click="appStore.toggleSidebar">
        <Fold v-if="appStore.sidebarOpened" />
        <Expand v-else />
      </el-icon>
      <Breadcrumb />
    </div>
    <div class="right-menu">
      <el-tooltip :content="appStore.isDark ? '浅色模式' : '暗黑模式'" placement="bottom">
        <div class="right-menu-item" @click="appStore.isDark = !appStore.isDark">
          <el-icon><Moon v-if="!appStore.isDark" /><Sunny v-else /></el-icon>
        </div>
      </el-tooltip>

      <el-tooltip content="全屏" placement="bottom">
        <div class="right-menu-item" @click="toggleFullscreen">
          <el-icon><FullScreen /></el-icon>
        </div>
      </el-tooltip>

      <Settings />

      <el-dropdown trigger="click">
        <div class="avatar-wrapper">
          <el-avatar :size="32" :src="userStore.avatar || undefined" class="user-avatar">
            {{ userStore.nickname?.charAt(0) || 'U' }}
          </el-avatar>
          <span class="username">{{ userStore.nickname }}</span>
          <el-icon class="caret"><CaretBottom /></el-icon>
        </div>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item @click="router.push('/dashboard')">
              <el-icon><HomeFilled /></el-icon>{{ t('navbar.dashboard') }}
            </el-dropdown-item>
            <el-dropdown-item divided @click="handleLogout">
              <el-icon><SwitchButton /></el-icon>{{ t('navbar.logout') }}
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useFullscreen } from '@vueuse/core';
import { useAppStore } from '@/store/app';
import { useUserStore } from '@/store/user';
import Breadcrumb from './Breadcrumb.vue';
import Settings from './Settings.vue';

const { t } = useI18n();
const router = useRouter();
const appStore = useAppStore();
const userStore = useUserStore();
const { toggle: toggleFullscreen } = useFullscreen();

function handleLogout() {
  userStore.logout();
}
</script>

<style lang="scss" scoped>
.navbar {
  height: var(--navbar-height);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  background: var(--el-bg-color);
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.06);
  position: relative;
  z-index: 10;

  .left-menu {
    display: flex;
    align-items: center;
  }

  .hamburger {
    font-size: 20px;
    cursor: pointer;
    margin-right: 12px;
    color: var(--el-text-color-primary);
    transition: color 0.2s;
    &:hover { color: var(--el-color-primary); }
  }

  .right-menu {
    display: flex;
    align-items: center;
    gap: 4px;

    .right-menu-item {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      font-size: 18px;
      cursor: pointer;
      border-radius: 6px;
      color: var(--el-text-color-regular);
      transition: all 0.2s;
      &:hover {
        background: var(--el-fill-color);
        color: var(--el-color-primary);
      }
    }

    .avatar-wrapper {
      display: flex;
      align-items: center;
      cursor: pointer;
      padding: 0 8px;
      height: 36px;
      border-radius: 6px;
      transition: background 0.2s;
      &:hover { background: var(--el-fill-color); }

      .user-avatar {
        background: var(--el-color-primary);
        color: #fff;
        font-size: 14px;
      }
      .username {
        font-size: 14px;
        margin-left: 8px;
        color: var(--el-text-color-primary);
        max-width: 80px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      .caret {
        font-size: 12px;
        margin-left: 2px;
        color: var(--el-text-color-secondary);
      }
    }
  }
}
</style>
