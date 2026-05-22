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
  // Mall 订单详情（M08-PR32）：动态参数路由，菜单种子未登记，作为隐藏静态路由直接注入
  {
    path: '/mall/order/:id',
    component: () => import('@/layout/index.vue'),
    meta: { hidden: true },
    children: [
      {
        path: '',
        component: () => import('@/views/mall/order/detail.vue'),
        name: 'MallOrderDetail',
        meta: { title: '订单详情', hidden: true },
      },
    ],
  },
  // Mall 商品创建 / 编辑（M10-PR38）：同样作为隐藏静态路由
  {
    path: '/mall/product/create',
    component: () => import('@/layout/index.vue'),
    meta: { hidden: true },
    children: [
      {
        path: '',
        component: () => import('@/views/mall/product/edit.vue'),
        name: 'MallProductCreate',
        meta: { title: '新增商品', hidden: true },
      },
    ],
  },
  {
    path: '/mall/product/:id',
    component: () => import('@/layout/index.vue'),
    meta: { hidden: true },
    children: [
      {
        path: '',
        component: () => import('@/views/mall/product/edit.vue'),
        name: 'MallProductEdit',
        meta: { title: '编辑商品', hidden: true },
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
