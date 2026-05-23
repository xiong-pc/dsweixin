<script setup lang="ts">
  import { useCartStore } from '~/stores/cart'

  /**
   * 加入购物车按钮（M11-PR45）：调用 Pinia cart store 写入后端购物车。
   *
   * - variantId 缺失（未选规格）→ 禁用按钮
   * - 加购成功后跳转到 /cart 让用户确认
   * - emit('added') 让父组件可以做其它反馈（关闭弹层 / Toast）
   */
  const props = defineProps<{
    variantId: number | null
    quantity?: number
  }>()

  const emit = defineEmits<{ (event: 'added', variantId: number): void }>()

  const cart = useCartStore()
  const localePath = useLocalePath()
  const router = useRouter()
  const submitting = ref(false)
  const errorMsg = ref<string | null>(null)

  const disabled = computed(() => !props.variantId || submitting.value)

  async function handleClick() {
    if (!props.variantId || submitting.value) return
    submitting.value = true
    errorMsg.value = null
    try {
      await cart.addItem(props.variantId, props.quantity ?? 1)
      emit('added', props.variantId)
      await router.push(localePath('/cart'))
    } catch (e) {
      errorMsg.value = (e as Error).message
    } finally {
      submitting.value = false
    }
  }
</script>

<template>
  <div class="space-y-2">
    <button
      type="button"
      :disabled="disabled"
      class="w-full rounded-md bg-primary px-6 py-3 text-base font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
      @click="handleClick"
    >
      <span v-if="submitting">{{ $t('common.loading') }}</span>
      <span v-else>{{ $t('product.add_to_cart') }}</span>
    </button>
    <p v-if="errorMsg" class="text-sm text-red-500">{{ errorMsg }}</p>
  </div>
</template>
