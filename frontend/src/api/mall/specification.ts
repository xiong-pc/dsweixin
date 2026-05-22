import request from '@/utils/request';
import type {
  SpecificationRow,
  SpecificationValueRow,
  StoreSpecificationPayload,
  StoreSpecificationValuePayload,
} from '@/types/api/mall/specification';

/**
 * 规格组（颜色 / 尺码）+ 嵌套值（红 / M）API。
 */

export function getMallSpecList(params?: { keywords?: string; pageSize?: number; page?: number }) {
  return request<any, ApiResponse<PageResult<SpecificationRow>>>({
    url: '/mall/specifications',
    method: 'get',
    params,
  });
}

export function createMallSpec(payload: StoreSpecificationPayload) {
  return request<any, ApiResponse<SpecificationRow>>({
    url: '/mall/specifications',
    method: 'post',
    data: payload,
  });
}

export function updateMallSpec(id: number, payload: StoreSpecificationPayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/specifications/${id}`,
    method: 'put',
    data: payload,
  });
}

export function deleteMallSpec(id: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/specifications/${id}`,
    method: 'delete',
  });
}

export function getMallSpecValues(specId: number) {
  return request<any, ApiResponse<SpecificationValueRow[]>>({
    url: `/mall/specifications/${specId}/values`,
    method: 'get',
  });
}

export function createMallSpecValue(specId: number, payload: StoreSpecificationValuePayload) {
  return request<any, ApiResponse<SpecificationValueRow>>({
    url: `/mall/specifications/${specId}/values`,
    method: 'post',
    data: payload,
  });
}

export function updateMallSpecValue(valueId: number, payload: StoreSpecificationValuePayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/specification-values/${valueId}`,
    method: 'put',
    data: payload,
  });
}

export function deleteMallSpecValue(valueId: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/specification-values/${valueId}`,
    method: 'delete',
  });
}
