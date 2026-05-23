/**
 * 主题色动态注入（M11-PR47）。
 *
 * - 读取当前店铺的 theme_id，按映射表选 primary / secondary 调色
 * - 把 `--color-primary` / `--color-secondary` 注入到 `<html>` 的 inline style
 * - Tailwind 的 `bg-primary` 等类已通过 CSS 变量映射，无需重新构建
 *
 * 当前调色板：P0 仅 4 套基础配色，后续接 `shop_themes` 表后再扩。
 */
export interface ThemePalette {
  primary: string // 形如 "24 96 200"（rgb 三元组，与 tailwind.css 变量约定一致）
  secondary: string
}

const PALETTES: Record<number, ThemePalette> = {
  // 0：默认（极光蓝 / 玫粉）
  0: { primary: '24 96 200', secondary: '248 113 113' },
  // 1：薄荷（清新绿 / 暖琥珀）
  1: { primary: '20 184 166', secondary: '249 115 22' },
  // 2：摩登（深紫 / 柠檬黄）
  2: { primary: '124 58 237', secondary: '234 179 8' },
  // 3：高对比（黑 / 红）
  3: { primary: '17 24 39', secondary: '220 38 38' },
}

export function paletteFor(themeId: number | null | undefined): ThemePalette {
  if (themeId == null) return PALETTES[0]
  return PALETTES[themeId] ?? PALETTES[0]
}

/**
 * 应用主题色到 documentElement；可在客户端 plugin 中调用，
 * 也可在任意组件 onMounted 中调用（重复设置幂等）。
 */
export function applyTheme(palette: ThemePalette) {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  root.style.setProperty('--color-primary', palette.primary)
  root.style.setProperty('--color-secondary', palette.secondary)
}
