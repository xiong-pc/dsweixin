<script setup lang="ts">
  import type { AuthIdentityType } from '~/types/auth'
  import { useAuthStore } from '~/stores/auth'

  /**
   * 登录页（M11-PR46）：支持密码登录 + 验证码登录两种 mode 切换。
   *
   * - mode=password：username（邮箱 / 手机）+ password
   * - mode=code：type 单选邮箱/手机 + target + sendCode → code → loginByCode（首次自动建号）
   * - 登录成功后跳 `redirect` query 或 /account
   */
  const route = useRoute()
  const router = useRouter()
  const localePath = useLocalePath()
  const auth = useAuthStore()
  const { t } = useI18n()

  const mode = ref<'password' | 'code'>('password')

  const passwordForm = reactive({ username: '', password: '' })
  const codeForm = reactive<{ type: AuthIdentityType; target: string; code: string }>({
    type: 'email',
    target: '',
    code: '',
  })

  const codeSent = ref(false)
  const sending = ref(false)
  const errorMsg = ref<string | null>(null)

  useHead({ title: t('auth.login_title') })

  const redirectTo = computed(() => {
    const r = String(route.query.redirect ?? '')
    return r && r.startsWith('/') ? r : localePath('/account')
  })

  async function submitPassword() {
    errorMsg.value = null
    try {
      await auth.login(passwordForm.username, passwordForm.password)
      await router.replace(redirectTo.value)
    } catch (e) {
      errorMsg.value = (e as Error).message
    }
  }

  async function sendCode() {
    if (!codeForm.target) {
      errorMsg.value = t('auth.target_required')
      return
    }
    sending.value = true
    errorMsg.value = null
    try {
      await auth.sendCode({ type: codeForm.type, target: codeForm.target })
      codeSent.value = true
    } catch (e) {
      errorMsg.value = (e as Error).message
    } finally {
      sending.value = false
    }
  }

  async function submitCode() {
    errorMsg.value = null
    try {
      await auth.loginByCode(codeForm)
      await router.replace(redirectTo.value)
    } catch (e) {
      errorMsg.value = (e as Error).message
    }
  }
</script>

<template>
  <section class="mx-auto max-w-md px-4 py-12">
    <h1 class="text-2xl font-bold text-gray-900">{{ t('auth.login_title') }}</h1>
    <p class="mt-2 text-sm text-gray-500">{{ t('auth.login_subtitle') }}</p>

    <!-- mode 切换 -->
    <div class="mt-6 inline-flex rounded-md border border-gray-200 p-1 text-xs">
      <button
        type="button"
        class="rounded px-3 py-1.5"
        :class="mode === 'password' ? 'bg-primary text-white' : 'text-gray-700'"
        @click="mode = 'password'"
      >
        {{ t('auth.password_mode') }}
      </button>
      <button
        type="button"
        class="rounded px-3 py-1.5"
        :class="mode === 'code' ? 'bg-primary text-white' : 'text-gray-700'"
        @click="mode = 'code'"
      >
        {{ t('auth.code_mode') }}
      </button>
    </div>

    <!-- 密码登录 -->
    <form v-if="mode === 'password'" class="mt-6 space-y-4" @submit.prevent="submitPassword">
      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ t('auth.username') }}</span>
        <input
          v-model="passwordForm.username"
          type="text"
          required
          autocomplete="username"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>
      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ t('auth.password') }}</span>
        <input
          v-model="passwordForm.password"
          type="password"
          required
          autocomplete="current-password"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>

      <p v-if="errorMsg" class="text-sm text-red-500">{{ errorMsg }}</p>

      <button
        type="submit"
        :disabled="auth.loading"
        class="w-full rounded-md bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <span v-if="auth.loading">{{ t('common.loading') }}</span>
        <span v-else>{{ t('auth.submit_login') }}</span>
      </button>
    </form>

    <!-- 验证码登录 -->
    <form v-else class="mt-6 space-y-4" @submit.prevent="submitCode">
      <div class="flex gap-2 text-xs">
        <label class="flex items-center gap-1">
          <input v-model="codeForm.type" type="radio" value="email" /> {{ t('auth.type_email') }}
        </label>
        <label class="flex items-center gap-1">
          <input v-model="codeForm.type" type="radio" value="phone" /> {{ t('auth.type_phone') }}
        </label>
      </div>

      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">
          {{ codeForm.type === 'email' ? t('auth.email') : t('auth.phone') }}
        </span>
        <div class="flex gap-2">
          <input
            v-model="codeForm.target"
            :type="codeForm.type === 'email' ? 'email' : 'tel'"
            required
            class="block flex-1 rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
          />
          <button
            type="button"
            :disabled="sending || !codeForm.target"
            class="rounded border border-primary px-3 py-2 text-xs text-primary transition hover:bg-primary/10 disabled:opacity-50"
            @click="sendCode"
          >
            {{
              sending ? t('common.loading') : codeSent ? t('auth.code_resent') : t('auth.send_code')
            }}
          </button>
        </div>
      </label>

      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ t('auth.code') }}</span>
        <input
          v-model="codeForm.code"
          type="text"
          required
          maxlength="6"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>

      <p v-if="errorMsg" class="text-sm text-red-500">{{ errorMsg }}</p>

      <button
        type="submit"
        :disabled="auth.loading"
        class="w-full rounded-md bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <span v-if="auth.loading">{{ t('common.loading') }}</span>
        <span v-else>{{ t('auth.submit_login') }}</span>
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
      {{ t('auth.no_account') }}
      <NuxtLink :to="localePath('/register')" class="text-primary hover:underline">
        {{ t('auth.go_register') }}
      </NuxtLink>
    </p>
  </section>
</template>
