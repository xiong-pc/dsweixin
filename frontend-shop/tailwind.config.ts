import type { Config } from 'tailwindcss'

/**
 * Tailwind 配置：
 * - 主题色通过 CSS 变量 `--color-primary` 等动态注入（M11-PR47 实现），
 *   这里仅声明一次，便于 `bg-primary` `text-primary` 直接命中。
 * - content 覆盖 Nuxt 全部默认目录 + plugins + composables。
 */
export default <Partial<Config>>{
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
    './plugins/**/*.{js,ts}',
    './composables/**/*.{js,ts}',
    './app.vue',
  ],
  theme: {
    extend: {
      colors: {
        primary: 'rgb(var(--color-primary) / <alpha-value>)',
        secondary: 'rgb(var(--color-secondary) / <alpha-value>)',
      },
      fontFamily: {
        sans: ['Inter', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
