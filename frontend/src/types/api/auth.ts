export interface LoginRequest {
  username: string
  password: string
}

export interface LoginResult {
  accessToken: string
  tokenType: string
  expiresIn: number
}

export interface UserProfile {
  userId: number
  username: string
  nickname: string
  avatar: string
  email: string
  phone: string
  gender: number
  status: number
  deptName: string
  roles: string[]
  permissions: string[]
}

export interface RouteMenu {
  path: string
  component: string
  name: string
  redirect?: string
  meta: {
    title: string
    icon: string
    hidden: boolean
  }
  children?: RouteMenu[]
}
