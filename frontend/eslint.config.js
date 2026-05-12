import pluginVue from 'eslint-plugin-vue';
import vueTsEslintConfig from '@vue/eslint-config-typescript';
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';

export default [
  {
    name: 'app/files-to-lint',
    files: ['**/*.{ts,mts,tsx,vue}'],
  },

  {
    name: 'app/files-to-ignore',
    ignores: [
      '**/dist/**',
      '**/dist-ssr/**',
      '**/coverage/**',
      // unplugin 自动生成的 dts 文件不参与 lint
      'src/types/auto-imports.d.ts',
      'src/types/components.d.ts',
    ],
  },

  ...pluginVue.configs['flat/essential'],
  ...vueTsEslintConfig(),
  skipFormatting,

  {
    name: 'app/custom-rules',
    rules: {
      // 项目使用 views/feature/index.vue 目录结构（Vite/Nuxt 标准），
      // 单词组件名约束在此场景下没意义，禁用
      'vue/multi-word-component-names': 'off',
      // any 类型降级为 warning，不阻塞 CI；存量代码大量使用 any，
      // 推荐渐进改进（替换为明确类型或 unknown），新增代码避免使用
      '@typescript-eslint/no-explicit-any': 'warn',
    },
  },
];
