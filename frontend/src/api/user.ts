import request from '@/utils/request'
import type { UserListQuery, StoreUserRequest, UpdateUserRequest, UserItem } from '@/types/api/user'

export function getUserList(params: UserListQuery) {
  return request<any, ApiResponse<PageResult<UserItem>>>({ url: '/users', method: 'get', params })
}

export function getUserDetail(id: number) {
  return request<any, ApiResponse<UserItem>>({ url: `/users/${id}`, method: 'get' })
}

export function createUser(data: StoreUserRequest) {
  return request<any, ApiResponse<UserItem>>({ url: '/users', method: 'post', data })
}

export function updateUser(id: number, data: UpdateUserRequest) {
  return request<any, ApiResponse<null>>({ url: `/users/${id}`, method: 'put', data })
}

export function deleteUser(id: number) {
  return request<any, ApiResponse<null>>({ url: `/users/${id}`, method: 'delete' })
}

export function updateUserStatus(id: number, status: number) {
  return request<any, ApiResponse<null>>({ url: `/users/${id}/status`, method: 'patch', data: { status } })
}

export function resetPassword(id: number) {
  return request<any, ApiResponse<null>>({ url: `/users/${id}/reset-password`, method: 'post' })
}
