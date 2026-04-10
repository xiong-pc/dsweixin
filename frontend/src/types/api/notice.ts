export interface NoticeListQuery {
  keywords?: string
  type?: number
  status?: number
  pageSize?: number
  page?: number
}

export interface StoreNoticeRequest {
  title: string
  type?: number
  level?: number
  content?: string
  status?: 0 | 1 | 2
}

export interface UpdateNoticeRequest {
  title?: string
  type?: number
  level?: number
  content?: string
}

export interface NoticeItem {
  id: number
  title: string
  type: number
  level: number
  content: string
  status: number
  publisher_id: number
  publish_time: string
  created_at: string
  publisher?: { id: number; nickname: string }
}
