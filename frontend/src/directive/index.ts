import type { App } from 'vue';
import { hasPerm } from './permission';

export function setupDirectives(app: App) {
  app.directive('hasPerm', hasPerm);
}
