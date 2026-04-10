export interface UserListQuery {
  keywords?: string
  status?: number
  dept_id?: number
  pageSize?: number
  page?: number
}

export interface StoreUserRequest {
  username: string
  nickname: string
  password: string
  email?: string
  phone?: string
  avatar?: string
  gender?: number
  status?: number
  dept_id?: number
  roleIds?: number[]
}

export interface UpdateUserRequest {
  username?: string
  nickname?: string
  email?: string
  phone?: string
  avatar?: string
  gender?: number
  status?: number
  dept_id?: number
  roleIds?: number[]
}

export interface UserItem {
  id: number
  username: string
  nickname: string
  name: string
  email: string
  phone: string
  avatar: string
  gender: number
  status: number
  dept_id: number
  tenant_id: number
  created_at: string
  updated_at: string
  dept?: { id: number; name: string }
  roles?: { id: number; name: string; code: string }[]
}
