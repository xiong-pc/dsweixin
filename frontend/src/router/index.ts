import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';

export const constantRoutes: RouteRecordRaw[] = [
  {
    path: '/login',
    component: () => import('@/views/login/index.vue'),
    meta: { hidden: true },
  },
  {
    path: '/404',
    component: () => import('@/views/error/404.vue'),
    meta: { hidden: true },
  },
  {
    path: '/',
    component: () => import('@/layout/index.vue'),
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        component: () => import('@/views/dashboard/index.vue'),
        name: 'Dashboard',
        meta: { title: '仪表盘', icon: 'HomeFilled', affix: true },
      },
    ],
  },
  // 通配 404 须在动态路由注入之后注册，见 permissionStore.generateRoutes（否则刷新 /system/... 会先命中通配）
];

const router = createRouter({
  history: createWebHistory(),
  routes: constantRoutes,
  scrollBehavior: () => ({ left: 0, top: 0 }),
});

export default router;
