<template>
  <el-breadcrumb separator="/">
    <el-breadcrumb-item v-for="item in breadcrumbs" :key="item.path">
      <span v-if="item.redirect === 'noRedirect' || item === breadcrumbs[breadcrumbs.length - 1]">
        {{ item.meta?.title }}
      </span>
      <a v-else @click.prevent="handleLink(item)">{{ item.meta?.title }}</a>
    </el-breadcrumb-item>
  </el-breadcrumb>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute, useRouter, type RouteLocationMatched } from 'vue-router';

const route = useRoute();
const router = useRouter();
const breadcrumbs = ref<RouteLocationMatched[]>([]);

function getBreadcrumbs() {
  const matched = route.matched.filter((item) => item.meta?.title);
  breadcrumbs.value = matched;
}

function handleLink(item: RouteLocationMatched) {
  const { redirect, path } = item;
  if (redirect) {
    router.push(redirect as string);
  } else {
    router.push(path);
  }
}

watch(() => route.path, getBreadcrumbs, { immediate: true });
</script>
