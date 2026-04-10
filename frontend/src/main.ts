import { createApp } from 'vue';
import App from './App.vue';
import { setupPlugins } from '@/plugins';
import '@/styles/index.scss';
import '@/styles/sidebar.scss';

const app = createApp(App);
setupPlugins(app);
app.mount('#app');
