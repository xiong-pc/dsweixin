<template>
  <div>
    <el-tooltip content="布局设置" placement="bottom">
      <div class="setting-btn" @click="visible = true">
        <el-icon><Setting /></el-icon>
      </div>
    </el-tooltip>

    <el-drawer v-model="visible" title="系统设置" size="320px" :with-header="true">
      <!-- 布局模式 -->
      <div class="setting-section">
        <div class="section-title">布局模式</div>
        <div class="layout-list">
          <div
            v-for="item in layouts"
            :key="item.value"
            class="layout-item"
            :class="{ active: appStore.layout === item.value }"
            @click="appStore.setLayout(item.value)"
          >
            <div class="layout-preview" :class="'preview-' + item.value">
              <div class="preview-sidebar"></div>
              <div class="preview-header"></div>
              <div class="preview-content"></div>
            </div>
            <span class="layout-label">{{ item.label }}</span>
          </div>
        </div>
      </div>

      <el-divider />

      <!-- 侧边栏颜色 -->
      <div class="setting-section">
        <div class="section-title">侧边栏主题</div>
        <div class="theme-list">
          <div
            v-for="item in sidebarThemes"
            :key="item.name"
            class="theme-item"
            :class="{ active: currentSidebarTheme === item.name }"
            :style="{ background: item.bg }"
            @click="applySidebarTheme(item)"
          >
            <el-icon v-if="currentSidebarTheme === item.name" color="#fff"><Check /></el-icon>
          </div>
        </div>
      </div>

      <el-divider />

      <!-- 主题设置 -->
      <div class="setting-section">
        <div class="section-title">主题设置</div>
        <div class="setting-item">
          <span>暗黑模式</span>
          <el-switch v-model="appStore.isDark" />
        </div>
        <div class="setting-item">
          <span>水印</span>
          <el-switch v-model="appStore.watermarkEnabled" />
        </div>
        <div class="setting-item" v-if="appStore.watermarkEnabled">
          <span>水印文字</span>
          <el-input v-model="appStore.watermarkText" size="small" style="width: 120px" />
        </div>
      </div>

      <el-divider />

      <!-- 界面显示 -->
      <div class="setting-section">
        <div class="section-title">界面显示</div>
        <div class="setting-item">
          <span>组件大小</span>
          <el-select v-model="appStore.size" size="small" style="width: 100px">
            <el-option label="默认" value="default" />
            <el-option label="大" value="large" />
            <el-option label="小" value="small" />
          </el-select>
        </div>
        <div class="setting-item">
          <span>语言</span>
          <el-select v-model="appStore.language" size="small" style="width: 100px">
            <el-option label="中文" value="zh-cn" />
            <el-option label="English" value="en" />
          </el-select>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useAppStore } from '@/store/app';

const appStore = useAppStore();
const visible = ref(false);
const currentSidebarTheme = ref('dark-blue');

const layouts = [
  { label: '左侧菜单', value: 'left' as const },
  { label: '顶部菜单', value: 'top' as const },
  { label: '混合布局', value: 'mix' as const },
];

const sidebarThemes = [
  { name: 'dark-blue', bg: '#001529', text: 'rgba(255,255,255,0.7)', active: '#0960bd', sub: '#000c17' },
  { name: 'dark', bg: '#212121', text: 'rgba(255,255,255,0.7)', active: '#409eff', sub: '#1a1a1a' },
  { name: 'purple', bg: '#304156', text: 'rgba(255,255,255,0.7)', active: '#409eff', sub: '#263445' },
  { name: 'blue', bg: '#003a8c', text: 'rgba(255,255,255,0.7)', active: '#1677ff', sub: '#002c6e' },
];

function applySidebarTheme(theme: typeof sidebarThemes[0]) {
  currentSidebarTheme.value = theme.name;
  const root = document.documentElement;
  root.style.setProperty('--sidebar-bg', theme.bg);
  root.style.setProperty('--sidebar-text', theme.text);
  root.style.setProperty('--sidebar-active-bg', theme.active);
  root.style.setProperty('--sidebar-submenu-bg', theme.sub);
  root.style.setProperty('--sidebar-logo-bg', theme.bg);
}
</script>

<style lang="scss" scoped>
.setting-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  font-size: 18px;
  cursor: pointer;
  border-radius: 6px;
  color: var(--el-text-color-regular);
  transition: all 0.2s;
  &:hover {
    background: var(--el-fill-color);
    color: var(--el-color-primary);
  }
}

.setting-section {
  .section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    margin-bottom: 14px;
  }
}

.layout-list {
  display: flex;
  gap: 16px;
}

.layout-item {
  cursor: pointer;
  text-align: center;
  transition: all 0.2s;

  &.active .layout-preview {
    outline: 2px solid var(--el-color-primary);
    outline-offset: 2px;
  }

  .layout-label {
    display: block;
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 6px;
  }

  &.active .layout-label {
    color: var(--el-color-primary);
    font-weight: 500;
  }
}

.layout-preview {
  width: 68px;
  height: 48px;
  border-radius: 6px;
  background: var(--el-fill-color-lighter);
  position: relative;
  overflow: hidden;
  border: 1px solid var(--el-border-color-lighter);

  .preview-sidebar {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 22px;
    background: #001529;
    border-radius: 6px 0 0 6px;
  }

  .preview-header {
    position: absolute;
    top: 0;
    right: 0;
    height: 12px;
    background: #fff;
    border-bottom: 1px solid #eee;
  }

  .preview-content {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 30px;
    height: 20px;
    background: var(--el-fill-color);
    border-radius: 2px;
  }
}

.preview-left {
  .preview-sidebar { display: block; }
  .preview-header { left: 22px; width: calc(100% - 22px); }
  .preview-content { left: 26px; }
}

.preview-top {
  .preview-sidebar { display: none; }
  .preview-header {
    left: 0;
    width: 100%;
    height: 14px;
    background: #001529;
    border-radius: 6px 6px 0 0;
    border: none;
  }
  .preview-content { left: 4px; width: calc(100% - 8px); }
}

.preview-mix {
  .preview-header {
    left: 0;
    width: 100%;
    height: 14px;
    background: #001529;
    border-radius: 6px 6px 0 0;
    border: none;
  }
  .preview-sidebar {
    top: 14px;
    height: calc(100% - 14px);
    width: 18px;
    background: #f0f2f5;
    border-radius: 0;
    border-right: 1px solid #eee;
  }
  .preview-content { left: 22px; }
}

.theme-list {
  display: flex;
  gap: 12px;
}

.theme-item {
  width: 36px;
  height: 36px;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  border: 2px solid transparent;

  &.active {
    border-color: var(--el-color-primary);
    box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.2);
  }

  &:hover {
    transform: scale(1.08);
  }
}

.setting-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  font-size: 14px;
  color: var(--el-text-color-regular);
}
</style>
