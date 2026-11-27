<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { AlertTriangle, Check, CheckCircle2, Eye, EyeOff, KeyRound, LoaderCircle } from 'lucide-vue-next'
import { authApi } from '@/api/auth'
import { extractErrorMessage } from '@/api/errors'

const { t } = useI18n()
const route = useRoute()

const email = ref(String(route.query.email ?? ''))
const token = ref(String(route.query.token ?? ''))
const hasValidParams = computed(() => Boolean(token.value.trim() && email.value.trim()))

const form = reactive({ password: '', password_confirmation: '' })
const showPassword = ref(false)
const showConfirm = ref(false)
const error = ref('')
const fieldErrors = reactive({ password: '', confirm: '' })
const reset = ref(false)
const loading = ref(false)

const passwordMeetsMin = computed(() => form.password.length >= 8)

watch(() => form.password, () => {
  fieldErrors.password = ''
  fieldErrors.confirm = ''
  error.value = ''
})
watch(() => form.password_confirmation, () => {
  fieldErrors.confirm = ''
  error.value = ''
})

async function submit(): Promise<void> {
  if (loading.value) return
  error.value = ''
  fieldErrors.password = form.password.length >= 8 ? '' : t('error.password_short')
  fieldErrors.confirm =
    form.password_confirmation && form.password === form.password_confirmation ? '' : t('error.password_mismatch')

  if (fieldErrors.password || fieldErrors.confirm) return

  if (!hasValidParams.value) {
    error.value = t('error.reset_link_invalid')
    return
  }

  loading.value = true
  try {
    await authApi.resetPassword({
      email: email.value,
      token: token.value,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    reset.value = true
  } catch (e) {
    // 422 means the token/email combination was rejected; 429 means the
    // attempt was throttled. Both are worth naming precisely.
    error.value = extractErrorMessage(e, t('error.reset_link_invalid'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="w-full">
    <div class="mb-6">
      <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $t('auth.reset_password_title') }}</h1>
      <p class="mt-1.5 text-sm text-text-secondary">{{ $t('auth.reset_password_subtitle') }}</p>
    </div>

    <template v-if="!hasValidParams && !reset">
      <div
        role="alert"
        class="space-y-3 rounded-none border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-900 dark:text-amber-200"
      >
        <div class="flex items-start gap-2.5">
          <AlertTriangle :size="18" class="mt-0.5 shrink-0" aria-hidden="true" />
          <span>{{ $t('error.reset_link_invalid') }}</span>
        </div>
        <RouterLink to="/auth/forgot-password" class="btn-primary min-h-11 w-full rounded-none">
          {{ $t('auth.request_new_link') }}
        </RouterLink>
      </div>
    </template>

    <template v-else-if="!reset">
      <form class="space-y-4" novalidate @submit.prevent="submit">
        <div>
          <label class="label" for="rp-password">{{ $t('auth.new_password') }}</label>
          <div class="relative">
            <input
              id="rp-password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password"
              class="input min-h-11 rounded-none bg-canvas pr-12 dark:bg-canvas"
              :class="{ 'input-error border-danger': Boolean(fieldErrors.password || error) }"
              :placeholder="$t('auth.password_placeholder')"
              :aria-invalid="Boolean(fieldErrors.password)"
              aria-describedby="rp-password-hint rp-password-error"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-none text-muted transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-border-focus"
              :aria-label="showPassword ? $t('auth.hide_password') : $t('auth.show_password')"
              :aria-pressed="showPassword"
              @click="showPassword = !showPassword"
            >
              <EyeOff v-if="showPassword" :size="18" aria-hidden="true" />
              <Eye v-else :size="18" aria-hidden="true" />
            </button>
          </div>
          <p
            id="rp-password-hint"
            class="mt-1.5 flex items-center gap-1.5 text-xs"
            :class="passwordMeetsMin ? 'font-medium text-emerald-700 dark:text-success' : 'text-muted'"
          >
            <Check v-if="passwordMeetsMin" :size="13" aria-hidden="true" />
            <span>{{ passwordMeetsMin ? $t('auth.password_met') : $t('auth.password_requirement') }}</span>
          </p>
          <p v-if="fieldErrors.password" id="rp-password-error" role="alert" class="mt-1 text-xs font-medium text-danger">
            {{ fieldErrors.password }}
          </p>
        </div>

        <div>
          <label class="label" for="rp-password-confirm">{{ $t('auth.confirm_password') }}</label>
          <div class="relative">
            <input
              id="rp-password-confirm"
              v-model="form.password_confirmation"
              :type="showConfirm ? 'text' : 'password'"
              autocomplete="new-password"
              class="input min-h-11 rounded-none bg-canvas pr-12 dark:bg-canvas"
              :class="{ 'input-error border-danger': Boolean(fieldErrors.confirm || error) }"
              :placeholder="$t('auth.confirm_password')"
              :aria-invalid="Boolean(fieldErrors.confirm)"
              :aria-describedby="fieldErrors.confirm ? 'rp-confirm-error' : undefined"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-none text-muted transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-border-focus"
              :aria-label="showConfirm ? $t('auth.hide_password') : $t('auth.show_password')"
              :aria-pressed="showConfirm"
              @click="showConfirm = !showConfirm"
            >
              <EyeOff v-if="showConfirm" :size="18" aria-hidden="true" />
              <Eye v-else :size="18" aria-hidden="true" />
            </button>
          </div>
          <p v-if="fieldErrors.confirm" id="rp-confirm-error" role="alert" class="mt-1 text-xs font-medium text-danger">
            {{ fieldErrors.confirm }}
          </p>
        </div>

        <div
          v-if="error"
          role="alert"
          class="space-y-2 rounded-none border-l-2 border-danger bg-danger/10 px-3 py-2.5 text-xs font-medium text-danger"
        >
          <p>{{ error }}</p>
          <RouterLink to="/auth/forgot-password" class="inline-block font-semibold underline">
            {{ $t('auth.request_new_link') }}
          </RouterLink>
        </div>

        <button
          type="submit"
          class="btn-primary min-h-11 w-full rounded-none"
          :disabled="loading"
          :aria-busy="loading"
        >
          <LoaderCircle v-if="loading" :size="16" class="animate-spin" aria-hidden="true" />
          <KeyRound v-else :size="16" aria-hidden="true" />
          {{ loading ? $t('auth.sending') : $t('auth.reset_password') }}
        </button>
      </form>
    </template>

    <template v-else>
      <div class="space-y-4">
        <div
          role="status"
          class="flex items-start gap-2.5 rounded-none border border-emerald-300 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-success/40 dark:bg-success/10 dark:text-success"
        >
          <CheckCircle2 :size="18" class="mt-0.5 shrink-0" aria-hidden="true" />
          <span>{{ $t('auth.password_reset_success') }}</span>
        </div>
        <RouterLink to="/auth/login" class="btn-primary min-h-11 w-full rounded-none">
          {{ $t('auth.go_to_sign_in') }}
        </RouterLink>
      </div>
    </template>

    <p v-if="!reset" class="mt-6 border-t border-border-gray pt-5 text-center text-sm text-text-secondary">
      {{ $t('auth.have_account') }}
      <RouterLink
        to="/auth/login"
        class="ml-1 font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
      >
        {{ $t('auth.go_to_sign_in') }}
      </RouterLink>
    </p>
  </div>
</template>
