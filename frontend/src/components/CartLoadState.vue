<script setup lang="ts">
import { AlertCircle, RefreshCw } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'

const cart = useCartStore()
</script>

<template>
  <div v-if="cart.loadError || cart.loading || !cart.loaded">
    <div
      v-if="cart.loadError"
      role="alert"
      class="rounded-none border border-danger/40 bg-danger/10 p-4 text-sm text-danger"
    >
      <div class="flex items-start gap-2.5">
        <AlertCircle :size="18" class="mt-0.5 shrink-0" aria-hidden="true" />
        <div class="min-w-0 flex-1">
          <p class="font-medium">{{ cart.loadError }}</p>
          <button
            type="button"
            class="btn-secondary btn-sm mt-3 min-h-10 rounded-none border-danger/40 bg-surface text-ink hover:bg-surface-hover sm:min-h-8"
            :disabled="cart.loading"
            @click="cart.fetch()"
          >
            <RefreshCw :size="14" :class="{ 'animate-spin': cart.loading }" aria-hidden="true" />
            {{ $t('actions.retry') }}
          </button>
        </div>
      </div>
    </div>
    <div v-else role="status" class="space-y-3 py-4" aria-live="polite">
      <p class="text-sm text-muted">{{ $t('cart.loading') }}</p>
      <div v-for="n in 2" :key="n" class="flex animate-pulse gap-3 border border-border-gray p-3">
        <div class="h-20 w-20 shrink-0 bg-surface-muted"></div>
        <div class="flex-1 space-y-2 py-1">
          <div class="h-4 w-3/4 bg-surface-muted"></div>
          <div class="h-3 w-1/3 bg-surface-muted"></div>
          <div class="mt-3 h-7 w-28 bg-surface-muted"></div>
        </div>
      </div>
    </div>
  </div>
</template>
