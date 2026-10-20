<script setup lang="ts">
import { CheckCircle2, Eye, EyeOff, ShieldCheck } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { computed, reactive, ref } from 'vue'
import { accountApi } from '@/api/account'

const { t } = useI18n()

const form = reactive({
  current: '',
  password: '',
  confirm: ''
})

const errors = reactive({
  current: '',
  password: '',
  confirm: ''
})

const success = ref(false)
const submitting = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)

const strengthScore = computed(() => {
  const password = form.password
  if (!password) return 0
  if (password.length < 8) return 1
  return 1 + Number(password.length >= 12) + Number(/[a-z]/.test(password) && /[A-Z]/.test(password)) + Number(/\d/.test(password) && /[^a-zA-Z0-9]/.test(password))
})
const strengthPercent = computed(() => strengthScore.value * 25)

const strengthBar = computed(() => {
  const score = strengthScore.value
  if (score === 0) return 'bg-gray-200'
  if (score === 1) return 'bg-red-500'
  if (score === 2) return 'bg-accent'
  return 'bg-success'
})

const strengthLabel = computed(() => {
  const score = strengthScore.value
  if (score === 0) return ''
  if (score === 1) return t('account.password_weak')
  if (score === 2) return t('account.password_fair')
  if (score === 3) return t('account.password_good')
  return t('account.password_strong')
})

async function submit() {
  if (submitting.value) return
  errors.current = form.current.length ? '' : t('account.require_current_password')
  errors.password = form.password.length >= 8 ? '' : t('error.password_short')
  errors.confirm = form.confirm && form.confirm === form.password ? '' : t('error.password_mismatch')
  success.value = false
  if (errors.current || errors.password || errors.confirm) return

  submitting.value = true
  try {
    await accountApi.changePassword({
      current_password: form.current,
      password: form.password,
      password_confirmation: form.confirm
    })
    success.value = true
    form.current = ''
    form.password = ''
    form.confirm = ''
  } catch (error: unknown) {
    const e = error as { response?: { data?: { errors?: Record<string, string[]> } } }
    const currentError = e.response?.data?.errors?.current_password?.[0]
    errors.current = currentError ?? t('account.password_change_failed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="w-full">
  <div class="grid max-w-5xl items-start gap-6 xl:grid-cols-3">
    <div class="card space-y-6 p-5 sm:p-6 xl:col-span-2">

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label" for="cp-current">{{ $t('account.current_password') }}</label>
          <input
            id="cp-current"
            v-model="form.current"
            type="password"
            autocomplete="current-password"
            required
            :aria-invalid="Boolean(errors.current)"
            class="input"
            :class="{ 'input-error': Boolean(errors.current) }"
          />
          <p v-if="errors.current" class="mt-1 text-xs text-red-500">{{ errors.current }}</p>
        </div>

        <div>
          <label class="label" for="cp-new">{{ $t('account.new_password') }}</label>
          <div class="relative">
            <input
              id="cp-new"
              v-model="form.password"
              :type="showNew ? 'text' : 'password'"
              autocomplete="new-password"
              required
              minlength="8"
              :aria-invalid="Boolean(errors.password)"
              class="input pr-10"
              :class="{ 'input-error': Boolean(errors.password) }"
            />
            <button
              type="button"
              class="btn-icon absolute right-0 top-1/2 -translate-y-1/2"
              :aria-label="$t(showNew ? 'account.ui.hide_password' : 'account.ui.show_password')"
              :aria-pressed="showNew"
              @click="showNew = !showNew"
            >
              <Eye v-if="!showNew" class="h-4 w-4" />
              <EyeOff v-else class="h-4 w-4" />
            </button>
          </div>
          <p v-if="errors.password" class="mt-1 text-xs text-red-500">{{ errors.password }}</p>
          <div class="mt-2">
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-surface">
              <div
                class="h-full rounded-full transition-all duration-300"
                :class="strengthBar"
                :style="{ width: `${strengthPercent}%` }"
              ></div>
            </div>
            <p v-if="strengthLabel" class="mt-1 text-xs text-gray-500 dark:text-muted">
              {{ $t('account.password_strength', { level: strengthLabel }) }}
            </p>
          </div>
        </div>

        <div>
          <label class="label" for="cp-confirm">{{ $t('account.confirm_new_password') }}</label>
          <div class="relative">
            <input
              id="cp-confirm"
              v-model="form.confirm"
              :type="showConfirm ? 'text' : 'password'"
              autocomplete="new-password"
              required
              :aria-invalid="Boolean(errors.confirm)"
              class="input pr-10"
              :class="{ 'input-error': Boolean(errors.confirm) }"
            />
            <button
              type="button"
              class="btn-icon absolute right-0 top-1/2 -translate-y-1/2"
              :aria-label="$t(showConfirm ? 'account.ui.hide_password' : 'account.ui.show_password')"
              :aria-pressed="showConfirm"
              @click="showConfirm = !showConfirm"
            >
              <Eye v-if="!showConfirm" class="h-4 w-4" />
              <EyeOff v-else class="h-4 w-4" />
            </button>
          </div>
          <p v-if="errors.confirm" class="mt-1 text-xs text-red-500">{{ errors.confirm }}</p>
        </div>

        <button type="submit" class="btn-primary w-full" :disabled="submitting">
          {{ submitting ? $t('account.updating_password') : $t('account.update_password') }}
        </button>
      </form>

      <p v-if="success" role="status" class="flex items-start gap-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 p-3 text-sm font-medium text-emerald-700 dark:text-success">
        <CheckCircle2 class="h-4 w-4 shrink-0" />
        {{ $t('account.password_changed') }}
      </p>
    </div>
    <aside class="rounded-2xl border border-primary/15 bg-primary/5 p-5"><ShieldCheck class="h-7 w-7 text-primary" /><h2 class="mt-4 text-sm font-semibold">{{ $t('account.ui.password_safety') }}</h2><p class="mt-2 text-sm leading-relaxed text-muted">{{ $t('account.ui.password_tip') }}</p></aside>
  </div>
  </div>
</template>
