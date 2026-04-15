export interface UserLogListQuery {
  keywords?: string
  uid?: number | ''
  site_id?: number | ''
  action_name?: string
  pageNum?: number
  pageSize?: number
}

export interface StoreUserLogRequest {
  uid?: number
  username?: string
  site_id?: number
  url?: string
  data?: string
  ip?: string
  action_name?: string
}

export interface UpdateUserLogRequest extends Partial<StoreUserLogRequest> {}

export interface UserLogItem {
  id: number
  uid: number
  username: string
  site_id: number
  url: string
  data: string | null
  ip: string
  action_name: string
}
