import request from '@/utils/request';
import type { StoreVariantPayload, UpdateVariantPayload, VariantRow } from '@/types/api/mall/variant';

/**
 * Mall 商品变体（SKU）API（M03-PR13）。
 *
 * 后端：
 *   GET    /api/v1/mall/products/{id}/variants
 *   POST   /api/v1/mall/products/{id}/variants
 *   POST   /api/v1/mall/products/{id}/variants/matrix（仅返回笛卡尔积预览）
 *   GET    /api/v1/mall/product-variants/{variant}
 *   PUT    /api/v1/mall/product-variants/{variant}
 *   DELETE /api/v1/mall/product-variants/{variant}
 */

export function listVariants(productId: number) {
  return request<any, ApiResponse<PageResult<VariantRow>>>({
    url: `/mall/products/${productId}/variants`,
    method: 'get',
  });
}

export function createVariant(productId: number, payload: StoreVariantPayload) {
  return request<any, ApiResponse<VariantRow>>({
    url: `/mall/products/${productId}/variants`,
    method: 'post',
    data: payload,
  });
}

export function updateVariant(variantId: number, payload: UpdateVariantPayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/product-variants/${variantId}`,
    method: 'put',
    data: payload,
  });
}

export function deleteVariant(variantId: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/product-variants/${variantId}`,
    method: 'delete',
  });
}

/** 矩阵预览：传入按规格分组的 spec_value_ids，返回笛卡尔积组合（每组合一行 spec_value_ids） */
export function generateMatrix(productId: number, specValueGroups: number[][]) {
  return request<any, ApiResponse<{ combinations: number[][] }>>({
    url: `/mall/products/${productId}/variants/matrix`,
    method: 'post',
    data: { groups: specValueGroups },
  });
}
