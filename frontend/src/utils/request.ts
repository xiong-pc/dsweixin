import axios, { type InternalAxiosRequestConfig, type AxiosResponse } from 'axios'
import { useUserStore } from '@/store/user'
import { ElMessage } from 'element-plus'
import router from '@/router'
import { getToken, setToken } from '@/utils/auth'

const service = axios.create({
  baseURL: import.meta.env.VITE_APP_API_URL + '/api/v1',
  timeout: 30000,
})

// Token 刷新状态管理（防并发）
let isRefreshing = false
let refreshSubscribers: ((token: string) => void)[] = []

function subscribeTokenRefresh(cb: (token: string) => void) {
  refreshSubscribers.push(cb)
}

function onRefreshed(token: string) {
  refreshSubscribers.forEach((cb) => cb(token))
  refreshSubscribers = []
}

/** 直接用 axios 调用 refresh，不经过 service 拦截器，避免循环 */
async function tryRefreshToken(): Promise<string | null> {
  const currentToken = getToken()
  if (!currentToken) return null

  try {
    const res = await axios.post(
      import.meta.env.VITE_APP_API_URL + '/api/v1/auth/refresh',
      {},
      { headers: { Authorization: `Bearer ${currentToken}` } },
    )
    if (res.data?.code === 200 && res.data?.data?.accessToken) {
      return res.data.data.accessToken
    }
  } catch {
    // refresh 失败，由调用方决定是否登出
  }
  return null
}

// 请求拦截器：注入 Token + 主动续签（Token 剩余有效期不足 60s 时）
service.interceptors.request.use(
  async (config: InternalAxiosRequestConfig) => {
    const userStore = useUserStore()
    const token = userStore.token

    if (!token) return config

    const isRefreshUrl = config.url?.includes('/auth/refresh')
    const shouldProactiveRefresh =
      !isRefreshing &&
      !isRefreshUrl &&
      userStore.tokenExpiresAt > 0 &&
      userStore.tokenExpiresAt - Date.now() < 60_000

    if (shouldProactiveRefresh) {
      isRefreshing = true
      const newToken = await tryRefreshToken()
      isRefreshing = false

      if (newToken) {
        setToken(newToken)
        userStore.setToken(newToken)
        onRefreshed(newToken)
        config.headers.Authorization = `Bearer ${newToken}`
        return config
      }
    }

    config.headers.Authorization = `Bearer ${token}`
    return config
  },
  (error) => Promise.reject(error),
)

// 响应拦截器：业务错误处理 + 401 刷新重试
service.interceptors.response.use(
  (response: AxiosResponse) => {
    const { code, msg } = response.data
    if (code === 200) {
      return response.data
    }
    ElMessage.error(msg || '请求失败')
    return Promise.reject(new Error(msg || '请求失败'))
  },
  async (error) => {
    const originalRequest = error.config

    if (!error.response) {
      ElMessage.error('网络异常，请检查网络连接')
      return Promise.reject(error)
    }

    const { status, data } = error.response

    if (status === 401 && !originalRequest._retry) {
      // 有其他请求正在刷新，排队等新 token
      if (isRefreshing) {
        return new Promise((resolve) => {
          subscribeTokenRefresh((newToken: string) => {
            originalRequest.headers.Authorization = `Bearer ${newToken}`
            resolve(service(originalRequest))
          })
        })
      }

      originalRequest._retry = true
      isRefreshing = true
      const newToken = await tryRefreshToken()
      isRefreshing = false

      if (newToken) {
        const userStore = useUserStore()
        setToken(newToken)
        userStore.setToken(newToken)
        onRefreshed(newToken)
        originalRequest.headers.Authorization = `Bearer ${newToken}`
        return service(originalRequest)
      }

      // 刷新失败 → 登出
      const userStore = useUserStore()
      userStore.resetToken()
      router.push('/login')
      ElMessage.error('登录已过期，请重新登录')
    } else if (status === 403) {
      ElMessage.error(data?.msg || '没有权限')
    } else if (status !== 401) {
      ElMessage.error(data?.msg || '请求失败')
    }

    return Promise.reject(error)
  },
)

export default service
