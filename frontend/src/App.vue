<template>
  <el-config-provider :locale="elLocale" :size="appStore.size">
    <router-view />
  </el-config-provider>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue';
import { useDark } from '@vueuse/core';
import { useI18n } from 'vue-i18n';
import zhCn from 'element-plus/es/locale/lang/zh-cn';
import en from 'element-plus/es/locale/lang/en';
import { useAppStore } from '@/store/app';

const appStore = useAppStore();
const isDark = useDark();
const { locale } = useI18n();

const elLocale = computed(() => (appStore.language === 'zh-cn' ? zhCn : en));

watch(
  () => appStore.isDark,
  (val) => {
    isDark.value = val;
  },
  { immediate: true }
);

watch(
  () => appStore.language,
  (val) => {
    locale.value = val;
    localStorage.setItem('language', val);
  }
);
</script>
