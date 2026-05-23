import request from '@/utils/request';
import type {
  PickerOption,
  ProductListQuery,
  ProductRow,
  QuickCreateProductPayload,
  UpdateProductPayload,
} from '@/types/api/mall/product';

/**
 * Mall 后台商品 API 客户端（M10-PR38）。
 *
 * 端点对齐 backend/routes/api.php /api/v1/mall/products + /mall/categories + /mall/brands。
 */

export function getMallProductList(params: ProductListQuery) {
  return request<any, ApiResponse<PageResult<ProductRow>>>({
    url: '/mall/products',
    method: 'get',
    params,
  });
}

export function getMallProductDetail(id: number) {
  return request<any, ApiResponse<ProductRow>>({
    url: `/mall/products/${id}`,
    method: 'get',
  });
}

export function quickCreateMallProduct(payload: QuickCreateProductPayload) {
  return request<any, ApiResponse<ProductRow>>({
    url: '/mall/products/quick-create',
    method: 'post',
    data: payload,
  });
}

export function updateMallProduct(id: number, payload: UpdateProductPayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/products/${id}`,
    method: 'put',
    data: payload,
  });
}

export function deleteMallProduct(id: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/products/${id}`,
    method: 'delete',
  });
}

/** 类目 picker：取首页 100 条，按需扩展为搜索 */
export function getMallCategoryOptions() {
  return request<any, ApiResponse<PageResult<PickerOption & { id: number }>>>({
    url: '/mall/categories',
    method: 'get',
    params: { pageSize: 100 },
  });
}

/** 品牌 picker */
export function getMallBrandOptions() {
  return request<any, ApiResponse<PageResult<PickerOption & { id: number }>>>({
    url: '/mall/brands',
    method: 'get',
    params: { pageSize: 100 },
  });
}
