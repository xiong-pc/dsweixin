export interface AttributeTranslation {
  locale: string;
  name: string;
}

export interface AttributeValueTranslation {
  locale: string;
  name: string;
}

export interface AttributeValueRow {
  id: number;
  attribute_id: number;
  code: string;
  sort: number;
  status: number;
  translations: AttributeValueTranslation[];
}

export interface AttributeRow {
  id: number;
  tenant_id: number;
  code: string;
  status: number;
  sort: number;
  translations: AttributeTranslation[];
  values?: AttributeValueRow[];
  created_at?: string | null;
}

export interface StoreAttributePayload {
  code?: string;
  status?: number;
  sort?: number;
  translations: AttributeTranslation[];
}

export interface StoreAttributeValuePayload {
  code?: string;
  sort?: number;
  status?: number;
  translations: AttributeValueTranslation[];
}
