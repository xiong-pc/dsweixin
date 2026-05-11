import request from '@/utils/request'
import type {
  DictListQuery,
  StoreDictRequest,
  UpdateDictRequest,
  DictItem,
  StoreDictItemRequest,
  UpdateDictItemRequest,
  DictValueItem,
} from '@/types/api/dict'

export function getDictList(params: DictListQuery) {
  return request<any, ApiResponse<PageResult<DictItem>>>({ url: '/system/dicts', method: 'get', params })
}

export function createDict(data: StoreDictRequest) {
  return request<any, ApiResponse<DictItem>>({ url: '/system/dicts', method: 'post', data })
}

export function updateDict(id: number, data: UpdateDictRequest) {
  return request<any, ApiResponse<null>>({ url: `/system/dicts/${id}`, method: 'put', data })
}

export function deleteDict(id: number) {
  return request<any, ApiResponse<null>>({ url: `/system/dicts/${id}`, method: 'delete' })
}

export function getDictItems(dictId: number) {
  return request<any, ApiResponse<DictValueItem[]>>({ url: `/system/dicts/${dictId}/items`, method: 'get' })
}

export function createDictItem(data: StoreDictItemRequest) {
  return request<any, ApiResponse<DictValueItem>>({ url: '/system/dict-items', method: 'post', data })
}

export function updateDictItem(id: number, data: UpdateDictItemRequest) {
  return request<any, ApiResponse<null>>({ url: `/system/dict-items/${id}`, method: 'put', data })
}

export function deleteDictItem(id: number) {
  return request<any, ApiResponse<null>>({ url: `/system/dict-items/${id}`, method: 'delete' })
}
