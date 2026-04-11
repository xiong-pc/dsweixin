export interface TenantListQuery {
  keywords?: string
  status?: number
  pageSize?: number
  page?: number
}

export interface StoreTenantRequest {
  name: string
  contact_name?: string
  contact_phone?: string
  domain?: string
  account_limit?: number
  expire_time?: string
  status?: number
  remark?: string
}

export interface UpdateTenantRequest extends Partial<StoreTenantRequest> {}

export interface TenantItem {
  id: number
  name: string
  status: number
  contact_name: string
  contact_phone: string
  domain: string
  package_name: string
  account_limit: number
  expire_time: string
  remark: string
  created_at: string
}
