export interface ConfigListQuery {
  keywords?: string;
  pageSize?: number;
  page?: number;
}

export interface StoreConfigRequest {
  name: string;
  key: string;
  value?: string;
  type?: string;
  remark?: string;
}

export type UpdateConfigRequest = Partial<StoreConfigRequest>;

export interface ConfigItem {
  id: number;
  name: string;
  key: string;
  value: string;
  type: string;
  remark: string;
  created_at: string;
}
