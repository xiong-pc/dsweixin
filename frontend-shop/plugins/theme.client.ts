/**
 * 客户端启动时根据 shop.theme_id 注入主题色（M11-PR47）。
 *
 * `.client.ts` 后缀保证只在浏览器执行，避免 SSR 阶段 document 未定义错误。
 * 在 tenant.global.ts 写入 shop 之后立刻应用；后续 shop 变更（理论上不会）通过 watch 跟随。
 */
export default defineNuxtPlugin(() => {
  const shop = useShop()

  const applyFromShop = () => {
    applyTheme(paletteFor(shop.value?.theme_id ?? 0))
  }

  // 首屏立刻应用一次
  applyFromShop()

  // shop 变更（如登录态切换 / 后续 hot reload）跟随更新
  watch(() => shop.value?.theme_id, applyFromShop)
})
