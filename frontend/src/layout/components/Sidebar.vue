<template>
  <div>
    <div class="logo-container">
      <img src="@/assets/logo.svg" alt="Logo" style="width: 32px; height: 32px" />
      <span v-if="appStore.sidebarOpened" class="logo-title">Vue3 Admin</span>
    </div>
    <el-scrollbar>
      <el-menu
        :default-active="activeMenu"
        :collapse="!appStore.sidebarOpened"
        :collapse-transition="false"
        :unique-opened="true"
        router
      >
        <SidebarItem
          v-for="route in constantMenuRoutes"
          :key="route.path"
          :item="route"
          :base-path="route.path"
        />
        <SidebarItem
          v-for="route in permissionStore.addedRoutes"
          :key="route.path"
          :item="route"
          :base-path="route.path"
        />
      </el-menu>
    </el-scrollbar>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAppStore } from '@/store/app';
import { usePermissionStore } from '@/store/permission';
import { constantRoutes } from '@/router';
import SidebarItem from './SidebarItem.vue';

const route = useRoute();
const appStore = useAppStore();
const permissionStore = usePermissionStore();

const activeMenu = computed(() => route.path);

const constantMenuRoutes = computed(() =>
  constantRoutes.filter((r) => r.path === '/' && r.children?.length)
);
</script>
