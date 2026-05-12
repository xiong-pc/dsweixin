import type * as ElementPlusIconsVue from '@element-plus/icons-vue';
import type { hasPerm } from '@/directive/permission';

declare module 'vue' {
  // 此处必须用 interface 才能与 Vue 内置 GlobalComponents 做 module augmentation 合并，
  // type alias 无法实现该模式，故对 no-empty-object-type 做局部豁免
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  export interface GlobalComponents extends ElementPlusIconsVue {}

  export interface GlobalDirectives {
    vHasPerm: typeof hasPerm;
  }
}

export {};
