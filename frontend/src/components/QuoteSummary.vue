<script setup lang="ts">
import { useCartStore } from '@/stores/cart'
import { formatPrice } from '@/utils/format'

withDefaults(defineProps<{ estimated?: boolean }>(), { estimated: false })

const cart = useCartStore()
</script>

<template>
  <div class="space-y-2.5" data-quote-summary :aria-busy="cart.quoteLoading">
    <div v-if="cart.quoteLoading" role="status" class="flex items-center gap-2 py-2 text-sm text-muted">
      <span class="inline-block h-3.5 w-3.5 animate-spin border-2 border-primary border-t-transparent"></span>
      <span>{{ $t('cart.updating_totals') }}</span>
    </div>
    <div v-else-if="cart.quoteError" role="alert" class="border border-danger/40 bg-danger/10 p-3 text-sm text-danger">
      <p>{{ cart.quoteError }}</p>
      <button type="button" class="mt-2 font-semibold underline" @click="cart.refreshQuote()">{{ $t('actions.retry') }}</button>
    </div>
    <template v-else-if="cart.quote">
      <p v-if="cart.quoteNotice" role="status" class="border-l-2 border-amber-500 bg-amber-500/10 px-2.5 py-1.5 text-xs font-medium text-amber-800 dark:text-amber-300">{{ cart.quoteNotice }}</p>
      <div class="flex items-center justify-between gap-3 text-sm" data-amount="subtotal">
        <span class="text-text-secondary">{{ $t('checkout.subtotal') }}</span>
        <span class="font-medium text-ink">{{ formatPrice(cart.quote.subtotal, cart.quote.currency) }}</span>
      </div>
      <div v-if="cart.quote.discount_amount > 0" class="flex items-center justify-between gap-3 text-sm" data-amount="discount">
        <span class="text-text-secondary">{{ $t('checkout.discount') }}</span>
        <span class="font-medium text-danger">−{{ formatPrice(cart.quote.discount_amount, cart.quote.currency) }}</span>
      </div>
      <div class="flex items-center justify-between gap-3 text-sm" data-amount="tax">
        <span class="text-text-secondary">{{ $t('checkout.tax') }}</span>
        <span class="font-medium text-ink">{{ formatPrice(cart.quote.tax_amount, cart.quote.currency) }}</span>
      </div>
      <div class="flex items-center justify-between gap-3 text-sm" data-amount="shipping">
        <span class="text-text-secondary">{{ $t('checkout.shipping') }}</span>
        <span class="font-medium text-ink">{{ formatPrice(cart.quote.shipping_amount, cart.quote.currency) }}</span>
      </div>
      <div class="flex items-baseline justify-between gap-3 border-t border-border-gray pt-3" data-amount="total">
        <span class="text-sm font-bold text-ink">{{ estimated ? $t('cart.estimated_total') : $t('checkout.total') }}</span>
        <span class="text-xl font-extrabold tracking-tight text-ink">{{ formatPrice(cart.quote.total, cart.quote.currency) }}</span>
      </div>
      <p v-if="!cart.quote.shipping_complete && cart.availableItems.length" class="border-l-2 border-amber-500 bg-amber-500/10 px-2.5 py-1.5 text-xs font-medium text-amber-800 dark:text-amber-300">{{ $t('cart.delivery_unavailable') }}</p>
    </template>
  </div>
</template>
