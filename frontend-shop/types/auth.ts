/**
 * Auth 相关数据类型（M11-PR46）。
 *
 * 与 backend/app/Http/Controllers/Api/Shop/AuthController 的响应字段保持一致。
 */

export interface CustomerProfile {
  id: number
  tenant_id: number
  shop_id: number | null
  email: string
  phone: string
  name: string
  avatar: string
  gender: string | number | null
  locale: string | null
  currency: string | null
  status: number
}

export interface AuthTokenPayload {
  accessToken: string
  tokenType: string
  expiresIn: number
  profile: CustomerProfile
}

export type AuthIdentityType = 'email' | 'phone'
