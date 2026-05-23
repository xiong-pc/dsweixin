import type { ApiResponse, ShopConfig } from '~/types/shop'

/**
 * 全局中间件（M11-PR42）：在 SSR 首屏 / 客户端首次导航时拉取店铺配置。
 *
 * 解析顺序（与后端 ShopResolverMiddleware 对齐）：
 *   1. 当前 host 的子域（生产部署：tenant-a.example.com）
 *   2. NUXT_PUBLIC_FALLBACK_SUBDOMAIN（本地开发兜底）
 *
 * 任意分支失败时把 shop 置为 null，由具体页面降级展示（PR43 会处理 404 + 引导到平台首页）。
 *
 * 仅在 shop 状态为空时 fetch 一次；后续路由切换不会重复请求。
 */
export default defineNuxtRouteMiddleware(async () => {
  const shop = useShop()
  if (shop.value) {
    return
  }

  const config = useRuntimeConfig()
  const platformDomain = config.public.platformDomain as string
  const apiBase = config.public.apiBase as string
  const headerName = config.public.shopHeader as string

  let subdomain: string | null = null
  let host: string | null = null

  // SSR：从请求头拿真实 host；客户端：直接 location
  if (import.meta.server) {
    const event = useRequestEvent()
    host = (event?.node?.req?.headers?.host as string | undefined)?.split(':')[0] ?? null
  } else if (typeof window !== 'undefined') {
    host = window.location.hostname
  }

  if (host && platformDomain && host.endsWith(`.${platformDomain}`)) {
    const candidate = host.slice(0, -(platformDomain.length + 1))
    if (candidate && !candidate.includes('.')) {
      subdomain = candidate
    }
  }

  // 本地兜底子域（开发环境主域不是 *.platform.local 的真实子域）
  if (!subdomain) {
    subdomain = (process.env.NUXT_PUBLIC_FALLBACK_SUBDOMAIN as string | undefined) ?? null
  }

  if (!subdomain) {
    return
  }

  try {
    const res = await $fetch<ApiResponse<ShopConfig>>('shop/config', {
      baseURL: apiBase,
      headers: { [headerName]: subdomain, Accept: 'application/json' },
    })
    if (res?.code === 200 && res.data) {
      shop.value = res.data
    }
  } catch {
    // 静默降级：让后续页面通过 useShop() 看到 null 然后展示通用错误页
  }
})
