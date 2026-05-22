/**
 * Mall 客户管理类型（M10-PR41）。
 */

export interface CustomerGroupTranslation {
  locale: string;
  name: string;
  description?: string;
}

export interface CustomerGroupRow {
  id: number;
  tenant_id: number;
  code: string;
  discount_rate: string | number;
  sort: number;
  status: number;
  translations: CustomerGroupTranslation[];
  customer_count?: number;
  created_at?: string | null;
}

export interface CustomerGroupListQuery {
  keywords?: string;
  status?: number | '';
  pageSize?: number;
  page?: number;
}

export interface StoreCustomerGroupPayload {
  code?: string;
  discount_rate?: number;
  sort?: number;
  status?: number;
  translations: CustomerGroupTranslation[];
}

/** Customer 的内嵌 group 简表 */
export interface CustomerGroupBrief {
  id: number;
  code: string;
  discount_rate: string | number;
  translations: { locale: string; name: string }[];
}

export interface CustomerRow {
  id: number;
  tenant_id: number;
  shop_id: number | null;
  group_id: number | null;
  email: string;
  phone: string;
  name: string;
  avatar: string;
  gender: string | number | null;
  birthday: string | null;
  locale: string | null;
  currency: string | null;
  status: number;
  last_login_at: string | null;
  last_login_ip: string | null;
  group: CustomerGroupBrief | null;
  created_at?: string | null;
}

export interface CustomerListQuery {
  keywords?: string; // 邮箱 / 手机 / 昵称
  status?: number | '';
  group_id?: number;
  pageSize?: number;
  page?: number;
}

export interface UpdateCustomerPayload {
  name?: string;
  status?: number;
  group_id?: number | null;
  locale?: string;
  currency?: string;
}
