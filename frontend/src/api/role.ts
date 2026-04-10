import request from '@/utils/request'
import type { RoleListQuery, StoreRoleRequest, UpdateRoleRequest, RoleItem } from '@/types/api/role'

export function getRoleList(params: RoleListQuery) {
  return request<any, ApiResponse<PageResult<RoleItem>>>({ url: '/roles', method: 'get', params })
}

export function getRoleDetail(id: number) {
  return request<any, ApiResponse<RoleItem>>({ url: `/roles/${id}`, method: 'get' })
}

export function createRole(data: StoreRoleRequest) {
  return request<any, ApiResponse<RoleItem>>({ url: '/roles', method: 'post', data })
}

export function updateRole(id: number, data: UpdateRoleRequest) {
  return request<any, ApiResponse<null>>({ url: `/roles/${id}`, method: 'put', data })
}

export function deleteRole(id: number) {
  return request<any, ApiResponse<null>>({ url: `/roles/${id}`, method: 'delete' })
}

export function getRoleMenus(id: number) {
  return request<any, ApiResponse<number[]>>({ url: `/roles/${id}/menus`, method: 'get' })
}

export function updateRoleMenus(id: number, menuIds: number[]) {
  return request<any, ApiResponse<null>>({ url: `/roles/${id}/menus`, method: 'put', data: { menuIds } })
}
