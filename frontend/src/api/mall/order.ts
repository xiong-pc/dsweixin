import request from '@/utils/request';
import type { OrderListQuery, OrderRow, ShipPayload, RefundPayload, CancelPayload } from '@/types/api/mall/order';

/**
 * Mall 后台订单 API 客户端（M08-PR32）。
 * 端点对齐 backend/routes/api.php `/api/v1/mall/orders/*`。
 */

export function getMallOrderList(params: OrderListQuery) {
  return request<any, ApiResponse<PageResult<OrderRow>>>({
    url: '/mall/orders',
    method: 'get',
    params,
  });
}

export function getMallOrderDetail(id: number) {
  return request<any, ApiResponse<OrderRow>>({
    url: `/mall/orders/${id}`,
    method: 'get',
  });
}

export function shipMallOrder(id: number, payload: ShipPayload) {
  return request<any, ApiResponse<OrderRow>>({
    url: `/mall/orders/${id}/ship`,
    method: 'post',
    data: payload,
  });
}

export function refundMallOrder(id: number, payload: RefundPayload) {
  return request<any, ApiResponse<OrderRow>>({
    url: `/mall/orders/${id}/refund`,
    method: 'post',
    data: payload,
  });
}

export function cancelMallOrder(id: number, payload: CancelPayload) {
  return request<any, ApiResponse<OrderRow>>({
    url: `/mall/orders/${id}/cancel`,
    method: 'post',
    data: payload,
  });
}
