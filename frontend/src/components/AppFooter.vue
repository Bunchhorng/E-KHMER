<script setup lang="ts">
import { ref } from 'vue'
import { Banknote, ChevronDown, QrCode, Send } from 'lucide-vue-next'
import apiClient from '@/api/client'
import { extractErrorMessage } from '@/api/errors'

const email = ref('')
const subscribed = ref(false)
const subscribing = ref(false)
const subscriptionError = ref('')
const emailError = ref('')
const consentError = ref(false)
const consent = ref(false)

const shopLinks = [
  { labelKey: 'footer.new_arrivals', to: '/shop' },
  { labelKey: 'footer.best_sellers', to: '/shop' },
  { labelKey: 'footer.sale', to: '/shop' },
  { labelKey: 'footer.help', to: '/support' },
  { labelKey: 'storefront.title', to: '/shops' }
]

const helpLinks = [
  { labelKey: 'footer.about_us', to: '/help/about' },
  { labelKey: 'footer.contact', to: '/support' },
  { labelKey: 'footer.faq', to: '/help/faq' },
  { labelKey: 'footer.shipping_returns', to: '/help/shipping' },
  { labelKey: 'footer.privacy_policy', to: '/help/privacy' },
  { labelKey: 'footer.terms', to: '/help/terms' }
]

async function subscribe(): Promise<void> {
  if (subscribing.value) return
  const address = email.value.trim()
  emailError.value = /^\S+@\S+\.\S+$/.test(address) ? '' : 'auth.email_invalid'
  consentError.value = !consent.value
  if (emailError.value || consentError.value) return
  subscribing.value = true; subscribed.value = false; subscriptionError.value = ''
  try { await apiClient.post('/newsletter', { email: address, consent: consent.value }); subscribed.value = true }
  catch (cause) { subscriptionError.value = extractErrorMessage(cause, 'Could not save your subscription.') }
  finally { subscribing.value = false }
}
</script>

<template>
  <footer class="border-t border-border-gray bg-surface pb-[calc(4.75rem+env(safe-area-inset-bottom))] dark:bg-ink lg:pb-0">
    <div class="container-app py-8 sm:py-10 lg:py-12">
      <div class="grid gap-7 sm:grid-cols-2 sm:gap-x-10 sm:gap-y-9 lg:grid-cols-[1.2fr_.8fr_.95fr_1.2fr] lg:gap-x-12">
        <div>
          <RouterLink to="/" class="text-2xl font-extrabold tracking-tight text-ink dark:text-white">
            E-<span class="text-primary">KHMER</span>
          </RouterLink>
          <p class="mt-3 max-w-xs text-sm leading-relaxed text-muted">
            {{ $t('footer.tagline') }}
          </p>
        </div>

        <details class="footer-section border-b border-border-gray pb-3 dark:border-white/10 sm:border-0 sm:pb-0">
          <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between text-sm font-semibold uppercase tracking-wide text-ink dark:text-white">
            {{ $t('footer.shop') }}
            <ChevronDown aria-hidden="true" :size="18" class="footer-chevron text-primary sm:hidden" />
          </summary>
          <ul class="footer-section-content mt-4 space-y-2.5 text-sm">
            <li v-for="link in shopLinks" :key="link.labelKey">
              <RouterLink :to="link.to" class="text-muted transition-colors hover:text-ink dark:hover:text-white">
                {{ $t(link.labelKey) }}
              </RouterLink>
            </li>
          </ul>
        </details>

        <details class="footer-section border-b border-border-gray pb-3 dark:border-white/10 sm:border-0 sm:pb-0">
          <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between text-sm font-semibold uppercase tracking-wide text-ink dark:text-white">
            {{ $t('footer.help') }}
            <ChevronDown aria-hidden="true" :size="18" class="footer-chevron text-primary sm:hidden" />
          </summary>
          <ul class="footer-section-content mt-4 space-y-2.5 text-sm">
            <li v-for="link in helpLinks" :key="link.labelKey">
              <RouterLink :to="link.to" class="text-muted transition-colors hover:text-ink dark:hover:text-white">{{ $t(link.labelKey) }}</RouterLink>
            </li>
          </ul>
        </details>

        <div>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-white">{{ $t('footer.newsletter') }}</h3>
          <p class="mt-3 text-sm leading-relaxed text-muted">
            {{ $t('footer.newsletter_text') }}
          </p>
          <form class="mt-4 flex flex-col gap-2 sm:flex-row" novalidate @submit.prevent="subscribe">
            <input
              v-model="email"
              type="email"
              class="input !border-border-gray !bg-canvas !text-ink placeholder:!text-muted dark:!border-white/10 dark:!bg-white/5 dark:!text-white dark:placeholder:!text-gray-500"
              :class="{ 'input-error': emailError }"
              :placeholder="$t('footer.email_placeholder')"
              :aria-label="$t('footer.email_placeholder')"
              :aria-invalid="Boolean(emailError)"
              :aria-describedby="emailError ? 'newsletter-email-error' : undefined"
              @input="emailError = ''"
            />
            <button type="submit" class="btn-primary min-h-11 w-full shrink-0 !px-4 sm:w-auto" :disabled="subscribing" :aria-label="$t('footer.subscribe')">
              <Send :size="15" />
              {{ $t('footer.subscribe') }}
            </button>
          </form>
          <p v-if="emailError" id="newsletter-email-error" role="alert" class="mt-2 text-sm text-danger">{{ $t(emailError) }}</p>
          <label class="mt-3 flex min-h-11 items-start gap-2 rounded-lg px-1 text-xs leading-relaxed text-muted" :class="consentError ? 'text-danger' : ''"><input v-model="consent" type="checkbox" class="mt-1 h-4 w-4 shrink-0 accent-primary" :aria-invalid="consentError" @change="consentError = false" />{{ $t('footer.newsletter_consent') }}</label>
          <p v-if="consentError" role="alert" class="mt-1 text-xs text-danger">{{ $t('footer.newsletter_consent') }}</p>
          <p v-if="subscriptionError" role="alert" class="mt-3 text-sm text-red-400">{{ subscriptionError }}</p>
          <p v-if="subscribed" class="mt-3 text-sm font-medium text-success">
            {{ $t('footer.newsletter_success') }}
          </p>
        </div>
      </div>

      <div class="mt-8 flex flex-col items-start gap-4 border-t border-border-gray pt-5 sm:mt-10 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
        <p class="text-xs text-muted">{{ $t('footer.copyright') }}</p>
        <div class="flex flex-wrap gap-2" aria-label="Supported payment methods">
          <span class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-border-gray bg-canvas px-2.5 py-1 text-[11px] font-semibold text-text-secondary dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            <QrCode :size="14" class="text-primary" aria-hidden="true" />
            Bakong / KHQR
          </span>
          <span class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-border-gray bg-canvas px-2.5 py-1 text-[11px] font-semibold text-text-secondary dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            <Banknote :size="14" class="text-primary" aria-hidden="true" />
            Cash on Delivery
          </span>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
@media (min-width: 640px) {
  .footer-section > summary { pointer-events: none; }
  .footer-section:not([open]) > .footer-section-content { display: block; }
}
.footer-section[open] .footer-chevron { transform: rotate(180deg); }
.footer-chevron { transition: transform 0.15s ease; }
</style>
