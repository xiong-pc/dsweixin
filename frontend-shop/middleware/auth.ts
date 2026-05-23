import { useAuthStore } from '~/stores/auth'

/**
 * 路由级 auth 守卫（M11-PR46）。
 *
 * 在受保护页面（/account/* 等）的 definePageMeta 里通过 `middleware: ['auth']` 引用：
 *
 * ```ts
 * definePageMeta({ middleware: ['auth'] })
 * ```
 *
 * 行为：
 *   1. 没有 token → 跳 /login，附带 redirect query 让登录后回跳
 *   2. 有 token 但 profile 缺失 → 调 fetchMe；失败则清态后再跳 /login
 *   3. 完整登录态 → 直接放行
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()
  const localePath = useLocalePath()

  if (!auth.token) {
    return navigateTo({
      path: localePath('/login'),
      query: { redirect: to.fullPath },
    })
  }

  if (!auth.profile) {
    await auth.fetchMe()
    if (!auth.profile) {
      return navigateTo({
        path: localePath('/login'),
        query: { redirect: to.fullPath },
      })
    }
  }
})
