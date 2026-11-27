<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Check, Eye, EyeOff, LoaderCircle, LogIn } from 'lucide-vue-next'
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
  remember: false,
})
const showPassword = ref(false)
const error = ref('')
const fieldErrors = reactive({ email: '', password: '' })

watch(() => form.email, () => {
  fieldErrors.email = ''
  error.value = ''
})
watch(() => form.password, () => {
  fieldErrors.password = ''
  error.value = ''
})

async function submit(): Promise<void> {
  if (auth.loading) return
  error.value = ''
  fieldErrors.email = form.email.trim() ? '' : t('error.email_required')
  fieldErrors.password = form.password ? '' : t('error.password_required')
  if (fieldErrors.email || fieldErrors.password) return

  try {
    const user = await auth.login(form.email.trim(), form.password, form.remember)
    const redirect =
      typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') && !route.query.redirect.startsWith('//')
        ? route.query.redirect
        : undefined
    if (redirect) {
      router.push(redirect)
    } else {
      router.push(
        user?.role === 'super_admin'
          ? { name: 'admin-dashboard' }
          : auth.isSeller
            ? { name: 'seller-dashboard' }
            : { name: 'account-dashboard' }
      )
    }
  } catch (e) {
    error.value = extractErrorMessage(e, t('error.invalid_credentials'))
  }
}
</script>

<template>
  <div class="w-full">
    <div class="mb-6">
      <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $t('auth.welcome_back') }}</h1>
      <p class="mt-1.5 text-sm text-text-secondary">{{ $t('auth.sign_in_subtitle') }}</p>
    </div>

    <form class="space-y-4" novalidate @submit.prevent="submit">
      <div>
        <label class="label" for="login-email">{{ $t('auth.email') }}</label>
        <input
          id="login-email"
          v-model="form.email"
          type="email"
          autocomplete="email"
          class="input min-h-11 rounded-none bg-canvas dark:bg-canvas"
          :class="{ 'input-error border-danger': Boolean(fieldErrors.email) }"
          placeholder="you@example.com"
          :aria-invalid="Boolean(fieldErrors.email)"
          :aria-describedby="fieldErrors.email ? 'login-email-error' : undefined"
        />
        <p v-if="fieldErrors.email" id="login-email-error" role="alert" class="mt-1 text-xs font-medium text-danger">
          {{ fieldErrors.email }}
        </p>
      </div>

      <div>
        <label class="label" for="login-password">{{ $t('auth.password') }}</label>
        <div class="relative">
          <input
            id="login-password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            class="input min-h-11 rounded-none bg-canvas pr-12 dark:bg-canvas"
            :class="{ 'input-error border-danger': Boolean(fieldErrors.password) }"
            placeholder="••••••••"
            :aria-invalid="Boolean(fieldErrors.password)"
            :aria-describedby="fieldErrors.password ? 'login-password-error' : undefined"
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
        <p v-if="fieldErrors.password" id="login-password-error" role="alert" class="mt-1 text-xs font-medium text-danger">
          {{ fieldErrors.password }}
        </p>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2.5 text-text-secondary hover:text-ink">
          <span class="relative inline-flex h-4 w-4 shrink-0 items-center justify-center">
            <input
              v-model="form.remember"
              type="checkbox"
              class="peer h-4 w-4 cursor-pointer appearance-none rounded-none border border-border-strong bg-canvas transition-colors checked:border-primary checked:bg-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
            />
            <Check
              :size="12"
              :stroke-width="3"
              aria-hidden="true"
              class="pointer-events-none absolute text-white opacity-0 transition-opacity peer-checked:opacity-100"
            />
          </span>
          <span>{{ $t('auth.remember_me') }}</span>
        </label>

        <RouterLink
          to="/auth/forgot-password"
          class="inline-flex min-h-11 items-center text-sm font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
        >
          {{ $t('auth.forgot_password') }}
        </RouterLink>
      </div>

      <p
        v-if="error"
        role="alert"
        class="rounded-none border-l-2 border-danger bg-danger/10 px-3 py-2.5 text-sm font-medium text-danger"
      >
        {{ error }}
      </p>

      <button
        type="submit"
        class="btn-primary min-h-11 w-full rounded-none"
        :disabled="auth.loading"
        :aria-busy="auth.loading"
      >
        <LoaderCircle v-if="auth.loading" :size="16" class="animate-spin" aria-hidden="true" />
        <LogIn v-else :size="16" aria-hidden="true" />
        {{ auth.loading ? $t('auth.signing_in') : $t('auth.sign_in') }}
      </button>
    </form>

    <p class="mt-6 border-t border-border-gray pt-5 text-center text-sm text-text-secondary">
      {{ $t('auth.no_account') }}
      <RouterLink
        :to="{ name: 'register', query: { redirect: route.query.redirect } }"
        class="ml-1 font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
      >
        {{ $t('auth.create_one') }}
      </RouterLink>
    </p>
  </div>
</template>
