import router from '@/router';
import { useUserStore } from '@/store/user';
import { usePermissionStore } from '@/store/permission';
import NProgress from 'nprogress';
import 'nprogress/nprogress.css';

NProgress.configure({ showSpinner: false });

const whiteList = ['/login', '/404', '/403', '/500'];

router.beforeEach(async (to, _from, next) => {
  NProgress.start();
  const userStore = useUserStore();
  const permissionStore = usePermissionStore();

  if (userStore.token) {
    if (to.path === '/login') {
      next({ path: '/' });
    } else {
      if (!userStore.userInfoLoaded) {
        try {
          await userStore.getUserInfo();
          await permissionStore.generateRoutes();
          next({ ...to, replace: true });
        } catch (error) {
          userStore.resetToken();
          next(`/login?redirect=${to.path}`);
        }
      } else {
        next();
      }
    }
  } else {
    if (whiteList.includes(to.path)) {
      next();
    } else {
      next(`/login?redirect=${to.path}`);
    }
  }
});

router.afterEach(() => {
  NProgress.done();
});
