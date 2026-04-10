import request from '@/utils/request'
import type { ConfigListQuery, StoreConfigRequest, UpdateConfigRequest, ConfigItem } from '@/types/api/config'

export function getConfigList(params: ConfigListQuery) {
  return request<any, ApiResponse<PageResult<ConfigItem>>>({ url: '/configs', method: 'get', params })
}

export function createConfig(data: StoreConfigRequest) {
  return request<any, ApiResponse<ConfigItem>>({ url: '/configs', method: 'post', data })
}

export function updateConfig(id: number, data: UpdateConfigRequest) {
  return request<any, ApiResponse<null>>({ url: `/configs/${id}`, method: 'put', data })
}

export function deleteConfig(id: number) {
  return request<any, ApiResponse<null>>({ url: `/configs/${id}`, method: 'delete' })
}
