<script setup lang="ts">
import { ref } from 'vue'
import { Send } from 'lucide-vue-next'
import apiClient from '@/api/client'
import { extractErrorMessage } from '@/api/errors'

const email = ref('')
const subscribed = ref(false)
const subscribing = ref(false)
const subscriptionError = ref('')
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
  if (subscribing.value || !consent.value) return
  subscribing.value = true; subscribed.value = false; subscriptionError.value = ''
  try { await apiClient.post('/newsletter', { email: email.value.trim(), consent: consent.value }); subscribed.value = true }
  catch (cause) { subscriptionError.value = extractErrorMessage(cause, 'Could not save your subscription.') }
  finally { subscribing.value = false }
}
</script>

<template>
  <footer class="border-t border-border-gray bg-surface dark:bg-ink">
    <div class="container-app py-10 sm:py-14">
      <div class="grid gap-8 sm:grid-cols-2 sm:gap-x-10 sm:gap-y-12 lg:grid-cols-4">
        <div>
          <RouterLink to="/" class="text-2xl font-extrabold tracking-tight text-ink dark:text-white">
            E-<span class="text-primary">KHMER</span>
          </RouterLink>
          <p class="mt-4 max-w-xs text-sm text-muted">
            {{ $t('footer.tagline') }}
          </p>
        </div>

        <div>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-white">{{ $t('footer.shop') }}</h3>
          <ul class="mt-4 space-y-2.5 text-sm">
            <li v-for="link in shopLinks" :key="link.labelKey">
              <RouterLink :to="link.to" class="text-muted transition-colors hover:text-ink dark:hover:text-white">
                {{ $t(link.labelKey) }}
              </RouterLink>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-white">{{ $t('footer.help') }}</h3>
          <ul class="mt-4 space-y-2.5 text-sm">
            <li v-for="link in helpLinks" :key="link.labelKey">
              <RouterLink :to="link.to" class="text-muted transition-colors hover:text-ink dark:hover:text-white">{{ $t(link.labelKey) }}</RouterLink>
            </li>
          </ul>
        </div>

        <div>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-ink dark:text-white">{{ $t('footer.newsletter') }}</h3>
          <p class="mt-4 text-sm text-muted">
            {{ $t('footer.newsletter_text') }}
          </p>
          <form class="mt-4 flex gap-2 max-[479px]:flex-col" @submit.prevent="subscribe">
            <input
              v-model="email"
              type="email"
              required
              class="input !border-border-gray !bg-canvas !text-ink placeholder:!text-muted dark:!border-white/10 dark:!bg-white/5 dark:!text-white dark:placeholder:!text-gray-500"
              :placeholder="$t('footer.email_placeholder')"
              :aria-label="$t('footer.email_placeholder')"
            />
            <button type="submit" class="btn-primary shrink-0 !px-4 max-[479px]:w-full" :disabled="subscribing || !consent" :aria-label="$t('footer.subscribe')">
              <Send :size="15" />
            </button>
          </form>
          <label class="mt-3 flex items-start gap-2 text-xs text-muted"><input v-model="consent" type="checkbox" class="mt-0.5" />{{ $t('footer.newsletter_consent') }}</label>
          <p v-if="subscriptionError" role="alert" class="mt-3 text-sm text-red-400">{{ subscriptionError }}</p>
          <p v-if="subscribed" class="mt-3 text-sm font-medium text-success">
            {{ $t('footer.newsletter_success') }}
          </p>
        </div>
      </div>

      <div class="mt-10 flex flex-col items-start gap-4 border-t border-border-gray pt-6 sm:mt-12 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
        <p class="text-xs text-muted">{{ $t('footer.copyright') }}</p>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="pay in ['Bakong / KHQR', 'Cash on Delivery']"
            :key="pay"
            class="rounded border border-border-gray bg-canvas px-2 py-1 text-[11px] font-semibold text-text-secondary dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
          >
            {{ pay }}
          </span>
        </div>
      </div>
    </div>
  </footer>
</template>
