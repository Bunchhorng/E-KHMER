<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { CheckCircle2, LoaderCircle, Mail } from 'lucide-vue-next'
import { authApi } from '@/api/auth'
import { extractErrorMessage } from '@/api/errors'

const { t } = useI18n()

const form = reactive({ email: '' })
const error = ref('')
const sent = ref(false)
const loading = ref(false)

// Drop a stale rejection as soon as the address is corrected.
watch(() => form.email, () => { error.value = '' })

async function submit() {
  error.value = ''
  if (!/.+@.+\..+/.test(form.email)) {
    error.value = t('error.email_invalid')
    return
  }
  loading.value = true
  try {
    await authApi.forgotPassword(form.email)
    sent.value = true
  } catch (e) {
    // The API replies 200 even for addresses with no account, so the success
    // screen must never be shown as a fallback for a failure. Reaching this
    // branch means a real problem: invalid payload, rate limiting, or network.
    error.value = extractErrorMessage(e, t('error.reset_request_failed'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="w-full max-w-md">
    <div class="card p-8">
      <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-ink dark:text-ink">{{ $t('auth.forgot_password_title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-muted">
          {{ $t('auth.forgot_password_subtitle') }}
        </p>
      </div>

      <template v-if="!sent">
        <form class="space-y-4" @submit.prevent="submit">
          <div>
            <label class="label" for="fp-email">{{ $t('auth.email') }}</label>
            <input
              id="fp-email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              class="input"
              :class="{ 'input-error': Boolean(error) }"
              placeholder="you@example.com"
            />
            <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
          </div>

          <button type="submit" class="btn-primary flex w-full items-center justify-center gap-2" :disabled="loading">
            <LoaderCircle v-if="loading" class="h-4 w-4 animate-spin" />
            <Mail v-else class="h-4 w-4" />
            {{ loading ? $t('auth.sending') : $t('auth.send_reset_link') }}
          </button>
        </form>
      </template>

      <template v-else>
        <p class="flex items-start gap-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 p-3 text-sm font-medium text-emerald-700 dark:text-success">
          <CheckCircle2 class="h-4 w-4 shrink-0" />
          {{ $t('auth.reset_sent', { email: form.email }) }}
        </p>
      </template>

      <p class="mt-6 text-center text-sm text-gray-500 dark:text-muted">
        {{ $t('auth.have_account') }}
        <RouterLink to="/auth/login" class="font-medium text-primary hover:text-primary-dark">{{ $t('auth.back_to_sign_in') }}</RouterLink>
      </p>
    </div>
  </div>
</template>
