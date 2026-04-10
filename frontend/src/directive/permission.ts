import { type Directive, type DirectiveBinding } from 'vue';
import { useUserStore } from '@/store/user';

export const hasPerm: Directive = {
  mounted(el: HTMLElement, binding: DirectiveBinding) {
    const { value } = binding;
    const userStore = useUserStore();
    const permissions = userStore.permissions;

    if (value && value instanceof Array && value.length > 0) {
      const hasPermission = permissions.some(
        (perm) => perm === '*' || value.includes(perm)
      );
      if (!hasPermission) {
        el.parentNode && el.parentNode.removeChild(el);
      }
    }
  },
};
