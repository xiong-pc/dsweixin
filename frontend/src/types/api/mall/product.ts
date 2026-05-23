/**
 * Mall 商品 / 类目 / 品牌后台类型（M10-PR38）。
 *
 * 与 backend/app/Http/Resources/Api/Mall/ProductResource 输出对齐。
 */

export interface ProductTranslation {
  locale: string;
  name: string;
  slug?: string;
  short_description?: string;
  description?: string;
  seo_title?: string;
  seo_keywords?: string;
  seo_description?: string;
}

export interface ProductRow {
  id: number;
  tenant_id: number;
  shop_id: number | null;
  brand_id: number | null;
  category_id: number | null;
  sku_prefix: string;
  cover_image: string;
  images: string[];
  base_price: string | number;
  base_currency: string;
  status: number;
  sort: number;
  sold_count: number;
  view_count: number;
  translations: ProductTranslation[];
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ProductListQuery {
  shop_id?: number;
  brand_id?: number;
  category_id?: number;
  status?: number | '';
  keywords?: string;
  pageSize?: number;
  page?: number;
}

export interface QuickCreateProductPayload {
  shop_id?: number | null;
  brand_id?: number | null;
  category_id?: number | null;
  cover_image?: string;
  images?: string[];
  base_currency?: string;
  status?: number;
  translations: ProductTranslation[];
  sku: string;
  price: number;
  compare_at_price?: number;
  stock: number;
  weight?: number;
  weight_unit?: 'g' | 'kg' | 'oz' | 'lb';
}

export interface UpdateProductPayload {
  shop_id?: number | null;
  brand_id?: number | null;
  category_id?: number | null;
  cover_image?: string;
  images?: string[];
  base_currency?: string;
  base_price?: number;
  status?: number;
  sort?: number;
  translations?: ProductTranslation[];
}

/** 类目 / 品牌 picker 简表（M10-PR39 会替换为完整 CRUD 页面） */
export interface PickerOption {
  id: number;
  name: string;
  code?: string;
}
