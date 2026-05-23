import { defineStore } from 'pinia'
import type { Cart, OrderSummary, PlaceOrderPayload } from '~/types/cart'

/**
 * 购物车状态（M11-PR45）。
 *
 * - 状态来源：Shop\CartController（X-Session-Id / X-Customer-Id 解析身份）
 * - 本地不缓存条目；每次操作后从后端拉取最新 cart，避免库存竞态
 * - SSR 安全：fetch 在 onMounted / 用户交互时调用，不在 setup 顶层阻塞
 */
export const useCartStore = defineStore('cart', () => {
  const cart = ref<Cart | null>(null)
  const loading = ref(false)
  const lastError = ref<string | null>(null)

  const itemCount = computed(() => cart.value?.item_count ?? 0)
  const totalQuantity = computed(() => cart.value?.total_quantity ?? 0)
  const items = computed(() => cart.value?.items ?? [])

  // 价格累加（变体 price * 数量），后端会在 checkout/preview 给权威值（含运费、税）
  const subtotal = computed(() => {
    return items.value.reduce((sum, it) => {
      const price = Number(it.variant?.price ?? 0)
      return sum + (Number.isFinite(price) ? price * it.quantity : 0)
    }, 0)
  })

  const currency = computed(() => cart.value?.currency ?? '')

  async function fetch() {
    loading.value = true
    lastError.value = null
    try {
      cart.value = await useApi<Cart>('shop/cart')
    } catch (e) {
      lastError.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function addItem(variantId: number, quantity = 1) {
    loading.value = true
    lastError.value = null
    try {
      cart.value = await useApi<Cart>('shop/cart/items', {
        method: 'POST',
        body: { variant_id: variantId, quantity },
      })
    } catch (e) {
      lastError.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function updateItem(itemId: number, quantity: number) {
    loading.value = true
    lastError.value = null
    try {
      cart.value = await useApi<Cart>(`shop/cart/items/${itemId}`, {
        method: 'PUT',
        body: { quantity },
      })
    } catch (e) {
      lastError.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function removeItem(itemId: number) {
    loading.value = true
    lastError.value = null
    try {
      cart.value = await useApi<Cart>(`shop/cart/items/${itemId}`, { method: 'DELETE' })
    } catch (e) {
      lastError.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function clear() {
    loading.value = true
    lastError.value = null
    try {
      cart.value = await useApi<Cart>('shop/cart', { method: 'DELETE' })
    } catch (e) {
      lastError.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  async function preview() {
    return useApi<Record<string, unknown>>('shop/checkout/preview')
  }

  async function placeOrder(payload: PlaceOrderPayload) {
    const order = await useApi<OrderSummary>('shop/checkout/place-order', {
      method: 'POST',
      body: payload,
    })
    // 下单成功后清空本地缓存的 cart（后端已清空购物车）
    cart.value = null
    return order
  }

  return {
    cart,
    loading,
    lastError,
    items,
    itemCount,
    totalQuantity,
    subtotal,
    currency,
    fetch,
    addItem,
    updateItem,
    removeItem,
    clear,
    preview,
    placeOrder,
  }
})
