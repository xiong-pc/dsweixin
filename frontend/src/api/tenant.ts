import request from '@/utils/request'
import type { TenantListQuery, StoreTenantRequest, UpdateTenantRequest, TenantItem } from '@/types/api/tenant'

export function getTenantList(params: TenantListQuery) {
  return request<any, ApiResponse<PageResult<TenantItem>>>({ url: '/tenants', method: 'get', params })
}

export function createTenant(data: StoreTenantRequest) {
  return request<any, ApiResponse<TenantItem>>({ url: '/tenants', method: 'post', data })
}

export function updateTenant(id: number, data: UpdateTenantRequest) {
  return request<any, ApiResponse<null>>({ url: `/tenants/${id}`, method: 'put', data })
}

export function deleteTenant(id: number) {
  return request<any, ApiResponse<null>>({ url: `/tenants/${id}`, method: 'delete' })
}
