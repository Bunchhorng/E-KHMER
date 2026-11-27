<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { CheckCircle2, LoaderCircle, LogIn, Mail, Send } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const verified = computed(() => route.query.verified === '1' || Boolean(auth.user?.email_verified))
const needsVerification = computed(
  () => Boolean(auth.user) && route.query.verified !== '1' && !auth.user?.email_verified
)

const sending = ref(false)
const sent = ref(false)
const error = ref('')

async function resend(): Promise<void> {
  if (sending.value) return
  sending.value = true
  error.value = ''
  sent.value = false
  try {
    await auth.sendVerificationEmail()
    await auth.refreshUser()
    sent.value = true
  } catch {
    error.value = 'verify.resend_failed'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="w-full text-center">
    <div
      class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-none border"
      :class="
        verified
          ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-success/40 dark:bg-success/10 dark:text-success'
          : 'border-primary/30 bg-primary/10 text-primary'
      "
    >
      <CheckCircle2 v-if="verified" :size="28" aria-hidden="true" />
      <Mail v-else :size="28" aria-hidden="true" />
    </div>

    <template v-if="verified">
      <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $t('verify.title') }}</h1>
      <p class="mt-2 text-sm leading-relaxed text-text-secondary">{{ $t('verify.verified_message') }}</p>
      <RouterLink v-if="auth.isAuthenticated" to="/account" class="btn-primary mt-6 min-h-11 w-full rounded-none">
        {{ $t('verify.go_to_dashboard') }}
      </RouterLink>
      <RouterLink v-else to="/" class="btn-primary mt-6 min-h-11 w-full rounded-none">
        {{ $t('verify.continue_shopping') }}
      </RouterLink>
    </template>

    <template v-else-if="needsVerification">
      <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $t('verify.check_your_inbox') }}</h1>
      <p class="mt-2 text-sm leading-relaxed text-text-secondary">
        {{ $t('verify.pending_message', { email: auth.user?.email }) }}
      </p>

      <button
        type="button"
        class="btn-primary mt-6 min-h-11 w-full rounded-none"
        :disabled="sending"
        :aria-busy="sending"
        @click="resend"
      >
        <LoaderCircle v-if="sending" :size="16" class="animate-spin" aria-hidden="true" />
        <Send v-else :size="16" aria-hidden="true" />
        {{ sending ? $t('verify.sending') : $t('verify.resend_email') }}
      </button>

      <p
        v-if="sent"
        role="status"
        class="mt-4 rounded-none border border-emerald-300 bg-emerald-50 p-3 text-sm font-medium text-emerald-800 dark:border-success/40 dark:bg-success/10 dark:text-success"
      >
        {{ $t('verify.email_sent') }}
      </p>
      <p
        v-else-if="error"
        role="alert"
        class="mt-4 rounded-none border-l-2 border-danger bg-danger/10 p-3 text-sm font-medium text-danger"
      >
        {{ $t(error) }}
      </p>
    </template>

    <template v-else>
      <h1 class="text-2xl font-bold tracking-tight text-ink">{{ $t('verify.unauthenticated_title') }}</h1>
      <p class="mt-2 text-sm leading-relaxed text-text-secondary">{{ $t('verify.unauthenticated_message') }}</p>
      <RouterLink
        :to="{ name: 'login', query: { redirect: '/auth/verify-email' } }"
        class="btn-primary mt-6 min-h-11 w-full rounded-none"
      >
        <LogIn :size="16" aria-hidden="true" />
        {{ $t('auth.sign_in') }}
      </RouterLink>
    </template>
  </div>
</template>
