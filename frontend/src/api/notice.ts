import request from '@/utils/request'
import type { NoticeListQuery, StoreNoticeRequest, UpdateNoticeRequest, NoticeItem } from '@/types/api/notice'

export function getNoticeList(params: NoticeListQuery) {
  return request<any, ApiResponse<PageResult<NoticeItem>>>({ url: '/notices', method: 'get', params })
}

export function createNotice(data: StoreNoticeRequest) {
  return request<any, ApiResponse<NoticeItem>>({ url: '/notices', method: 'post', data })
}

export function updateNotice(id: number, data: UpdateNoticeRequest) {
  return request<any, ApiResponse<null>>({ url: `/notices/${id}`, method: 'put', data })
}

export function deleteNotice(id: number) {
  return request<any, ApiResponse<null>>({ url: `/notices/${id}`, method: 'delete' })
}

export function publishNotice(id: number) {
  return request<any, ApiResponse<null>>({ url: `/notices/${id}/publish`, method: 'patch' })
}

export function revokeNotice(id: number) {
  return request<any, ApiResponse<null>>({ url: `/notices/${id}/revoke`, method: 'patch' })
}
