<template>
  <div class="login-container">
    <div class="login-bg"></div>
    <div class="login-card">
      <div class="login-header">
        <img src="@/assets/logo.svg" alt="Logo" class="login-logo" />
        <h2>Vue3 Element Admin</h2>
        <p>企业级后台管理系统</p>
      </div>
      <el-form ref="loginFormRef" :model="loginForm" :rules="loginRules" size="large">
        <el-form-item prop="username">
          <el-input
            v-model="loginForm.username"
            :placeholder="t('login.username')"
            :prefix-icon="User"
            clearable
          />
        </el-form-item>
        <el-form-item prop="password">
          <el-input
            v-model="loginForm.password"
            type="password"
            :placeholder="t('login.password')"
            :prefix-icon="Lock"
            show-password
            @keyup.enter="handleLogin"
          />
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            :loading="loading"
            class="login-btn"
            @click="handleLogin"
          >
            {{ t('login.login') }}
          </el-button>
        </el-form-item>
      </el-form>
      <div class="login-footer">
        <span>默认账号: superadmin / 123456</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { User, Lock } from '@element-plus/icons-vue';
import { useUserStore } from '@/store/user';
import type { FormInstance } from 'element-plus';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const userStore = useUserStore();

const loginFormRef = ref<FormInstance>();
const loading = ref(false);

const loginForm = reactive({
  username: 'superadmin',
  password: '123456',
});

const loginRules = {
  username: [{ required: true, message: t('login.usernameRule'), trigger: 'blur' }],
  password: [{ required: true, message: t('login.passwordRule'), trigger: 'blur' }],
};

async function handleLogin() {
  const valid = await loginFormRef.value?.validate().catch(() => false);
  if (!valid) return;

  loading.value = true;
  try {
    await userStore.login(loginForm);
    const redirect = (route.query.redirect as string) || '/';
    router.push(redirect);
  } finally {
    loading.value = false;
  }
}
</script>

<style lang="scss" scoped>
.login-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  position: relative;
  overflow: hidden;
  background: #0b1120;
}

.login-bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 20% 50%, rgba(37, 99, 235, 0.15) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.12) 0%, transparent 50%),
    radial-gradient(ellipse at 50% 80%, rgba(6, 182, 212, 0.1) 0%, transparent 50%);
}

.login-card {
  position: relative;
  width: 420px;
  padding: 40px 36px 30px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 16px;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(12px);

  :deep(.el-input__wrapper) {
    border-radius: 8px;
    box-shadow: 0 0 0 1px var(--el-border-color) inset;
    &:hover { box-shadow: 0 0 0 1px var(--el-color-primary) inset; }
  }
}

.login-header {
  text-align: center;
  margin-bottom: 32px;

  .login-logo {
    width: 48px;
    height: 48px;
    margin-bottom: 12px;
  }

  h2 {
    font-size: 22px;
    color: #1a1a1a;
    margin: 0 0 6px;
    font-weight: 700;
    letter-spacing: 0.5px;
  }

  p {
    font-size: 13px;
    color: #999;
    margin: 0;
  }
}

.login-btn {
  width: 100%;
  height: 44px;
  font-size: 16px;
  border-radius: 8px;
  letter-spacing: 4px;
}

.login-footer {
  text-align: center;
  margin-top: 16px;
  font-size: 12px;
  color: #bbb;
}
</style>
