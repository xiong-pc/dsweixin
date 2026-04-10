export interface TenantListQuery {
  keywords?: string
  status?: number
  pageSize?: number
  page?: number
}

export interface StoreTenantRequest {
  name: string
  code: string
  status?: number
  contact_name?: string
  contact_phone?: string
  expired_at?: string
  remark?: string
}

export interface UpdateTenantRequest extends Partial<StoreTenantRequest> {}

export interface TenantItem {
  id: number
  name: string
  code: string
  status: number
  contact_name: string
  contact_phone: string
  expired_at: string
  remark: string
  created_at: string
}
