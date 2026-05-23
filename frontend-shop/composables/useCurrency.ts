/**
 * 用户币种偏好（M11-PR47）。
 *
 * - cookie 持久化：用户在 CurrencySwitcher 切换后保留 30 天
 * - 默认值：未选择时回退到 shop.currency（来自 SSR 中间件 useShop）
 * - **当前 P0 仅支持显示币种名（如 "USD"）；实际汇率换算由 M02 ExchangeRate 接入后才能联动**
 *
 * 使用：
 * ```ts
 * const currency = useCurrency()
 * currency.value = 'USD' // 切换
 * ```
 */
export function useCurrency() {
  return useCookie<string | null>('shop_currency_pref', {
    default: () => null,
    maxAge: 60 * 60 * 24 * 30,
    sameSite: 'lax',
  })
}

/**
 * 计算当前应使用的币种：用户偏好 > 店铺默认 > 兜底 'USD'。
 */
export function useResolvedCurrency() {
  const pref = useCurrency()
  const shop = useShop()
  return computed(() => pref.value || shop.value?.currency || 'USD')
}

/**
 * 简单价格格式化（PR48+ 接 ExchangeRate 后扩展为 Intl.NumberFormat）。
 */
export function formatPrice(amount: number | string, currency: string): string {
  const num = Number(amount)
  if (!Number.isFinite(num)) return ''
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(num)
  } catch {
    return `${currency} ${num.toFixed(2)}`
  }
}
