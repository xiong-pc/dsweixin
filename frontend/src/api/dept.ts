import request from '@/utils/request'
import type { DeptListQuery, StoreDeptRequest, UpdateDeptRequest, DeptItem } from '@/types/api/dept'

export function getDeptList(params?: DeptListQuery) {
  return request<any, ApiResponse<DeptItem[]>>({ url: '/depts', method: 'get', params })
}

export function getDeptDetail(id: number) {
  return request<any, ApiResponse<DeptItem>>({ url: `/depts/${id}`, method: 'get' })
}

export function createDept(data: StoreDeptRequest) {
  return request<any, ApiResponse<DeptItem>>({ url: '/depts', method: 'post', data })
}

export function updateDept(id: number, data: UpdateDeptRequest) {
  return request<any, ApiResponse<null>>({ url: `/depts/${id}`, method: 'put', data })
}

export function deleteDept(id: number) {
  return request<any, ApiResponse<null>>({ url: `/depts/${id}`, method: 'delete' })
}
