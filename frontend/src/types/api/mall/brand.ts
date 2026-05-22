export interface BrandTranslation {
  locale: string;
  name: string;
  description?: string;
}

export interface BrandRow {
  id: number;
  tenant_id: number;
  code: string;
  logo: string;
  website: string;
  sort: number;
  status: number;
  translations: BrandTranslation[];
  created_at?: string | null;
}

export interface BrandListQuery {
  keywords?: string;
  status?: number | '';
  pageSize?: number;
  page?: number;
}

export interface StoreBrandPayload {
  code?: string;
  logo?: string;
  website?: string;
  sort?: number;
  status?: number;
  translations: BrandTranslation[];
}
