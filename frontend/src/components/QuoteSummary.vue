<script setup lang="ts">
import { useCartStore } from '@/stores/cart'
import { formatPrice } from '@/utils/format'
const cart = useCartStore()
</script>

<template>
  <div class="space-y-3" data-quote-summary :aria-busy="cart.quoteLoading">
    <p v-if="cart.quoteLoading" role="status" class="text-sm text-muted">{{ $t('cart.updating_totals') }}</p>
    <div v-else-if="cart.quoteError" role="alert" class="text-sm text-red-600 dark:text-red-300">
      <p>{{ cart.quoteError }}</p>
      <button type="button" class="mt-2 underline" @click="cart.refreshQuote()">{{ $t('actions.retry') }}</button>
    </div>
    <template v-else-if="cart.quote">
      <p v-if="cart.quoteNotice" role="status" class="text-sm text-amber-800 dark:text-amber-300">{{ cart.quoteNotice }}</p>
      <div class="flex justify-between gap-3 text-sm" data-amount="subtotal"><span class="text-muted">{{ $t('checkout.subtotal') }}</span><span>{{ formatPrice(cart.quote.subtotal, cart.quote.currency) }}</span></div>
      <div v-if="cart.quote.discount_amount > 0" class="flex justify-between gap-3 text-sm" data-amount="discount"><span class="text-muted">{{ $t('checkout.discount') }}</span><span class="text-red-500">−{{ formatPrice(cart.quote.discount_amount, cart.quote.currency) }}</span></div>
      <div class="flex justify-between gap-3 text-sm" data-amount="tax"><span class="text-muted">{{ $t('checkout.tax') }}</span><span>{{ formatPrice(cart.quote.tax_amount, cart.quote.currency) }}</span></div>
      <div class="flex justify-between gap-3 text-sm" data-amount="shipping"><span class="text-muted">{{ $t('checkout.shipping') }}</span><span>{{ formatPrice(cart.quote.shipping_amount, cart.quote.currency) }}</span></div>
      <div class="flex justify-between gap-3 border-t border-border-gray pt-3 font-semibold" data-amount="total"><span>{{ $t('checkout.total') }}</span><span class="text-xl">{{ formatPrice(cart.quote.total, cart.quote.currency) }}</span></div>
      <p v-if="!cart.quote.shipping_complete && cart.availableItems.length" class="text-xs text-amber-800 dark:text-amber-300">{{ $t('cart.delivery_unavailable') }}</p>
    </template>
  </div>
</template>
