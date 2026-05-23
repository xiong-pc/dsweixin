/**
 * Mall 订单后台类型（M08-PR32）。
 *
 * 与 backend/app/Http/Resources/Api/Shop/OrderResource + Mall\OrderController 输出对齐。
 */

export type OrderStatus = 'pending' | 'paid' | 'shipped' | 'delivered' | 'cancelled' | 'refunded' | string;

export interface OrderItemRow {
  id: number;
  product_id: number | null;
  variant_id: number | null;
  sku: string | null;
  name_snapshot: string | null;
  quantity: number;
  unit_price: string | number;
  currency: string;
  line_total: string | number;
}

export interface OrderShippingAddress {
  country_code?: string;
  province?: string;
  city?: string;
  district?: string;
  street?: string;
  postal_code?: string;
  contact_name?: string;
  contact_phone?: string;
  contact_email?: string;
}

export interface OrderShipmentRow {
  id: number;
  carrier?: string;
  tracking_no?: string;
  status?: string;
  shipped_at?: string | null;
  delivered_at?: string | null;
}

export interface OrderHistoryRow {
  id: number;
  from_status: string | null;
  to_status: string | null;
  operator_type: string | null;
  operator_id: number | null;
  reason: string | null;
  note: string | null;
  created_at: string | null;
}

export interface OrderRow {
  id: number;
  order_no: string;
  tenant_id: number;
  customer_id: number | null;
  session_id: string | null;
  status: OrderStatus;
  currency: string;
  subtotal: string | number;
  total: string | number;
  pay_method?: string | null;
  paid_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  items?: OrderItemRow[];
  shipping_address?: OrderShippingAddress | null;
  billing_address?: OrderShippingAddress | null;
  shipments?: OrderShipmentRow[];
  histories?: OrderHistoryRow[];
}

export interface OrderListQuery {
  keywords?: string; // 订单号 / 客户邮箱（后端按需扩展）
  status?: OrderStatus | '';
  pageSize?: number;
  page?: number;
  pageNum?: number;
}

export interface ShipPayload {
  carrier: string;
  tracking_no: string;
  fee?: number;
}

export interface RefundPayload {
  amount?: number;
  reason?: string;
}

export interface CancelPayload {
  reason?: string;
}
