<script setup lang="ts">
  import type { AuthIdentityType } from '~/types/auth'
  import { useAuthStore } from '~/stores/auth'

  /**
   * 注册页（M11-PR46）：邮箱 / 手机 + 密码 + 验证码 + 可选昵称。
   *
   * 一定要先点 "发送验证码" 才能 submit；后端会校验 vcode 有效。
   * 注册成功后自动登录，跳到 /account。
   */
  const router = useRouter()
  const localePath = useLocalePath()
  const auth = useAuthStore()
  const { t } = useI18n()

  useHead({ title: t('auth.register_title') })

  const form = reactive<{
    type: AuthIdentityType
    target: string
    password: string
    code: string
    name: string
  }>({
    type: 'email',
    target: '',
    password: '',
    code: '',
    name: '',
  })

  const sending = ref(false)
  const codeSent = ref(false)
  const errorMsg = ref<string | null>(null)

  async function sendCode() {
    if (!form.target) {
      errorMsg.value = t('auth.target_required')
      return
    }
    sending.value = true
    errorMsg.value = null
    try {
      await auth.sendCode({ type: form.type, target: form.target })
      codeSent.value = true
    } catch (e) {
      errorMsg.value = (e as Error).message
    } finally {
      sending.value = false
    }
  }

  async function submit() {
    errorMsg.value = null
    try {
      await auth.register({
        type: form.type,
        target: form.target,
        password: form.password,
        code: form.code,
        name: form.name || undefined,
      })
      await router.replace(localePath('/account'))
    } catch (e) {
      errorMsg.value = (e as Error).message
    }
  }
</script>

<template>
  <section class="mx-auto max-w-md px-4 py-12">
    <h1 class="text-2xl font-bold text-gray-900">{{ t('auth.register_title') }}</h1>
    <p class="mt-2 text-sm text-gray-500">{{ t('auth.register_subtitle') }}</p>

    <form class="mt-6 space-y-4" @submit.prevent="submit">
      <div class="flex gap-2 text-xs">
        <label class="flex items-center gap-1">
          <input v-model="form.type" type="radio" value="email" /> {{ t('auth.type_email') }}
        </label>
        <label class="flex items-center gap-1">
          <input v-model="form.type" type="radio" value="phone" /> {{ t('auth.type_phone') }}
        </label>
      </div>

      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">
          {{ form.type === 'email' ? t('auth.email') : t('auth.phone') }}
        </span>
        <div class="flex gap-2">
          <input
            v-model="form.target"
            :type="form.type === 'email' ? 'email' : 'tel'"
            required
            class="block flex-1 rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
          />
          <button
            type="button"
            :disabled="sending || !form.target"
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
          v-model="form.code"
          type="text"
          required
          maxlength="6"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>

      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ t('auth.password') }}</span>
        <input
          v-model="form.password"
          type="password"
          required
          minlength="6"
          autocomplete="new-password"
          class="block w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none"
        />
      </label>

      <label class="block">
        <span class="mb-1 block text-xs text-gray-600">{{ t('auth.name_optional') }}</span>
        <input
          v-model="form.name"
          type="text"
          autocomplete="name"
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
        <span v-else>{{ t('auth.submit_register') }}</span>
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
      {{ t('auth.have_account') }}
      <NuxtLink :to="localePath('/login')" class="text-primary hover:underline">
        {{ t('auth.go_login') }}
      </NuxtLink>
    </p>
  </section>
</template>
