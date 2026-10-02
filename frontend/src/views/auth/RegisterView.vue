<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Eye, EyeOff, LoaderCircle, UserPlus } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage, extractFieldErrors } from '@/api/errors'

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()

const form = reactive({
  name: '',
  email: '',
  password: '',
  confirm: ''
})
const showPassword = ref(false)
const showConfirm = ref(false)
const error = ref('')
const errors = reactive({ name: '', email: '', password: '', confirm: '' })
const emailRejected = ref(false)

// A server rejection belongs to the value that was rejected. Keeping it pinned to
// the input made a corrected address still read "email already taken" until the
// next submit.
watch(() => form.name, () => { errors.name = '' })
watch(() => form.email, () => {
  errors.email = ''
  emailRejected.value = false
})
watch(() => form.password, () => {
  errors.password = ''
  errors.confirm = ''
})
watch(() => form.confirm, () => { errors.confirm = '' })

async function submit() {
  if (auth.loading) return

  error.value = ''
  errors.name = form.name.trim() ? '' : t('error.name_required')
  errors.email = /.+@.+\..+/.test(form.email) ? '' : t('error.email_invalid')
  errors.password = form.password.length >= 8 ? '' : t('error.password_short')
  errors.confirm = form.confirm && form.confirm === form.password ? '' : t('error.password_mismatch')

  if (errors.name || errors.email || errors.password || errors.confirm) return

  try {
    const user = await auth.register({
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.confirm
    })
    router.push(user?.role === 'admin' ? { name: 'admin-dashboard' } : { name: 'account-dashboard' })
  } catch (e) {
    // Surface per-field rejections (duplicate email, weak password) on the input
    // itself and keep a summary message for everything else.
    const fieldErrors = extractFieldErrors(e)

    errors.name = fieldErrors.name ?? ''
    errors.email = fieldErrors.email ?? ''
    errors.password = fieldErrors.password ?? ''
    errors.confirm = fieldErrors.password_confirmation ?? ''
    emailRejected.value = Boolean(fieldErrors.email)

    error.value = Object.keys(fieldErrors).length === 0
      ? extractErrorMessage(e, t('error.registration_failed'))
      : ''
  }
}
</script>

<template>
  <div class="w-full max-w-md">
    <div class="card p-8">
      <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-ink dark:text-ink">{{ $t('auth.create_account') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ $t('auth.register_subtitle') }}</p>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label" for="reg-name">{{ $t('auth.full_name') }}</label>
          <input
            id="reg-name"
            v-model="form.name"
            type="text"
            autocomplete="name"
            class="input"
            :class="{ 'input-error': Boolean(errors.name) }"
          />
          <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
        </div>

        <div>
          <label class="label" for="reg-email">{{ $t('auth.email') }}</label>
          <input
            id="reg-email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            class="input"
            :class="{ 'input-error': Boolean(errors.email) }"
          />
          <p v-if="errors.email" class="mt-1 text-xs text-red-500">
            {{ errors.email }}
            <RouterLink
              v-if="emailRejected"
              :to="{ path: '/auth/login', query: { email: form.email } }"
              class="ml-1 font-medium underline"
            >
              {{ $t('error.sign_in_instead') }}
            </RouterLink>
          </p>
        </div>

        <div>
          <label class="label" for="reg-password">{{ $t('auth.password') }}</label>
          <div class="relative">
            <input
              id="reg-password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password"
              class="input pr-10"
              :class="{ 'input-error': Boolean(errors.password) }"
              :placeholder="$t('auth.password_placeholder')"
            />
            <button
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
              @click="showPassword = !showPassword"
            >
              <Eye v-if="!showPassword" class="h-4 w-4" />
              <EyeOff v-else class="h-4 w-4" />
            </button>
          </div>
          <p v-if="errors.password" class="mt-1 text-xs text-red-500">{{ errors.password }}</p>
        </div>

        <div>
          <label class="label" for="reg-confirm">{{ $t('auth.confirm_password') }}</label>
          <div class="relative">
            <input
              id="reg-confirm"
              v-model="form.confirm"
              :type="showConfirm ? 'text' : 'password'"
              autocomplete="new-password"
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

        <p v-if="error" class="rounded-lg bg-red-50 dark:bg-red-900/30 p-3 text-sm text-red-600 dark:text-red-400">
          {{ error }}
        </p>

        <button type="submit" class="btn-primary flex w-full items-center justify-center gap-2" :disabled="auth.loading">
          <LoaderCircle v-if="auth.loading" class="h-4 w-4 animate-spin" />
          <UserPlus v-else class="h-4 w-4" />
          {{ auth.loading ? $t('auth.creating_account') : $t('auth.create_account') }}
        </button>
        <p class="text-center text-xs text-gray-500 dark:text-muted">{{ $t('verify.register_hint') }}</p>
      </form>

      <p class="mt-6 text-center text-sm text-gray-500 dark:text-muted">
        {{ $t('auth.have_account') }}
        <RouterLink to="/auth/login" class="font-medium text-primary hover:text-primary-dark">{{ $t('auth.sign_in') }}</RouterLink>
      </p>
    </div>
  </div>
</template>
