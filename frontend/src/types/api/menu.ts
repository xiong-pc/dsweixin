export interface MenuListQuery {
  keywords?: string
}

export interface StoreMenuRequest {
  parent_id?: number
  name: string
  type: 1 | 2 | 3 | 4  // 1:目录 2:菜单 3:按钮 4:外链
  path?: string
  component?: string
  permission?: string
  icon?: string
  sort?: number
  visible?: boolean
  redirect?: string
}

export interface UpdateMenuRequest extends Partial<StoreMenuRequest> {}

export interface MenuItem {
  id: number
  parent_id: number
  name: string
  type: number
  path: string
  component: string
  permission: string
  icon: string
  sort: number
  visible: number
  redirect: string
  children?: MenuItem[]
}
