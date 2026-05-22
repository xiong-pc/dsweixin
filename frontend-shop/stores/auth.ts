import { defineStore } from 'pinia'
import type { AuthIdentityType, AuthTokenPayload, CustomerProfile } from '~/types/auth'

/**
 * 客户认证状态（M11-PR46）。
 *
 * - token 存 cookie（useAuthToken），刷新页面后保持登录
 * - profile 仅本次会话内存中（每次首屏 fetchMe 拉一次）
 * - 所有写操作都同步更新 cookie token + profile，避免不一致
 */
export const useAuthStore = defineStore('auth', () => {
  const token = useAuthToken()
  const profile = ref<CustomerProfile | null>(null)
  const loading = ref(false)
  const lastError = ref<string | null>(null)

  const isLoggedIn = computed(() => Boolean(token.value && profile.value))

  function applyTokenPayload(p: AuthTokenPayload) {
    token.value = p.accessToken
    profile.value = p.profile
  }

  async function login(username: string, password: string) {
    loading.value = true
    lastError.value = null
    try {
      const data = await useApi<AuthTokenPayload>('shop/auth/login', {
        method: 'POST',
        body: { username, password },
        skipAuth: true,
      })
      applyTokenPayload(data)
    } catch (e) {
      lastError.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function register(payload: {
    type: AuthIdentityType
    target: string
    password: string
    code: string
    name?: string
  }) {
    loading.value = true
    lastError.value = null
    try {
      const data = await useApi<AuthTokenPayload>('shop/auth/register', {
        method: 'POST',
        body: payload,
        skipAuth: true,
      })
      applyTokenPayload(data)
    } catch (e) {
      lastError.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function loginByCode(payload: { type: AuthIdentityType; target: string; code: string }) {
    loading.value = true
    lastError.value = null
    try {
      const data = await useApi<AuthTokenPayload>('shop/auth/login-by-code', {
        method: 'POST',
        body: payload,
        skipAuth: true,
      })
      applyTokenPayload(data)
    } catch (e) {
      lastError.value = (e as Error).message
      throw e
    } finally {
      loading.value = false
    }
  }

  async function sendCode(payload: { type: AuthIdentityType; target: string }) {
    return useApi<null>('shop/auth/send-code', {
      method: 'POST',
      body: payload,
      skipAuth: true,
    })
  }

  async function fetchMe() {
    if (!token.value) {
      profile.value = null
      return null
    }
    try {
      const data = await useApi<CustomerProfile>('shop/auth/me')
      profile.value = data
      return data
    } catch (_) {
      // token 过期等错误：清掉本地态，触发 middleware 重定向到登录页
      logoutLocal()
      return null
    }
  }

  function logoutLocal() {
    token.value = null
    profile.value = null
  }

  async function logout() {
    if (!token.value) {
      logoutLocal()
      return
    }
    try {
      await useApi<null>('shop/auth/logout', { method: 'POST' })
    } catch (_) {
      // 即便服务端返回错误，本地也要清掉 token（防止卡在伪登录态）
    } finally {
      logoutLocal()
    }
  }

  return {
    token,
    profile,
    loading,
    lastError,
    isLoggedIn,
    login,
    register,
    loginByCode,
    sendCode,
    fetchMe,
    logout,
  }
})
