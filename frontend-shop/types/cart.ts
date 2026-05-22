/**
 * 购物车 / 订单类型（M11-PR45）。
 *
 * 与 backend/app/Http/Resources/Api/Shop/CartResource + OrderResource 输出对齐。
 */

export interface CartItemVariantSpecValue {
  id: number
  code: string
  translations: Array<{ locale: string; name: string }>
}

export interface CartItemVariant {
  id: number
  sku: string
  price: string | number
  image: string | null
  stock: number
  specification_values: CartItemVariantSpecValue[]
}

export interface CartItemProduct {
  id: number
  cover_image: string | null
  translations: Array<{ locale: string; name: string }>
}

export interface CartItem {
  id: number
  product_id: number
  variant_id: number
  quantity: number
  variant: CartItemVariant | null
  product: CartItemProduct | null
}

export interface Cart {
  id: number | null
  tenant_id: number | null
  shop_id: number | null
  customer_id: number | null
  session_id: string | null
  locale: string | null
  currency: string | null
  item_count: number
  total_quantity: number
  items: CartItem[]
}

export interface CheckoutAddress {
  country_code: string
  province?: string
  city?: string
  district?: string
  street: string
  postal_code?: string
  contact_name: string
  contact_phone: string
  contact_email?: string
}

export interface PlaceOrderPayload {
  shipping_address: CheckoutAddress
  billing_address?: CheckoutAddress
  remark?: string
  payment_method?: string
}

export interface OrderItemSummary {
  id: number
  quantity: number
  unit_price: string | number
  line_total: string | number
}

export interface OrderSummary {
  id: number
  order_no: string
  status: string
  total: string | number
  currency: string
  items: OrderItemSummary[]
}
