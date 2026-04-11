export interface AreaListQuery {
  keywords?: string
  level?: number | ''
  pid?: number | ''
  status?: number | ''
  pageNum?: number
  pageSize?: number
}

export interface StoreAreaRequest {
  pid?: number
  name: string
  shortname?: string
  longitude?: string
  latitude?: string
  level?: number
  sort?: number
  status?: number
}

export interface UpdateAreaRequest extends Partial<StoreAreaRequest> {}

export interface AreaItem {
  id: number
  pid: number
  name: string
  shortname: string
  longitude: string
  latitude: string
  level: number
  sort: number
  status: number
}
