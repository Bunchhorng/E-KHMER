<script setup lang="ts">
import { CheckCircle2, Eye, EyeOff } from 'lucide-vue-next'
import { clamp } from '@/utils/format'
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

const strengthPercent = computed(() => clamp((form.password.length / 12) * 100, 0, 100))

const strengthBar = computed(() => {
  const length = form.password.length
  if (length === 0) return 'bg-gray-200'
  if (length < 5) return 'bg-red-500'
  if (length < 8) return 'bg-accent'
  return 'bg-success'
})

const strengthLabel = computed(() => {
  const length = form.password.length
  if (length === 0) return ''
  if (length < 5) return t('account.password_weak')
  if (length < 8) return t('account.password_fair')
  if (length < 12) return t('account.password_good')
  return t('account.password_strong')
})

async function submit() {
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
  <div>
    <div class="card space-y-6 p-6">
      <div>
        <h2 class="text-xl font-bold text-ink dark:text-ink">{{ $t('account.change_password') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ $t('account.change_password_subtitle') }}</p>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label" for="cp-current">{{ $t('account.current_password') }}</label>
          <input
            id="cp-current"
            v-model="form.current"
            type="password"
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
              class="input pr-10"
              :class="{ 'input-error': Boolean(errors.password) }"
            />
            <button
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
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
              class="input pr-10"
              :class="{ 'input-error': Boolean(errors.confirm) }"
            />
            <button
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
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

      <p v-if="success" class="flex items-start gap-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 p-3 text-sm font-medium text-emerald-700 dark:text-success">
        <CheckCircle2 class="h-4 w-4 shrink-0" />
        {{ $t('account.password_changed') }}
      </p>
    </div>
  </div>
</template>
