import { defineStore } from 'pinia';
import { ref } from 'vue';
import { type RouteRecordRaw } from 'vue-router';
import { getRoutesApi } from '@/api/auth';
import router from '@/router';

const modules = import.meta.glob('../views/**/**.vue');

export const Layout = () => import('@/layout/index.vue');

export const usePermissionStore = defineStore('permission', () => {
  const routes = ref<RouteRecordRaw[]>([]);
  const addedRoutes = ref<RouteRecordRaw[]>([]);

  async function generateRoutes() {
    if (router.hasRoute('PathMatch404')) {
      router.removeRoute('PathMatch404');
    }

    const res = await getRoutesApi();
    const asyncRoutes = transformRoutes(res.data || []);
    asyncRoutes.forEach((route) => {
      router.addRoute(route);
    });
    addedRoutes.value = asyncRoutes;

    router.addRoute({
      name: 'PathMatch404',
      path: '/:pathMatch(.*)*',
      redirect: '/404',
      meta: { hidden: true },
    });

    return asyncRoutes;
  }

  function transformRoutes(serverRoutes: any[]): RouteRecordRaw[] {
    const res: RouteRecordRaw[] = [];
    for (const route of serverRoutes) {
      const tmp: any = {
        path: route.path,
        name: route.name,
        meta: route.meta || {},
        redirect: route.redirect || undefined,
      };

      if (route.component === 'Layout') {
        tmp.component = Layout;
      } else {
        const component = route.component;
        tmp.component = modules[`../views/${component}.vue`] || modules[`../views/${component}/index.vue`];
      }

      if (route.children && route.children.length > 0) {
        tmp.children = transformRoutes(route.children);
      }

      res.push(tmp);
    }
    return res;
  }

  function resetRoutes() {
    if (router.hasRoute('PathMatch404')) {
      router.removeRoute('PathMatch404');
    }
    addedRoutes.value.forEach((route) => {
      if (route.name) {
        router.removeRoute(route.name);
      }
    });
    addedRoutes.value = [];
    routes.value = [];
  }

  return { routes, addedRoutes, generateRoutes, resetRoutes };
});
