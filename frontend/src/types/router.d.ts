import 'vue-router';

declare module 'vue-router' {
  interface RouteMeta {
    title?: string;
    icon?: string;
    hidden?: boolean;
    roles?: string[];
    keepAlive?: boolean;
    alwaysShow?: boolean;
    affix?: boolean;
  }
}
