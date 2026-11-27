<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, CheckCircle2, LoaderCircle, Mail } from 'lucide-vue-next'
import { authApi } from '@/api/auth'
import { extractErrorMessage } from '@/api/errors'

const { t } = useI18n()

const form = reactive({ email: '' })
const error = ref('')
const sent = ref(false)
const loading = ref(false)

// Drop a stale rejection as soon as the address is corrected.
watch(() => form.email, () => { error.value = '' })

async function submit(): Promise<void> {
  if (loading.value) return
  error.value = ''
  if (!/.+@.+\..+/.test(form.email.trim())) {
    error.value = t('error.email_invalid')
    return
  }
  loading.value = true
  try {
    await authApi.forgotPassword(form.email.trim())
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
  <div class="w-full">
    <div class="mb-6">
      <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $t('auth.forgot_password_title') }}</h1>
      <p class="mt-1.5 text-sm text-text-secondary">{{ $t('auth.forgot_password_subtitle') }}</p>
    </div>

    <template v-if="!sent">
      <form class="space-y-4" novalidate @submit.prevent="submit">
        <div>
          <label class="label" for="fp-email">{{ $t('auth.email') }}</label>
          <input
            id="fp-email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            class="input min-h-11 rounded-none bg-canvas dark:bg-canvas"
            :class="{ 'input-error border-danger': Boolean(error) }"
            placeholder="you@example.com"
            :aria-invalid="Boolean(error)"
            :aria-describedby="error ? 'fp-email-error' : undefined"
          />
          <p v-if="error" id="fp-email-error" role="alert" class="mt-1 text-xs font-medium text-danger">
            {{ error }}
          </p>
        </div>

        <button
          type="submit"
          class="btn-primary min-h-11 w-full rounded-none"
          :disabled="loading"
          :aria-busy="loading"
        >
          <LoaderCircle v-if="loading" :size="16" class="animate-spin" aria-hidden="true" />
          <Mail v-else :size="16" aria-hidden="true" />
          {{ loading ? $t('auth.sending') : $t('auth.send_reset_link') }}
        </button>
      </form>
    </template>

    <template v-else>
      <div
        role="status"
        class="flex items-start gap-2.5 rounded-none border border-emerald-300 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-success/40 dark:bg-success/10 dark:text-success"
      >
        <CheckCircle2 :size="18" class="mt-0.5 shrink-0" aria-hidden="true" />
        <span>{{ $t('auth.reset_sent', { email: form.email }) }}</span>
      </div>
    </template>

    <div class="mt-6 border-t border-border-gray pt-5 text-center">
      <RouterLink
        to="/auth/login"
        class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
      >
        <ArrowLeft :size="15" aria-hidden="true" />
        {{ $t('auth.back_to_sign_in') }}
      </RouterLink>
    </div>
  </div>
</template>
