export interface CategoryTranslation {
  locale: string;
  name: string;
  description?: string;
}

export interface CategoryRow {
  id: number;
  tenant_id: number;
  parent_id: number;
  code: string;
  cover_image: string;
  sort: number;
  status: number;
  translations: CategoryTranslation[];
  children?: CategoryRow[];
  created_at?: string | null;
}

export interface StoreCategoryPayload {
  parent_id?: number;
  code?: string;
  cover_image?: string;
  sort?: number;
  status?: number;
  translations: CategoryTranslation[];
}
