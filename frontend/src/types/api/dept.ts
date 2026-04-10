export interface DeptListQuery {
  keywords?: string
  status?: number
}

export interface StoreDeptRequest {
  parent_id?: number
  name: string
  sort?: number
  status?: number
}

export interface UpdateDeptRequest extends Partial<StoreDeptRequest> {}

export interface DeptItem {
  id: number
  parent_id: number
  name: string
  sort: number
  status: number
  children?: DeptItem[]
}
