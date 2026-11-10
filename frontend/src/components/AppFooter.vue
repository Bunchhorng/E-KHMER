<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { Banknote, ChevronDown, QrCode, Send } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/client'
import { extractErrorMessage } from '@/api/errors'

const email = ref('')
const { t } = useI18n()
const subscribed = ref(false)
const subscribing = ref(false)
const subscriptionError = ref('')
const emailError = ref('')
const consentError = ref(false)
const consent = ref(false)
const desktopFooter = ref(false)
let footerBreakpoint: MediaQueryList | undefined

function syncFooterBreakpoint(event?: MediaQueryListEvent): void {
  desktopFooter.value = event?.matches ?? footerBreakpoint?.matches ?? false
}

onMounted(() => {
  footerBreakpoint = window.matchMedia('(min-width: 1024px)')
  syncFooterBreakpoint()
  footerBreakpoint.addEventListener('change', syncFooterBreakpoint)
})

onBeforeUnmount(() => footerBreakpoint?.removeEventListener('change', syncFooterBreakpoint))

const shopLinks = [
  { labelKey: 'footer.all_products', to: '/shop' },
  { labelKey: 'footer.categories', to: '/#categories' },
  { labelKey: 'footer.explore_shops', to: '/shops' },
  { labelKey: 'footer.new_arrivals', to: '/shop?sort=newest' },
  { labelKey: 'footer.best_sellers', to: '/shop?sort=rating' }
]

const supportLinks = [
  { labelKey: 'footer.help_center', to: '/support' },
  { labelKey: 'footer.contact', to: '/support' },
  { labelKey: 'footer.faq', to: '/help/faq' },
  { labelKey: 'footer.shipping_returns', to: '/help/shipping' },
  // Order tracking requires an order number. Support is the only valid public
  // entry point when a customer does not have one in the current route.
  { labelKey: 'footer.order_tracking', to: '/support' }
]

const policyLinks = [
  { labelKey: 'footer.about_us', to: '/help/about' },
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
  catch (cause) { subscriptionError.value = extractErrorMessage(cause, t('footer.newsletter_error')) }
  finally { subscribing.value = false }
}
</script>

