export interface RoleListQuery {
  keywords?: string
  pageSize?: number
  page?: number
}

export interface StoreRoleRequest {
  name: string
  code: string
  data_scope?: number
  sort?: number
  status?: number
  remark?: string
  menuIds?: number[]
}

export interface UpdateRoleRequest {
  name?: string
  code?: string
  data_scope?: number
  sort?: number
  status?: number
  remark?: string
  menuIds?: number[]
}

export interface RoleItem {
  id: number
  name: string
  code: string
  data_scope: number
  sort: number
  status: number
  remark: string
  created_at: string
  menus?: { id: number }[]
}
