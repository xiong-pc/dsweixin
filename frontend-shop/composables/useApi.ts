import type { NitroFetchOptions, NitroFetchRequest } from 'nitropack'
import type { ApiResponse } from '~/types/shop'

/**
 * 统一 API 客户端（M11-PR42）。
 *
 * - baseURL：来自 runtimeConfig.public.apiBase，默认指向 Laravel `/api/v1`
 * - 自动注入 X-Tenant-Id / X-Shop-Id header（来自 useShop 解析的 ShopConfig）
 * - 自动注入 X-Shop-Subdomain header（让后端 ShopResolverMiddleware 兜底）
 * - 自动注入 Authorization Bearer token（来自 useState('auth.token')，PR46 完整登录后写入）
 * - 后端响应统一为 { code, msg, data }；非 200 抛 ApiError，便于上层 catch
 *
 * 使用示例：
 * ```ts
 * const { data } = await useApi<Product[]>('products')
 * const created = await useApi<Order>('orders', { method: 'POST', body: { ... } })
 * ```
 */
export class ApiError extends Error {
  constructor(
    public readonly code: number,
    public readonly msg: string,
    public readonly response?: ApiResponse,
  ) {
    super(msg || `API error ${code}`)
    this.name = 'ApiError'
  }
}

type RequestOptions<
  M extends 'get' | 'post' | 'put' | 'patch' | 'delete' | 'head' | 'options' = 'get',
> = Omit<NitroFetchOptions<NitroFetchRequest, M>, 'baseURL'> & {
  /** 是否跳过 token 注入（公开端点：config / send-code 等） */
  skipAuth?: boolean
}

export function useApi<T = unknown>(
  path: string,
  options: RequestOptions<'get' | 'post' | 'put' | 'patch' | 'delete'> = {},
): Promise<T> {
  const config = useRuntimeConfig()
  const shop = useShop()
  const session = useShopSession()
  const token = useAuthToken()
  const i18n = useNuxtApp().$i18n as { locale: { value: string } } | undefined

  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...((options.headers as Record<string, string>) ?? {}),
  }

  // 多租户身份头（PR3 / PR34 已建立的契约）
  if (shop.value) {
    headers['X-Tenant-Id'] = String(shop.value.tenant_id)
    headers['X-Shop-Id'] = String(shop.value.shop_id)
    headers[config.public.shopHeader as string] = shop.value.subdomain
  } else if (process.env.NUXT_PUBLIC_FALLBACK_SUBDOMAIN) {
    headers[config.public.shopHeader as string] = process.env.NUXT_PUBLIC_FALLBACK_SUBDOMAIN
  }

  // 游客身份（购物车 / 结账识别用），始终发送
  if (session.value) {
    headers['X-Session-Id'] = session.value
  }

  // 当前语言（驱动后端资源 locale 解析）
  if (i18n?.locale?.value) {
    headers['X-Locale'] = i18n.locale.value
  }

  if (!options.skipAuth && token.value) {
    headers['Authorization'] = `Bearer ${token.value}`
  }

  return $fetch<ApiResponse<T>>(path, {
    baseURL: config.public.apiBase as string,
    ...options,
    headers,
  }).then((res) => {
    if (res?.code !== 200) {
      throw new ApiError(res?.code ?? 500, res?.msg ?? 'Unknown error', res)
    }
    return res.data
  })
}