<template>
  <footer data-testid="storefront-footer" class="border-t border-border-gray bg-surface dark:bg-ink">
    <div class="container-app py-7 sm:py-10 lg:py-12">
      <div class="grid gap-7 lg:grid-cols-[1.15fr_.8fr_.95fr_minmax(18rem,1.2fr)] lg:gap-x-12">
        <section class="max-w-sm">
          <RouterLink to="/" class="inline-flex min-h-11 items-center text-2xl font-extrabold tracking-tight text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 dark:text-white dark:focus-visible:ring-offset-ink">
            E-<span class="text-primary">KHMER</span>
          </RouterLink>
          <p class="mt-3 text-sm leading-6 text-muted">{{ $t('footer.tagline') }}</p>
        </section>

        <details class="footer-section border-b border-border-gray dark:border-white/10 lg:border-0" :open="desktopFooter">
          <summary class="footer-summary text-ink dark:text-white">
            {{ $t('footer.shop') }}
            <ChevronDown aria-hidden="true" :size="18" class="footer-chevron text-primary lg:hidden" />
          </summary>
          <ul class="footer-section-content pb-4 pt-1 text-sm lg:pb-0 lg:pt-3">
            <li v-for="link in shopLinks" :key="link.labelKey"><RouterLink :to="link.to" class="footer-link">{{ $t(link.labelKey) }}</RouterLink></li>
          </ul>
        </details>

        <details class="footer-section border-b border-border-gray dark:border-white/10 lg:border-0" :open="desktopFooter">
          <summary class="footer-summary text-ink dark:text-white">
            {{ $t('footer.customer_support') }}
            <ChevronDown aria-hidden="true" :size="18" class="footer-chevron text-primary lg:hidden" />
          </summary>
          <ul class="footer-section-content pb-4 pt-1 text-sm lg:pb-0 lg:pt-3">
            <li v-for="link in supportLinks" :key="link.labelKey"><RouterLink :to="link.to" class="footer-link">{{ $t(link.labelKey) }}</RouterLink></li>
          </ul>
        </details>

        <section class="min-w-0">
          <h2 class="footer-heading">{{ $t('footer.newsletter') }}</h2>
          <p class="mt-3 max-w-md text-sm leading-6 text-muted">{{ $t('footer.newsletter_text') }}</p>
          <form class="mt-4 grid gap-2" novalidate @submit.prevent="subscribe">
            <input
              v-model="email"
              type="email"
              class="input min-h-11 w-full !border-border-gray !bg-canvas !text-ink placeholder:!text-muted dark:!border-white/10 dark:!bg-white/5 dark:!text-white dark:placeholder:!text-gray-500"
              :class="{ 'input-error': emailError }"
              :placeholder="$t('footer.email_placeholder')"
              :aria-label="$t('footer.email_placeholder')"
              :aria-invalid="Boolean(emailError)"
              :aria-describedby="emailError ? 'newsletter-email-error' : undefined"
              @input="emailError = ''"
            />
            <button type="submit" class="btn-primary min-h-11 w-full !px-4" :disabled="subscribing">
              <Send :size="16" aria-hidden="true" />
              {{ $t('footer.subscribe') }}
            </button>
          </form>
          <p v-if="emailError" id="newsletter-email-error" role="alert" class="mt-2 text-sm text-danger">{{ $t(emailError) }}</p>
          <label class="mt-3 flex min-h-11 items-start gap-2 rounded-lg px-1 text-xs leading-relaxed text-muted" :class="consentError ? 'text-danger' : ''"><input v-model="consent" type="checkbox" class="mt-1 h-4 w-4 shrink-0 accent-primary" :aria-invalid="consentError" @change="consentError = false" />{{ $t('footer.newsletter_consent') }}</label>
          <p v-if="consentError" role="alert" class="mt-1 text-xs text-danger">{{ $t('footer.newsletter_consent') }}</p>
          <p v-if="subscriptionError" role="alert" class="mt-3 text-sm text-danger">{{ subscriptionError }}</p>
          <p v-if="subscribed" role="status" class="mt-3 text-sm font-medium text-success">{{ $t('footer.newsletter_success') }}</p>
        </section>
      </div>

      <details class="footer-section mt-6 border-y border-border-gray dark:border-white/10 lg:hidden">
        <summary class="footer-summary text-ink dark:text-white">
          {{ $t('footer.policies') }}
          <ChevronDown aria-hidden="true" :size="18" class="footer-chevron text-primary" />
        </summary>
        <ul class="footer-section-content pb-4 pt-1 text-sm"><li v-for="link in policyLinks" :key="link.labelKey"><RouterLink :to="link.to" class="footer-link">{{ $t(link.labelKey) }}</RouterLink></li></ul>
      </details>

      <div class="mt-6 border-t border-border-gray pt-5 dark:border-white/10 lg:mt-10">
        <div class="flex flex-col gap-4 text-xs sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
          <p class="text-muted">{{ $t('footer.copyright') }}</p>
          <nav class="flex flex-wrap gap-x-4 gap-y-2" :aria-label="$t('footer.policies')"><RouterLink v-for="link in policyLinks" :key="link.labelKey" :to="link.to" class="footer-legal-link">{{ $t(link.labelKey) }}</RouterLink></nav>
          <div class="flex flex-wrap gap-2" :aria-label="$t('footer.payment_methods')">
            <span class="payment-method"><QrCode :size="14" class="text-primary" aria-hidden="true" />Bakong / KHQR</span>
            <span class="payment-method"><Banknote :size="14" class="text-primary" aria-hidden="true" />{{ $t('footer.cash_on_delivery') }}</span>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.footer-heading,
.footer-summary {
  display: flex;
  min-height: 2.75rem;
  align-items: center;
  justify-content: space-between;
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.footer-summary { cursor: pointer; list-style: none; }
.footer-summary::-webkit-details-marker { display: none; }
.footer-section-content { display: grid; gap: 0.125rem; }
.footer-link,
.footer-legal-link {
  display: inline-flex;
  min-height: 2.5rem;
  align-items: center;
  color: rgb(var(--color-text-muted));
  transition: color 0.15s ease;
}
.footer-link:hover,
.footer-legal-link:hover { color: rgb(var(--color-ink)); }
.payment-method {
  display: inline-flex;
  min-height: 2rem;
  align-items: center;
  gap: 0.375rem;
  border: 1px solid rgb(var(--color-border));
  border-radius: 0.5rem;
  background: rgb(var(--color-canvas));
  padding: 0.25rem 0.625rem;
  color: rgb(var(--color-text-secondary));
  font-size: 0.6875rem;
  font-weight: 700;
}
@media (min-width: 1024px) {
  .footer-section > summary { pointer-events: none; }
  .footer-section > .footer-section-content { display: grid !important; }
  .footer-link { min-height: 2rem; }
}
.footer-section[open] .footer-chevron { transform: rotate(180deg); }
.footer-chevron { transition: transform 0.15s ease; }
@media (prefers-reduced-motion: reduce) {
  .footer-chevron,
  .footer-link,
  .footer-legal-link { transition: none; }
}
</style>
