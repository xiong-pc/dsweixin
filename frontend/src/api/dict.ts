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
  return request<any, ApiResponse<PageResult<DictItem>>>({ url: '/dicts', method: 'get', params })
}

export function createDict(data: StoreDictRequest) {
  return request<any, ApiResponse<DictItem>>({ url: '/dicts', method: 'post', data })
}

export function updateDict(id: number, data: UpdateDictRequest) {
  return request<any, ApiResponse<null>>({ url: `/dicts/${id}`, method: 'put', data })
}

export function deleteDict(id: number) {
  return request<any, ApiResponse<null>>({ url: `/dicts/${id}`, method: 'delete' })
}

export function getDictItems(dictId: number) {
  return request<any, ApiResponse<DictValueItem[]>>({ url: `/dicts/${dictId}/items`, method: 'get' })
}

export function createDictItem(data: StoreDictItemRequest) {
  return request<any, ApiResponse<DictValueItem>>({ url: '/dict-items', method: 'post', data })
}

export function updateDictItem(id: number, data: UpdateDictItemRequest) {
  return request<any, ApiResponse<null>>({ url: `/dict-items/${id}`, method: 'put', data })
}

export function deleteDictItem(id: number) {
  return request<any, ApiResponse<null>>({ url: `/dict-items/${id}`, method: 'delete' })
}
