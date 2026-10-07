<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Eye, EyeOff, LoaderCircle, LogIn } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage } from '@/api/errors'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

// The register screen sends a rejected address here so the user does not retype it.
const form = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  remember: false
})
const showPassword = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  if (!form.email) {
    error.value = t('error.email_required')
    return
  }
  if (!form.password) {
    error.value = t('error.password_required')
    return
  }
  try {
    const user = await auth.login(form.email, form.password, form.remember)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : undefined
    if (redirect) router.push(redirect)
    else router.push(['admin', 'super_admin'].includes(user?.role ?? '') ? { name: 'admin-dashboard' } : { name: 'account-dashboard' })
  } catch (e) {
    error.value = extractErrorMessage(e, t('error.invalid_credentials'))
  }
}
</script>

<template>
  <div class="w-full max-w-md">
    <div class="card p-8">
      <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-ink dark:text-ink">{{ $t('auth.welcome_back') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ $t('auth.sign_in_subtitle') }}</p>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label" for="login-email">{{ $t('auth.email') }}</label>
          <input
            id="login-email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            class="input"
            placeholder="you@example.com"
          />
        </div>

        <div>
          <label class="label" for="login-password">{{ $t('auth.password') }}</label>
          <div class="relative">
            <input
              id="login-password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              class="input pr-10"
              placeholder="••••••••"
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
        </div>

        <div class="flex items-center justify-between text-sm">
          <label class="flex items-center gap-2 text-gray-600 dark:text-muted">
            <input v-model="form.remember" type="checkbox" class="accent-primary" />
            {{ $t('auth.remember_me') }}
          </label>
          <RouterLink to="/auth/forgot-password" class="font-medium text-primary hover:text-primary-dark">
            {{ $t('auth.forgot_password') }}
          </RouterLink>
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 dark:bg-red-900/30 p-3 text-sm text-red-600 dark:text-red-400">
          {{ error }}
        </p>

        <button type="submit" class="btn-primary flex w-full items-center justify-center gap-2" :disabled="auth.loading">
          <LoaderCircle v-if="auth.loading" class="h-4 w-4 animate-spin" />
          <LogIn v-else class="h-4 w-4" />
          {{ auth.loading ? $t('auth.signing_in') : $t('auth.sign_in') }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-gray-500 dark:text-muted">
        {{ $t('auth.no_account') }}
        <RouterLink to="/auth/register" class="font-medium text-primary hover:text-primary-dark">{{ $t('auth.create_one') }}</RouterLink>
      </p>
    </div>
  </div>
</template>
