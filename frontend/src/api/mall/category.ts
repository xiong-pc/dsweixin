import request from '@/utils/request';
import type { CategoryRow, StoreCategoryPayload } from '@/types/api/mall/category';

export function getMallCategoryList() {
  return request<any, ApiResponse<PageResult<CategoryRow>>>({
    url: '/mall/categories',
    method: 'get',
    params: { pageSize: 200 },
  });
}

export function getMallCategoryDetail(id: number) {
  return request<any, ApiResponse<CategoryRow>>({
    url: `/mall/categories/${id}`,
    method: 'get',
  });
}

export function createMallCategory(payload: StoreCategoryPayload) {
  return request<any, ApiResponse<CategoryRow>>({
    url: '/mall/categories',
    method: 'post',
    data: payload,
  });
}

export function updateMallCategory(id: number, payload: StoreCategoryPayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/categories/${id}`,
    method: 'put',
    data: payload,
  });
}

export function deleteMallCategory(id: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/categories/${id}`,
    method: 'delete',
  });
}
