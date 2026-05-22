// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

/**
 * 由 @nuxt/eslint 自动生成的 base 规则 + 项目自定义覆盖。
 * `nuxt prepare` 会生成 .nuxt/eslint.config.mjs；首次安装前文件可能不存在，
 * 此时 lint 命令应通过 postinstall 钩子触发的 nuxt prepare 自动恢复。
 */
export default withNuxt({
  rules: {
    '@typescript-eslint/no-unused-vars': [
      'warn',
      {
        argsIgnorePattern: '^_',
        caughtErrorsIgnorePattern: '^_',
        varsIgnorePattern: '^_',
      },
    ],
    'vue/multi-word-component-names': 'off',
    'vue/no-multiple-template-root': 'off',
    // Prettier 在 HTML 内会强制保留 `<input />` 自闭合；与 vue/html-self-closing 默认值冲突，关闭噪音
    'vue/html-self-closing': 'off',
  },
})
