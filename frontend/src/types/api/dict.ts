export interface DictListQuery {
  keywords?: string
  pageSize?: number
  page?: number
}

export interface StoreDictRequest {
  name: string
  code: string
  status?: number
  remark?: string
}

export interface UpdateDictRequest extends Partial<StoreDictRequest> {}

export interface DictItem {
  id: number
  name: string
  code: string
  status: number
  remark: string
  created_at: string
  items?: DictValueItem[]
}

export interface StoreDictItemRequest {
  dict_id: number
  label: string
  value: string
  sort?: number
  status?: number
  remark?: string
}

export interface UpdateDictItemRequest extends Partial<Omit<StoreDictItemRequest, 'dict_id'>> {}

export interface DictValueItem {
  id: number
  dict_id: number
  label: string
  value: string
  sort: number
  status: number
  remark: string
}
