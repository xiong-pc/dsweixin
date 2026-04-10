import request from '@/utils/request';

export function getCategoryList(params?: any) {
  return request<any, ApiResponse>({ url: '/categories', method: 'get', params });
}

export function getCategoryDetail(id: number) {
  return request<any, ApiResponse>({ url: `/categories/${id}`, method: 'get' });
}

export function createCategory(data: any) {
  return request<any, ApiResponse>({ url: '/categories', method: 'post', data });
}

export function updateCategory(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/categories/${id}`, method: 'put', data });
}

export function deleteCategory(id: number) {
  return request<any, ApiResponse>({ url: `/categories/${id}`, method: 'delete' });
}
