import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { RouteLocationNormalized } from 'vue-router';

export interface TagView {
  name: string;
  path: string;
  fullPath: string;
  title: string;
  affix?: boolean;
  meta?: any;
}

export const useTagsViewStore = defineStore('tagsView', () => {
  const visitedViews = ref<TagView[]>([]);

  function addView(view: RouteLocationNormalized) {
    if (visitedViews.value.some(v => v.path === view.path)) return;
    visitedViews.value.push({
      name: view.name as string,
      path: view.path,
      fullPath: view.fullPath,
      title: (view.meta?.title as string) || 'no-name',
      affix: view.meta?.affix,
      meta: view.meta,
    });
  }

  function removeView(path: string) {
    const index = visitedViews.value.findIndex(v => v.path === path);
    if (index > -1) visitedViews.value.splice(index, 1);
  }

  function removeOtherViews(path: string) {
    visitedViews.value = visitedViews.value.filter(v => v.affix || v.path === path);
  }

  function removeAllViews() {
    visitedViews.value = visitedViews.value.filter(v => v.affix);
  }

  return { visitedViews, addView, removeView, removeOtherViews, removeAllViews };
});
