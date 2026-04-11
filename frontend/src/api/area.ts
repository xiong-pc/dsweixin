import request from '@/utils/request'
import type { AreaListQuery, StoreAreaRequest, UpdateAreaRequest, AreaItem } from '@/types/api/area'

export function getAreaList(params: AreaListQuery) {
  return request<any, ApiResponse<PageResult<AreaItem>>>({ url: '/areas', method: 'get', params })
}

export function getAreaDetail(id: number) {
  return request<any, ApiResponse<AreaItem>>({ url: `/areas/${id}`, method: 'get' })
}

export function createArea(data: StoreAreaRequest) {
  return request<any, ApiResponse<AreaItem>>({ url: '/areas', method: 'post', data })
}

export function updateArea(id: number, data: UpdateAreaRequest) {
  return request<any, ApiResponse<null>>({ url: `/areas/${id}`, method: 'put', data })
}

export function deleteArea(id: number) {
  return request<any, ApiResponse<null>>({ url: `/areas/${id}`, method: 'delete' })
}
