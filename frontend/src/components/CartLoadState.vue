<script setup lang="ts">
import { useCartStore } from '@/stores/cart'
const cart = useCartStore()
</script>

<template>
  <div v-if="cart.loadError || cart.loading || !cart.loaded">
    <div v-if="cart.loadError" role="alert" class="rounded-xl border border-red-200 p-4 text-sm text-red-600 dark:text-red-300">
      <p>{{ cart.loadError }}</p>
      <button type="button" class="mt-2 underline" :disabled="cart.loading" @click="cart.fetch()">{{ $t('actions.retry') }}</button>
    </div>
    <p v-else role="status" class="p-5 text-sm text-muted" aria-live="polite">{{ $t('cart.loading') }}</p>
  </div>
</template>
