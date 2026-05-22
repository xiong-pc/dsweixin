/**
 * 后端 GET /api/v1/shop/config 返回的店铺配置（M11-PR42）。
 *
 * 与 backend/app/Http/Controllers/Api/Shop/ShopConfigController::show 的输出字段对齐。
 */
export interface ShopConfig {
  tenant_id: number
  shop_id: number
  name: string
  code: string
  subdomain: string
  locale: string
  currency: string
  timezone: string
  theme_id: number
  status: number
}

/**
 * 后端统一响应包：{ code, msg, data }。
 */
export interface ApiResponse<T = unknown> {
  code: number
  msg: string
  data: T
}
