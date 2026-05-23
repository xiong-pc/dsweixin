import request from '@/utils/request';
import type {
  AttributeRow,
  AttributeValueRow,
  StoreAttributePayload,
  StoreAttributeValuePayload,
} from '@/types/api/mall/attribute';

/**
 * 属性组（材质 / 产地）+ 嵌套值 API。
 */

export function getMallAttrList(params?: { keywords?: string; pageSize?: number; page?: number }) {
  return request<any, ApiResponse<PageResult<AttributeRow>>>({
    url: '/mall/attributes',
    method: 'get',
    params,
  });
}

export function createMallAttr(payload: StoreAttributePayload) {
  return request<any, ApiResponse<AttributeRow>>({
    url: '/mall/attributes',
    method: 'post',
    data: payload,
  });
}

export function updateMallAttr(id: number, payload: StoreAttributePayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/attributes/${id}`,
    method: 'put',
    data: payload,
  });
}

export function deleteMallAttr(id: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/attributes/${id}`,
    method: 'delete',
  });
}

export function getMallAttrValues(attrId: number) {
  return request<any, ApiResponse<AttributeValueRow[]>>({
    url: `/mall/attributes/${attrId}/values`,
    method: 'get',
  });
}

export function createMallAttrValue(attrId: number, payload: StoreAttributeValuePayload) {
  return request<any, ApiResponse<AttributeValueRow>>({
    url: `/mall/attributes/${attrId}/values`,
    method: 'post',
    data: payload,
  });
}

export function updateMallAttrValue(valueId: number, payload: StoreAttributeValuePayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/attribute-values/${valueId}`,
    method: 'put',
    data: payload,
  });
}

export function deleteMallAttrValue(valueId: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/attribute-values/${valueId}`,
    method: 'delete',
  });
}
