<script setup lang="ts">
  /**
   * 商品图片画廊（M11-PR44）：主图 + 缩略图切换。
   *
   * - 极简实现：state 由 `ref` 管理当前选中图，无需 store
   * - 缩略图列表 fallback：images 为空时退化为单张 cover
   * - 主图加 `eager` loading（首屏关键 LCP），缩略图 lazy
   */
  const props = defineProps<{
    images: string[]
    alt: string
  }>()

  const allImages = computed(() => (props.images.length > 0 ? props.images : []))
  const active = ref(0)

  watch(allImages, () => {
    active.value = 0
  })

  function select(index: number) {
    active.value = index
  }
</script>

<template>
  <div class="space-y-3">
    <div class="aspect-square w-full overflow-hidden rounded-lg bg-gray-100">
      <img
        v-if="allImages[active]"
        :src="allImages[active]"
        :alt="alt"
        loading="eager"
        class="h-full w-full object-cover"
      />
      <div v-else class="flex h-full items-center justify-center text-gray-400">
        {{ $t('common.no_image') }}
      </div>
    </div>

    <div v-if="allImages.length > 1" class="flex gap-2 overflow-x-auto pb-1">
      <button
        v-for="(src, i) in allImages"
        :key="i"
        type="button"
        class="h-16 w-16 shrink-0 overflow-hidden rounded border-2 transition"
        :class="i === active ? 'border-primary' : 'border-transparent hover:border-gray-300'"
        :aria-label="`${alt} ${i + 1}`"
        @click="select(i)"
      >
        <img
          :src="src"
          :alt="`${alt} ${i + 1}`"
          loading="lazy"
          class="h-full w-full object-cover"
        />
      </button>
    </div>
  </div>
</template>
