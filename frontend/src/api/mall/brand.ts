import request from '@/utils/request';
import type { BrandListQuery, BrandRow, StoreBrandPayload } from '@/types/api/mall/brand';

export function getMallBrandList(params: BrandListQuery) {
  return request<any, ApiResponse<PageResult<BrandRow>>>({
    url: '/mall/brands',
    method: 'get',
    params,
  });
}

export function getMallBrandDetail(id: number) {
  return request<any, ApiResponse<BrandRow>>({
    url: `/mall/brands/${id}`,
    method: 'get',
  });
}

export function createMallBrand(payload: StoreBrandPayload) {
  return request<any, ApiResponse<BrandRow>>({
    url: '/mall/brands',
    method: 'post',
    data: payload,
  });
}

export function updateMallBrand(id: number, payload: StoreBrandPayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/brands/${id}`,
    method: 'put',
    data: payload,
  });
}

export function deleteMallBrand(id: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/brands/${id}`,
    method: 'delete',
  });
}
