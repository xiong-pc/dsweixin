/**
 * 游客 session_id（M11-PR45）。
 *
 * - 用 cookie 持久化（SSR + CSR 同步可见）
 * - 首次访问随机生成 UUID v4
 * - 已登录客户也保留 session_id，便于游客购物车合并到客户账号
 *
 * 通过 useApi 自动以 `X-Session-Id` header 发到后端，CartController / CheckoutController 据此识别游客。
 */
export function useShopSession() {
  const cookie = useCookie<string>('shop_session_id', {
    maxAge: 60 * 60 * 24 * 365, // 1 年
    sameSite: 'lax',
    secure: true,
  })

  if (!cookie.value) {
    cookie.value = generateUuid()
  }

  return cookie
}

function generateUuid(): string {
  // crypto.randomUUID 在 Node 19+ / 现代浏览器原生支持
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  // RFC4122 v4 兜底
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    const v = c === 'x' ? r : (r & 0x3) | 0x8
    return v.toString(16)
  })
}
