import { defineStore } from 'pinia';
import { ref } from 'vue';

export type LayoutType = 'left' | 'top' | 'mix';

const STORAGE_KEY = 'app_settings';

function loadSettings() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}

function saveSettings(settings: Record<string, unknown>) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
}

export const useAppStore = defineStore('app', () => {
  const saved = loadSettings();

  const sidebarOpened = ref<boolean>(saved.sidebarOpened ?? true);
  const layout = ref<LayoutType>(saved.layout ?? 'left');
  const isDark = ref<boolean>(saved.isDark ?? false);
  const isFullscreen = ref(false);
  const watermarkEnabled = ref<boolean>(saved.watermarkEnabled ?? false);
  const watermarkText = ref<string>(saved.watermarkText ?? 'Vue3 Admin');
  const language = ref<string>(saved.language ?? 'zh-cn');
  const size = ref<'default' | 'large' | 'small'>(saved.size ?? 'default');
  const activeTopMenu = ref('');

  function persist() {
    saveSettings({
      sidebarOpened: sidebarOpened.value,
      layout: layout.value,
      isDark: isDark.value,
      watermarkEnabled: watermarkEnabled.value,
      watermarkText: watermarkText.value,
      language: language.value,
      size: size.value,
    });
  }

  function toggleSidebar() {
    sidebarOpened.value = !sidebarOpened.value;
    persist();
  }

  function setLayout(val: LayoutType) {
    layout.value = val;
    persist();
  }

  function setIsDark(val: boolean) {
    isDark.value = val;
    persist();
  }

  function setLanguage(val: string) {
    language.value = val;
    persist();
  }

  function setSize(val: 'default' | 'large' | 'small') {
    size.value = val;
    persist();
  }

  function setWatermark(enabled: boolean, text?: string) {
    watermarkEnabled.value = enabled;
    if (text !== undefined) watermarkText.value = text;
    persist();
  }

  return {
    sidebarOpened, layout, isDark, isFullscreen,
    watermarkEnabled, watermarkText, language, size, activeTopMenu,
    toggleSidebar, setLayout, setIsDark, setLanguage, setSize, setWatermark,
  };
});
