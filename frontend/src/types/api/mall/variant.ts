/**
 * Mall 商品变体（SKU）类型（M03-PR13 兜底实现）。
 */

export interface VariantSpecValue {
  id: number;
  specification_id?: number;
  code?: string;
  translations?: { locale: string; name: string }[];
}

export interface VariantRow {
  id: number;
  product_id: number;
  sku: string;
  barcode?: string;
  price: string | number;
  compare_at_price?: string | number;
  cost?: string | number;
  weight?: string | number;
  weight_unit?: 'g' | 'kg' | 'oz' | 'lb';
  stock: number;
  reserved?: number;
  low_stock_threshold?: number;
  image?: string;
  status: number;
  sort: number;
  specification_values?: VariantSpecValue[];
}

export interface StoreVariantPayload {
  sku: string;
  barcode?: string;
  price?: number;
  compare_at_price?: number;
  cost?: number;
  weight?: number;
  weight_unit?: 'g' | 'kg' | 'oz' | 'lb';
  stock?: number;
  low_stock_threshold?: number;
  image?: string;
  status?: number;
  sort?: number;
  specification_value_ids?: number[];
}

export interface UpdateVariantPayload {
  sku?: string;
  barcode?: string;
  price?: number;
  compare_at_price?: number;
  cost?: number;
  weight?: number;
  weight_unit?: 'g' | 'kg' | 'oz' | 'lb';
  stock?: number;
  low_stock_threshold?: number;
  image?: string;
  status?: number;
  sort?: number;
  specification_value_ids?: number[];
}
