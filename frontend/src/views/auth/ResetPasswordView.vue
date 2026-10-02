<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { CheckCircle2, KeyRound, LoaderCircle } from 'lucide-vue-next'
import { authApi } from '@/api/auth'
import { extractErrorMessage } from '@/api/errors'

const { t } = useI18n()
const route = useRoute()

const email = ref(String(route.query.email ?? ''))
const token = ref(String(route.query.token ?? ''))
const form = reactive({ password: '', password_confirmation: '' })
const error = ref('')
const reset = ref(false)
const loading = ref(false)

function submit() {
  error.value = ''
  if (form.password.length < 8) {
    error.value = t('error.password_short')
    return
  }
  if (form.password !== form.password_confirmation) {
    error.value = t('error.password_mismatch')
    return
  }
  if (!token.value || !email.value) {
    error.value = t('error.reset_link_invalid')
    return
  }

  loading.value = true
  authApi
    .resetPassword({
      email: email.value,
      token: token.value,
      password: form.password,
      password_confirmation: form.password_confirmation
    })
    .then(() => {
      reset.value = true
    })
    .catch((e) => {
      // 422 means the token/email combination was rejected; 429 means the
      // attempt was throttled. Both are worth naming precisely.
      error.value = extractErrorMessage(e, t('error.reset_link_invalid'))
    })
    .finally(() => {
      loading.value = false
    })
}
</script>

<template>
  <div class="w-full max-w-md">
    <div class="card p-8">
      <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-ink dark:text-ink">{{ $t('auth.reset_password_title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-muted">
          {{ $t('auth.reset_password_subtitle') }}
        </p>
      </div>

      <template v-if="!reset">
        <form class="space-y-4" @submit.prevent="submit">
          <div>
            <label class="label" for="rp-password">{{ $t('auth.new_password') }}</label>
            <input
              id="rp-password"
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              class="input"
              :class="{ 'input-error': Boolean(error) }"
              :placeholder="$t('auth.password_placeholder')"
            />
          </div>

          <div>
            <label class="label" for="rp-password-confirm">{{ $t('auth.confirm_password') }}</label>
            <input
              id="rp-password-confirm"
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              class="input"
              :class="{ 'input-error': Boolean(error) }"
              :placeholder="$t('auth.confirm_password')"
            />
          </div>

          <p v-if="error" class="rounded-lg bg-red-50 dark:bg-red-900/30 px-3 py-2 text-xs font-medium text-red-500">
            {{ error }}
          </p>

          <button type="submit" class="btn-primary flex w-full items-center justify-center gap-2" :disabled="loading">
            <LoaderCircle v-if="loading" class="h-4 w-4 animate-spin" />
            <KeyRound v-else class="h-4 w-4" />
            {{ loading ? $t('auth.sending') : $t('auth.reset_password') }}
          </button>
        </form>
      </template>

      <template v-else>
        <p class="flex items-start gap-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 p-3 text-sm font-medium text-emerald-700 dark:text-success">
          <CheckCircle2 class="h-4 w-4 shrink-0" />
          {{ $t('auth.password_reset_success') }}
        </p>
      </template>

      <p class="mt-6 text-center text-sm text-gray-500 dark:text-muted">
        {{ $t('auth.have_account') }}
        <RouterLink to="/auth/login" class="font-medium text-primary hover:text-primary-dark">{{ $t('auth.go_to_sign_in') }}</RouterLink>
      </p>
    </div>
  </div>
</template>