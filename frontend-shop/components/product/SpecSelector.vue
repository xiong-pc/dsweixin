<script setup lang="ts">
  import type { ShopProductVariant } from '~/types/catalog'

  /**
   * 规格选择器（M11-PR45）：从商品的 variants[] 中选 SKU。
   *
   * - 单变体：自动选中，不渲染 UI（避免点 1 个 chip 才能加购）
   * - 多变体：按 specification_id 分组，每组单选 chips；只有所有规格选满后才暴露 selected
   * - 缺货变体（available=0）禁用
   *
   * v-model 暴露当前选中的 variant_id；父组件据此控制加购按钮 enable / 显示价格。
   */
  const props = defineProps<{
    variants: ShopProductVariant[]
  }>()

  const selectedVariantId = defineModel<number | null>('selectedVariantId', {
    default: null,
  })

  interface SpecGroup {
    specificationId: number
    values: { id: number; code: string; name: string }[]
  }

  // 把 variants 拆出唯一的 (specification_id → values) 分组
  const specGroups = computed<SpecGroup[]>(() => {
    const groups = new Map<number, SpecGroup>()
    for (const v of props.variants) {
      for (const sv of v.specification_values) {
        if (!groups.has(sv.specification_id)) {
          groups.set(sv.specification_id, { specificationId: sv.specification_id, values: [] })
        }
        const group = groups.get(sv.specification_id)!
        if (!group.values.find((x) => x.id === sv.id)) {
          group.values.push({ id: sv.id, code: sv.code, name: sv.name })
        }
      }
    }
    return Array.from(groups.values())
  })

  // 当前每个 specification_id 选中的 spec_value_id
  const selectedSpecValueIds = ref<Record<number, number>>({})

  // 单变体捷径：直接选中
  watchEffect(() => {
    if (props.variants.length === 1) {
      selectedVariantId.value = props.variants[0].id
      const next: Record<number, number> = {}
      for (const sv of props.variants[0].specification_values) {
        next[sv.specification_id] = sv.id
      }
      selectedSpecValueIds.value = next
    }
  })

  function isSpecValueSelected(specId: number, valueId: number) {
    return selectedSpecValueIds.value[specId] === valueId
  }

  function selectSpecValue(specId: number, valueId: number) {
    selectedSpecValueIds.value = { ...selectedSpecValueIds.value, [specId]: valueId }
    resolveVariant()
  }

  function resolveVariant() {
    // 所有 spec 都选满才尝试匹配 variant
    if (Object.keys(selectedSpecValueIds.value).length !== specGroups.value.length) {
      selectedVariantId.value = null
      return
    }
    const matched = props.variants.find((v) =>
      v.specification_values.every(
        (sv) => selectedSpecValueIds.value[sv.specification_id] === sv.id,
      ),
    )
    selectedVariantId.value = matched?.id ?? null
  }

  function isSpecValueOutOfStock(specId: number, valueId: number): boolean {
    const variantsHere = props.variants.filter((v) =>
      v.specification_values.some((sv) => sv.specification_id === specId && sv.id === valueId),
    )
    if (variantsHere.length === 0) return false
    return variantsHere.every((v) => v.available <= 0)
  }
</script>

<template>
  <div v-if="specGroups.length >= 1 && variants.length > 1" class="space-y-3">
    <div v-for="group in specGroups" :key="group.specificationId" class="flex flex-wrap gap-2">
      <button
        v-for="value in group.values"
        :key="value.id"
        type="button"
        class="rounded border px-3 py-1.5 text-sm transition"
        :class="[
          isSpecValueSelected(group.specificationId, value.id)
            ? 'border-primary bg-primary/10 text-primary'
            : 'border-gray-300 text-gray-700 hover:border-gray-400',
          isSpecValueOutOfStock(group.specificationId, value.id)
            ? 'cursor-not-allowed opacity-50 line-through'
            : '',
        ]"
        :disabled="isSpecValueOutOfStock(group.specificationId, value.id)"
        @click="selectSpecValue(group.specificationId, value.id)"
      >
        {{ value.name || value.code }}
      </button>
    </div>
  </div>
</template>
