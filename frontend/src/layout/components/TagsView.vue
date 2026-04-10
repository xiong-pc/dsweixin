<template>
  <div class="tags-view-container">
    <el-scrollbar class="tags-view-wrapper">
      <router-link
        v-for="tag in tagsViewStore.visitedViews"
        :key="tag.path"
        :to="tag.fullPath"
        class="tags-view-item"
        :class="{ active: isActive(tag) }"
      >
        <span class="dot" v-if="isActive(tag)"></span>
        {{ tag.title }}
        <el-icon v-if="!tag.affix" class="close-icon" @click.prevent.stop="closeTag(tag)">
          <Close />
        </el-icon>
      </router-link>
    </el-scrollbar>
  </div>
</template>

<script setup lang="ts">
import { watch } from 'vue';
import { useRoute } from 'vue-router';
import { useTagsViewStore, type TagView } from '@/store/tagsView';

const route = useRoute();
const tagsViewStore = useTagsViewStore();

function isActive(tag: TagView) {
  return tag.path === route.path;
}

function closeTag(tag: TagView) {
  tagsViewStore.removeView(tag.path);
}

function addTags() {
  if (route.name) {
    tagsViewStore.addView(route);
  }
}

watch(() => route.path, addTags, { immediate: true });
</script>

<style lang="scss" scoped>
.tags-view-container {
  height: var(--tags-view-height);
  width: 100%;
  background: var(--el-bg-color);
  border-bottom: 1px solid var(--el-border-color-lighter);
  padding: 0 8px;
  display: flex;
  align-items: center;

  .tags-view-wrapper {
    white-space: nowrap;

    .tags-view-item {
      display: inline-flex;
      align-items: center;
      height: 26px;
      padding: 0 10px;
      font-size: 12px;
      margin-right: 4px;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      color: var(--el-text-color-regular);
      background: var(--el-fill-color-lighter);
      transition: all 0.2s;
      vertical-align: middle;
      line-height: 26px;

      &:hover {
        color: var(--el-color-primary);
        background: var(--el-color-primary-light-9);
      }

      &.active {
        background: var(--el-color-primary);
        color: #fff;

        .close-icon:hover {
          background: rgba(255, 255, 255, 0.3);
          color: #fff;
        }
      }

      .dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #fff;
        margin-right: 6px;
      }

      .close-icon {
        margin-left: 4px;
        font-size: 12px;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
        &:hover {
          background: var(--el-fill-color);
          color: var(--el-text-color-primary);
        }
      }
    }
  }
}
</style>
