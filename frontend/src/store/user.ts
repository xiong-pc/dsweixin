import { defineStore } from 'pinia'
import { ref } from 'vue'
import { loginApi, logoutApi, getMeApi } from '@/api/auth'
import { getToken, setToken as saveToken, removeToken } from '@/utils/auth'
import type { UserProfile } from '@/types/api/auth'
import router from '@/router'
import { usePermissionStore } from '@/store/permission'

const TOKEN_EXPIRES_KEY = 'tokenExpiresAt'

export const useUserStore = defineStore('user', () => {
  const token = ref(getToken())
  const tokenExpiresAt = ref(Number(localStorage.getItem(TOKEN_EXPIRES_KEY) || 0))
  const userInfoLoaded = ref(false)
  const userId = ref(0)
  const username = ref('')
  const nickname = ref('')
  const avatar = ref('')
  const roles = ref<string[]>([])
  const permissions = ref<string[]>([])

  /** 供 request.ts 刷新 Token 后同步调用 */
  function setToken(newToken: string, expiresIn?: number) {
    token.value = newToken
    saveToken(newToken)
    if (expiresIn) {
      const expiresAt = Date.now() + expiresIn * 1000
      tokenExpiresAt.value = expiresAt
      localStorage.setItem(TOKEN_EXPIRES_KEY, String(expiresAt))
    }
  }

  async function login(loginData: { username: string; password: string }) {
    const res = await loginApi(loginData)
    const { accessToken, expiresIn } = res.data
    setToken(accessToken, expiresIn)
  }

  async function getUserInfo(): Promise<UserProfile> {
    const res = await getMeApi()
    const data = res.data
    userId.value = data.userId
    username.value = data.username
    nickname.value = data.nickname
    avatar.value = data.avatar
    roles.value = data.roles
    permissions.value = data.permissions
    userInfoLoaded.value = true
    return data
  }

  async function logout() {
    try {
      await logoutApi()
    } finally {
      resetToken()
      router.push('/login')
    }
  }

  function resetToken() {
    usePermissionStore().resetRoutes()
    token.value = ''
    tokenExpiresAt.value = 0
    userInfoLoaded.value = false
    userId.value = 0
    username.value = ''
    nickname.value = ''
    avatar.value = ''
    roles.value = []
    permissions.value = []
    removeToken()
    localStorage.removeItem(TOKEN_EXPIRES_KEY)
  }

  return {
    token,
    tokenExpiresAt,
    userInfoLoaded,
    userId,
    username,
    nickname,
    avatar,
    roles,
    permissions,
    setToken,
    login,
    getUserInfo,
    logout,
    resetToken,
  }
})
