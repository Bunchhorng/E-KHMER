<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Check, Eye, EyeOff, LoaderCircle, Mail, UserPlus } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage, extractFieldErrors } from '@/api/errors'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()

const form = reactive({
  name: '',
  email: '',
  password: '',
  confirm: '',
})
const showPassword = ref(false)
const showConfirm = ref(false)
const error = ref('')
const errors = reactive({ name: '', email: '', password: '', confirm: '' })
const emailRejected = ref(false)

const passwordMeetsMin = computed(() => form.password.length >= 8)

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

async function submit(): Promise<void> {
  if (auth.loading) return

  error.value = ''
  errors.name = form.name.trim() ? '' : t('error.name_required')
  errors.email = /.+@.+\..+/.test(form.email.trim()) ? '' : t('error.email_invalid')
  errors.password = form.password.length >= 8 ? '' : t('error.password_short')
  errors.confirm = form.confirm && form.confirm === form.password ? '' : t('error.password_mismatch')

  if (errors.name || errors.email || errors.password || errors.confirm) return

  try {
    const user = await auth.register({
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password,
      password_confirmation: form.confirm,
    })
    const redirect =
      typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') && !route.query.redirect.startsWith('//')
        ? route.query.redirect
        : undefined
    router.push(redirect ?? (user?.role === 'super_admin' ? { name: 'admin-dashboard' } : { name: 'account-dashboard' }))
  } catch (e) {
    const fieldErrors = extractFieldErrors(e)

    errors.name = fieldErrors.name ?? ''
    errors.email = fieldErrors.email ?? ''
    errors.password = fieldErrors.password ?? ''
    errors.confirm = fieldErrors.password_confirmation ?? ''
    emailRejected.value = Boolean(fieldErrors.email)

    error.value =
      Object.keys(fieldErrors).length === 0 ? extractErrorMessage(e, t('error.registration_failed')) : ''
  }
}
</script>

<template>
  <div class="w-full">
    <div class="mb-6">
      <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $t('auth.create_account') }}</h1>
      <p class="mt-1.5 text-sm text-text-secondary">{{ $t('auth.register_subtitle') }}</p>
    </div>

    <form class="space-y-4" novalidate @submit.prevent="submit">
      <div>
        <label class="label" for="reg-name">{{ $t('auth.full_name') }}</label>
        <input
          id="reg-name"
          v-model="form.name"
          type="text"
          autocomplete="name"
          class="input min-h-11 rounded-none bg-canvas dark:bg-canvas"
          :class="{ 'input-error border-danger': Boolean(errors.name) }"
          :aria-invalid="Boolean(errors.name)"
          :aria-describedby="errors.name ? 'reg-name-error' : undefined"
        />
        <p v-if="errors.name" id="reg-name-error" role="alert" class="mt-1 text-xs font-medium text-danger">
          {{ errors.name }}
        </p>
      </div>

      <div>
        <label class="label" for="reg-email">{{ $t('auth.email') }}</label>
        <input
          id="reg-email"
          v-model="form.email"
          type="email"
          autocomplete="email"
          class="input min-h-11 rounded-none bg-canvas dark:bg-canvas"
          :class="{ 'input-error border-danger': Boolean(errors.email) }"
          placeholder="you@example.com"
          :aria-invalid="Boolean(errors.email)"
          :aria-describedby="errors.email ? 'reg-email-error' : undefined"
        />
        <p v-if="errors.email" id="reg-email-error" role="alert" class="mt-1 text-xs font-medium text-danger">
          {{ errors.email }}
          <RouterLink
            v-if="emailRejected"
            :to="{ path: '/auth/login', query: { email: form.email } }"
            class="ml-1 font-semibold underline"
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
            class="input min-h-11 rounded-none bg-canvas pr-12 dark:bg-canvas"
            :class="{ 'input-error border-danger': Boolean(errors.password) }"
            :placeholder="$t('auth.password_placeholder')"
            :aria-invalid="Boolean(errors.password)"
            aria-describedby="reg-password-hint reg-password-error"
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
          id="reg-password-hint"
          class="mt-1.5 flex items-center gap-1.5 text-xs"
          :class="passwordMeetsMin ? 'font-medium text-emerald-700 dark:text-success' : 'text-muted'"
        >
          <Check v-if="passwordMeetsMin" :size="13" aria-hidden="true" />
          <span>{{ passwordMeetsMin ? $t('auth.password_met') : $t('auth.password_requirement') }}</span>
        </p>
        <p v-if="errors.password" id="reg-password-error" role="alert" class="mt-1 text-xs font-medium text-danger">
          {{ errors.password }}
        </p>
      </div>

      <div>
        <label class="label" for="reg-confirm">{{ $t('auth.confirm_password') }}</label>
        <div class="relative">
          <input
            id="reg-confirm"
            v-model="form.confirm"
            :type="showConfirm ? 'text' : 'password'"
            autocomplete="new-password"
            class="input min-h-11 rounded-none bg-canvas pr-12 dark:bg-canvas"
            :class="{ 'input-error border-danger': Boolean(errors.confirm) }"
            :aria-invalid="Boolean(errors.confirm)"
            :aria-describedby="errors.confirm ? 'reg-confirm-error' : undefined"
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
        <p v-if="errors.confirm" id="reg-confirm-error" role="alert" class="mt-1 text-xs font-medium text-danger">
          {{ errors.confirm }}
        </p>
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
        <UserPlus v-else :size="16" aria-hidden="true" />
        {{ auth.loading ? $t('auth.creating_account') : $t('auth.create_account') }}
      </button>

      <div class="flex items-start gap-2 rounded-none border border-border-gray bg-surface-muted/50 px-3 py-2.5 text-xs leading-relaxed text-text-secondary">
        <Mail :size="14" class="mt-0.5 shrink-0 text-info" aria-hidden="true" />
        <span>{{ $t('verify.register_hint') }}</span>
      </div>
    </form>

    <p class="mt-6 border-t border-border-gray pt-5 text-center text-sm text-text-secondary">
      {{ $t('auth.have_account') }}
      <RouterLink
        :to="{ name: 'login', query: { redirect: route.query.redirect } }"
        class="ml-1 font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
      >
        {{ $t('auth.sign_in') }}
      </RouterLink>
    </p>
  </div>
</template>
