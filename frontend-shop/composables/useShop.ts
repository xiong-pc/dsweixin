import type { ShopConfig } from '~/types/shop'

/**
 * 当前请求关联的店铺配置（M11-PR42）。
 *
 * 由 `middleware/tenant.global.ts` 在 SSR 阶段填充：根据请求 host / 子域 / 兜底环境变量
 * 调用 `/api/v1/shop/config`，结果通过 `useState('shop')` 在客户端复用，避免重复 fetch。
 *
 * 没有解析到时为 null（开发兜底场景），调用方应自行降级（如默认 zh-CN / CNY）。
 */
export function useShop() {
  return useState<ShopConfig | null>('shop', () => null)
}
