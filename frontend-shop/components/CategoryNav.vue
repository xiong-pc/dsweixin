<script setup lang="ts">
  import type { ShopCategory } from '~/types/catalog'

  /**
   * 类目水平导航（M11-PR43）。
   *
   * 仅展示一级类目（parent_id=0）；下钻交互留给 PR43+ 后续完善。
   */
  const props = defineProps<{
    categories: ShopCategory[]
  }>()

  const localePath = useLocalePath()
  const topLevel = computed(() => props.categories.filter((c) => c.parent_id === 0))
</script>

<template>
  <nav v-if="topLevel.length" class="border-y border-gray-100 bg-white">
    <div class="mx-auto flex max-w-7xl gap-6 overflow-x-auto px-4 py-3">
      <NuxtLink
        v-for="cat in topLevel"
        :key="cat.id"
        :to="localePath(`/category/${cat.id}`)"
        class="whitespace-nowrap text-sm text-gray-700 hover:text-primary"
      >
        {{ cat.name || cat.code }}
      </NuxtLink>
    </div>
  </nav>
</template>
