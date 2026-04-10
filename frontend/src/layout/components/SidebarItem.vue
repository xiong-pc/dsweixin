<template>
  <template v-if="!item.meta?.hidden">
    <template v-if="hasOneShowingChild(item)">
      <el-menu-item :index="resolvePath(onlyOneChild.path)">
        <el-icon v-if="onlyOneChild.meta?.icon">
          <component :is="onlyOneChild.meta.icon" />
        </el-icon>
        <template #title>{{ onlyOneChild.meta?.title }}</template>
      </el-menu-item>
    </template>

    <el-sub-menu v-else :index="resolvePath(item.path)">
      <template #title>
        <el-icon v-if="item.meta?.icon">
          <component :is="item.meta.icon" />
        </el-icon>
        <span>{{ item.meta?.title }}</span>
      </template>
      <SidebarItem
        v-for="child in item.children"
        :key="child.path"
        :item="child"
        :base-path="resolvePath(child.path)"
      />
    </el-sub-menu>
  </template>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import type { RouteRecordRaw } from 'vue-router';

const props = defineProps<{
  item: RouteRecordRaw;
  basePath: string;
}>();

const onlyOneChild = ref<any>({});

function hasOneShowingChild(item: RouteRecordRaw): boolean {
  const showingChildren = (item.children || []).filter(
    (child) => !child.meta?.hidden
  );
  if (showingChildren.length === 1) {
    onlyOneChild.value = showingChildren[0];
    return true;
  }
  if (showingChildren.length === 0) {
    onlyOneChild.value = { ...item, path: '', meta: item.meta };
    return true;
  }
  return false;
}

function resolvePath(routePath: string): string {
  if (routePath.startsWith('/')) return routePath;
  if (props.basePath.startsWith('/')) {
    return props.basePath.endsWith('/')
      ? props.basePath + routePath
      : props.basePath + '/' + routePath;
  }
  return '/' + props.basePath + '/' + routePath;
}
</script>
