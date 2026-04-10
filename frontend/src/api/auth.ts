import request from '@/utils/request'
import type { LoginRequest, LoginResult, UserProfile, RouteMenu } from '@/types/api/auth'

export function loginApi(data: LoginRequest) {
  return request<any, ApiResponse<LoginResult>>({ url: '/auth/login', method: 'post', data })
}

export function refreshTokenApi(token: string) {
  return request<any, ApiResponse<LoginResult>>({
    url: '/auth/refresh',
    method: 'post',
    headers: { Authorization: `Bearer ${token}` },
  })
}

export function logoutApi() {
  return request<any, ApiResponse<null>>({ url: '/auth/logout', method: 'post' })
}

export function getMeApi() {
  return request<any, ApiResponse<UserProfile>>({ url: '/auth/me', method: 'get' })
}

export function getRoutesApi() {
  return request<any, ApiResponse<RouteMenu[]>>({ url: '/auth/routes', method: 'get' })
}
