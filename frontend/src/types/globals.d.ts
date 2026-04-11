import type * as ElementPlusIconsVue from '@element-plus/icons-vue'
import type { hasPerm } from '@/directive/permission'

declare module 'vue' {
  export interface GlobalComponents extends ElementPlusIconsVue {}

  export interface GlobalDirectives {
    vHasPerm: typeof hasPerm
  }
}

export {}
