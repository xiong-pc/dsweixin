/**
 * Bearer token 持久化（M11-PR46）。
 *
 * - 使用 cookie 存储，SSR + CSR 同步可见，刷新页面后仍保持登录
 * - useApi 自动读取此 ref 注入 Authorization header
 * - 登出时把 ref 置 null 即可清除 cookie
 *
 * 注意：这里没用 HttpOnly（Nuxt useCookie 客户端可读，否则 JS 无法添加 Authorization）。
 * 真实生产环境若担心 XSS，建议改后端 Set-Cookie + 同源代理转 header 模式。
 */
export function useAuthToken() {
  return useCookie<string | null>('shop_auth_token', {
    default: () => null,
    maxAge: 60 * 60 * 24 * 30, // 30 天，与 Passport token_expire_days 类似量级
    sameSite: 'lax',
    secure: true,
  })
}
