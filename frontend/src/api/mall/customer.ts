import request from '@/utils/request';
import type {
  CustomerGroupListQuery,
  CustomerGroupRow,
  CustomerListQuery,
  CustomerRow,
  StoreCustomerGroupPayload,
  UpdateCustomerPayload,
} from '@/types/api/mall/customer';

/**
 * Mall 客户 + 客户分组 API（M10-PR41）。
 *
 * 后端：
 *   GET/PUT/DELETE /api/v1/mall/customers (仅 list/show/update/destroy)
 *   apiResource /api/v1/mall/customer-groups
 */

// ===== Customers =====
export function getMallCustomerList(params: CustomerListQuery) {
  return request<any, ApiResponse<PageResult<CustomerRow>>>({
    url: '/mall/customers',
    method: 'get',
    params,
  });
}

export function getMallCustomerDetail(id: number) {
  return request<any, ApiResponse<CustomerRow>>({
    url: `/mall/customers/${id}`,
    method: 'get',
  });
}

export function updateMallCustomer(id: number, payload: UpdateCustomerPayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/customers/${id}`,
    method: 'put',
    data: payload,
  });
}

export function deleteMallCustomer(id: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/customers/${id}`,
    method: 'delete',
  });
}

// ===== Customer Groups =====
export function getMallCustomerGroupList(params: CustomerGroupListQuery) {
  return request<any, ApiResponse<PageResult<CustomerGroupRow>>>({
    url: '/mall/customer-groups',
    method: 'get',
    params,
  });
}

export function createMallCustomerGroup(payload: StoreCustomerGroupPayload) {
  return request<any, ApiResponse<CustomerGroupRow>>({
    url: '/mall/customer-groups',
    method: 'post',
    data: payload,
  });
}

export function updateMallCustomerGroup(id: number, payload: StoreCustomerGroupPayload) {
  return request<any, ApiResponse<null>>({
    url: `/mall/customer-groups/${id}`,
    method: 'put',
    data: payload,
  });
}

export function deleteMallCustomerGroup(id: number) {
  return request<any, ApiResponse<null>>({
    url: `/mall/customer-groups/${id}`,
    method: 'delete',
  });
}
