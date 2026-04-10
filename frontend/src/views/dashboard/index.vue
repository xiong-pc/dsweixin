<template>
  <div class="app-container">
    <el-row :gutter="20">
      <el-col :span="6" v-for="item in statCards" :key="item.title">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-card-content">
            <div class="stat-info">
              <span class="stat-title">{{ item.title }}</span>
              <span class="stat-value">{{ item.value }}</span>
            </div>
            <el-icon :size="48" :style="{ color: item.color }">
              <component :is="item.icon" />
            </el-icon>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px">
      <el-col :span="16">
        <el-card shadow="hover">
          <template #header>
            <span>快捷入口</span>
          </template>
          <el-row :gutter="12">
            <el-col :span="6" v-for="shortcut in shortcuts" :key="shortcut.title">
              <div class="shortcut-item" @click="router.push(shortcut.path)">
                <el-icon :size="32" :color="shortcut.color"><component :is="shortcut.icon" /></el-icon>
                <span>{{ shortcut.title }}</span>
              </div>
            </el-col>
          </el-row>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>
            <span>系统信息</span>
          </template>
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="前端框架">Vue3 + Element Plus</el-descriptions-item>
            <el-descriptions-item label="后端框架">Laravel</el-descriptions-item>
            <el-descriptions-item label="构建工具">Vite</el-descriptions-item>
            <el-descriptions-item label="语言">TypeScript</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const statCards = reactive([
  { title: '用户总数', value: '1,280', icon: 'UserFilled', color: '#409eff' },
  { title: '角色数量', value: '8', icon: 'Avatar', color: '#67c23a' },
  { title: '菜单数量', value: '36', icon: 'Menu', color: '#e6a23c' },
  { title: '在线人数', value: '56', icon: 'Connection', color: '#f56c6c' },
]);

const shortcuts = reactive([
  { title: '用户管理', path: '/system/user', icon: 'User', color: '#409eff' },
  { title: '角色管理', path: '/system/role', icon: 'Avatar', color: '#67c23a' },
  { title: '菜单管理', path: '/system/menu', icon: 'Menu', color: '#e6a23c' },
  { title: '部门管理', path: '/system/dept', icon: 'OfficeBuilding', color: '#f56c6c' },
]);
</script>

<style lang="scss" scoped>
.stat-card {
  margin-bottom: 20px;
  .stat-card-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    .stat-info {
      display: flex;
      flex-direction: column;
      .stat-title { font-size: 14px; color: #999; }
      .stat-value { font-size: 28px; font-weight: 700; color: #333; margin-top: 8px; }
    }
  }
}

.shortcut-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20px 0;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.3s;
  &:hover { background: var(--el-fill-color-lighter); }
  span { margin-top: 8px; font-size: 13px; color: #666; }
}
</style>
