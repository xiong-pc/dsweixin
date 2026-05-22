export interface SpecificationTranslation {
  locale: string;
  name: string;
}

export interface SpecificationValueTranslation {
  locale: string;
  name: string;
}

export interface SpecificationValueRow {
  id: number;
  specification_id: number;
  code: string;
  sort: number;
  status: number;
  translations: SpecificationValueTranslation[];
}

export interface SpecificationRow {
  id: number;
  tenant_id: number;
  code: string;
  status: number;
  sort: number;
  translations: SpecificationTranslation[];
  values?: SpecificationValueRow[];
  created_at?: string | null;
}

export interface StoreSpecificationPayload {
  code?: string;
  status?: number;
  sort?: number;
  translations: SpecificationTranslation[];
}

export interface StoreSpecificationValuePayload {
  code?: string;
  sort?: number;
  status?: number;
  translations: SpecificationValueTranslation[];
}
