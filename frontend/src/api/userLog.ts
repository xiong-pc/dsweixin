import request from '@/utils/request'
import type { UserLogListQuery, StoreUserLogRequest, UpdateUserLogRequest, UserLogItem } from '@/types/api/userLog'

export function getUserLogList(params: UserLogListQuery) {
  return request<any, ApiResponse<PageResult<UserLogItem>>>({ url: '/user-logs', method: 'get', params })
}

export function getUserLogDetail(id: number) {
  return request<any, ApiResponse<UserLogItem>>({ url: `/user-logs/${id}`, method: 'get' })
}

export function createUserLog(data: StoreUserLogRequest) {
  return request<any, ApiResponse<UserLogItem>>({ url: '/user-logs', method: 'post', data })
}

export function updateUserLog(id: number, data: UpdateUserLogRequest) {
  return request<any, ApiResponse<null>>({ url: `/user-logs/${id}`, method: 'put', data })
}

export function deleteUserLog(id: number) {
  return request<any, ApiResponse<null>>({ url: `/user-logs/${id}`, method: 'delete' })
}
