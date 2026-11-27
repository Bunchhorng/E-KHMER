<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { Banknote, Check, ChevronDown, QrCode, Send } from 'lucide-vue-next'
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
  <footer data-testid="storefront-footer" class="border-t border-border-gray bg-surface text-ink transition-colors duration-200">
    <div class="container-app py-7 sm:py-10 lg:py-12">
      <div class="grid gap-7 lg:grid-cols-[1.15fr_.8fr_.95fr_minmax(18rem,1.2fr)] lg:gap-x-12">
        <section class="max-w-sm">
          <RouterLink
            to="/"
            class="inline-flex min-h-11 items-center rounded-none text-2xl font-extrabold tracking-tight text-ink transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
          >
            E-<span class="text-primary">KHMER</span>
          </RouterLink>
          <p class="mt-3 text-sm leading-6 text-text-secondary">{{ $t('footer.tagline') }}</p>
        </section>

        <details class="footer-section border-b border-border-gray lg:border-0" :open="desktopFooter">
          <summary class="footer-summary">
            <span>{{ $t('footer.shop') }}</span>
            <ChevronDown aria-hidden="true" :size="18" class="footer-chevron text-info lg:hidden" />
          </summary>
          <ul class="footer-section-content pb-4 pt-1 text-sm lg:pb-0 lg:pt-3">
            <li v-for="link in shopLinks" :key="link.labelKey"><RouterLink :to="link.to" class="footer-link">{{ $t(link.labelKey) }}</RouterLink></li>
          </ul>
        </details>

        <details class="footer-section border-b border-border-gray lg:border-0" :open="desktopFooter">
          <summary class="footer-summary">
            <span>{{ $t('footer.customer_support') }}</span>
            <ChevronDown aria-hidden="true" :size="18" class="footer-chevron text-info lg:hidden" />
          </summary>
          <ul class="footer-section-content pb-4 pt-1 text-sm lg:pb-0 lg:pt-3">
            <li v-for="link in supportLinks" :key="link.labelKey"><RouterLink :to="link.to" class="footer-link">{{ $t(link.labelKey) }}</RouterLink></li>
          </ul>
        </details>

        <section class="min-w-0">
          <h2 class="footer-heading">{{ $t('footer.newsletter') }}</h2>
          <p class="mt-3 max-w-md text-sm leading-6 text-text-secondary">{{ $t('footer.newsletter_text') }}</p>
          <form class="mt-4 grid gap-2" novalidate @submit.prevent="subscribe">
            <input
              v-model="email"
              type="email"
              class="input min-h-11 w-full rounded-none bg-canvas text-ink placeholder:text-muted focus:bg-surface dark:bg-canvas dark:text-ink dark:placeholder:text-muted dark:focus:bg-surface"
              :class="emailError ? 'input-error border-danger focus:border-danger focus:ring-danger/25 dark:border-danger dark:focus:border-danger dark:focus:ring-danger/25' : 'border-border-strong focus:border-border-focus focus:ring-border-focus/25 dark:border-border-strong dark:focus:border-border-focus dark:focus:ring-border-focus/25'"
              :placeholder="$t('footer.email_placeholder')"
              :aria-label="$t('footer.email_placeholder')"
              :aria-invalid="Boolean(emailError)"
              :aria-describedby="emailError ? 'newsletter-email-error' : undefined"
              @input="emailError = ''"
            />
            <button
              type="submit"
              class="btn-primary min-h-11 w-full rounded-none px-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
              :disabled="subscribing"
            >
              <Send :size="16" aria-hidden="true" />
              {{ $t('footer.subscribe') }}
            </button>
          </form>
          <p v-if="emailError" id="newsletter-email-error" role="alert" class="mt-2 text-sm font-medium text-danger">{{ $t(emailError) }}</p>
          <label
            class="mt-3 flex min-h-11 cursor-pointer items-start gap-2.5 rounded-none px-1 py-1 text-xs leading-relaxed transition-colors"
            :class="consentError ? 'text-danger' : 'text-text-secondary hover:text-ink'"
          >
            <span class="relative mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center">
              <input
                v-model="consent"
                type="checkbox"
                class="peer h-4 w-4 cursor-pointer appearance-none rounded-none border bg-canvas transition-colors checked:border-primary checked:bg-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                :class="consentError ? 'border-danger' : 'border-border-strong'"
                :aria-invalid="consentError"
                @change="consentError = false"
              />
              <Check
                :size="12"
                :stroke-width="3"
                aria-hidden="true"
                class="pointer-events-none absolute text-white opacity-0 transition-opacity peer-checked:opacity-100"
              />
            </span>
            <span>{{ $t('footer.newsletter_consent') }}</span>
          </label>
          <p v-if="consentError" role="alert" class="mt-1 text-xs font-medium text-danger">{{ $t('footer.newsletter_consent') }}</p>
          <p v-if="subscriptionError" role="alert" class="mt-3 text-sm font-medium text-danger">{{ subscriptionError }}</p>
          <p v-if="subscribed" role="status" class="mt-3 rounded-none border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:border-success/40 dark:bg-success/10 dark:text-success">{{ $t('footer.newsletter_success') }}</p>
        </section>
      </div>

      <details class="footer-section mt-6 border-y border-border-gray lg:hidden">
        <summary class="footer-summary">
          <span>{{ $t('footer.policies') }}</span>
          <ChevronDown aria-hidden="true" :size="18" class="footer-chevron text-info" />
        </summary>
        <ul class="footer-section-content pb-4 pt-1 text-sm"><li v-for="link in policyLinks" :key="link.labelKey"><RouterLink :to="link.to" class="footer-link">{{ $t(link.labelKey) }}</RouterLink></li></ul>
      </details>

      <div class="mt-6 border-t border-border-gray pt-5 lg:mt-10">
        <div class="flex flex-col gap-4 text-xs sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
          <p class="text-muted">{{ $t('footer.copyright') }}</p>
          <nav class="flex flex-wrap gap-x-4 gap-y-2" :aria-label="$t('footer.policies')"><RouterLink v-for="link in policyLinks" :key="link.labelKey" :to="link.to" class="footer-legal-link">{{ $t(link.labelKey) }}</RouterLink></nav>
          <div class="flex flex-wrap gap-2" :aria-label="$t('footer.payment_methods')">
            <span class="payment-method"><QrCode :size="14" class="shrink-0 text-info" aria-hidden="true" /><span>Bakong / KHQR</span></span>
            <span class="payment-method"><Banknote :size="14" class="shrink-0 text-info" aria-hidden="true" /><span>{{ $t('footer.cash_on_delivery') }}</span></span>
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
  color: rgb(var(--color-ink));
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.footer-summary {
  cursor: pointer;
  list-style: none;
  border-radius: 0;
  transition: color 0.15s ease, background-color 0.15s ease;
}
.footer-summary:hover { color: rgb(var(--color-info)); }
.footer-summary:active { background-color: rgb(var(--color-surface-hover)); }
.footer-summary:focus-visible {
  outline: 2px solid rgb(var(--color-border-focus));
  outline-offset: 2px;
}
.footer-summary::-webkit-details-marker { display: none; }
.footer-section-content { display: grid; gap: 0.125rem; }
.footer-link,
.footer-legal-link {
  display: inline-flex;
  min-height: 2.5rem;
  align-items: center;
  border-radius: 0;
  color: rgb(var(--color-text-secondary));
  text-decoration: none;
  text-underline-offset: 0.25rem;
  transition: color 0.15s ease, background-color 0.15s ease;
}
.footer-link:hover,
.footer-legal-link:hover {
  color: rgb(var(--color-info));
  text-decoration: underline;
}
.footer-link:active,
.footer-legal-link:active { color: rgb(var(--color-ink)); }
.footer-link:focus-visible,
.footer-legal-link:focus-visible {
  color: rgb(var(--color-ink));
  outline: 2px solid rgb(var(--color-border-focus));
  outline-offset: 2px;
}
.payment-method {
  display: inline-flex;
  min-height: 2rem;
  align-items: center;
  gap: 0.375rem;
  border: 1px solid rgb(var(--color-border-strong));
  border-radius: 0;
  background: rgb(var(--color-surface-muted));
  padding: 0.25rem 0.625rem;
  color: rgb(var(--color-text-secondary));
  font-size: 0.6875rem;
  font-weight: 700;
  transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
}
@media (min-width: 1024px) {
  .footer-section > summary {
    cursor: default;
    pointer-events: none;
  }
  .footer-section > summary:hover { color: rgb(var(--color-ink)); }
  .footer-section > .footer-section-content { display: grid !important; }
  .footer-link { min-height: 2rem; }
}
.footer-section[open] .footer-chevron { transform: rotate(180deg); }
.footer-chevron { transition: transform 0.15s ease; }
@media (prefers-reduced-motion: reduce) {
  .footer-chevron,
  .footer-summary,
  .footer-link,
  .footer-legal-link,
  .payment-method { transition: none; }
}
</style>
