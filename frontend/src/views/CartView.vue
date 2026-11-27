<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft, Loader2, Lock, RotateCcw, ShoppingBag, Store, Tag, X } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
import type { CartItem } from '@/types'
import CartLine from '@/components/CartLine.vue'
import CartLoadState from '@/components/CartLoadState.vue'
import QuoteSummary from '@/components/QuoteSummary.vue'
import EmptyState from '@/components/EmptyState.vue'
import { formatPrice } from '@/utils/format'

interface RemovedSnapshot {
  title: string
  variantId: string
  quantity: number
}

const router = useRouter()
const cartStore = useCartStore()

const couponInput = ref('')
const lastRemoved = ref<RemovedSnapshot | null>(null)
const statusNotice = ref('')
let statusTimer: number | undefined

function setTransientStatus(message: string): void {
  statusNotice.value = message
  window.clearTimeout(statusTimer)
  statusTimer = window.setTimeout(() => {
    statusNotice.value = ''
  }, 3000)
}

onBeforeUnmount(() => window.clearTimeout(statusTimer))

const sellerGroups = computed(() => {
  const map = new Map<number, { shopId: number; shopName: string; items: CartItem[] }>()
  for (const item of cartStore.availableItems) {
    const shopId = item.shopId ?? 0
    const shopName = item.shopName || 'E-KHMER'
    const existing = map.get(shopId)
    if (existing) existing.items.push(item)
    else map.set(shopId, { shopId, shopName, items: [item] })
  }
  return [...map.values()]
})

async function clearCart(): Promise<void> {
  lastRemoved.value = null
  statusNotice.value = ''
  await cartStore.clear()
}

async function applyCoupon(): Promise<void> {
  if (!couponInput.value.trim()) return
  const ok = await cartStore.applyCoupon(couponInput.value)
  if (ok) couponInput.value = ''
}

function onItemRemoved(item: CartItem): void {
  statusNotice.value = ''
  lastRemoved.value = item.variant?.variantId
    ? { title: item.title, variantId: item.variant.variantId, quantity: item.quantity }
    : null
}

function onItemUpdated(): void {
  setTransientStatus('cart.updated_notice')
}

async function undoRemove(): Promise<void> {
  const snapshot = lastRemoved.value
  if (!snapshot) return
  lastRemoved.value = null
  await cartStore.addItem({ variantId: snapshot.variantId, quantity: snapshot.quantity })
}
</script>

<template>
  <div class="py-5 pb-28 sm:py-6 lg:pb-8">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border-gray pb-4">
      <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ $t('cart.shopping_cart') }}</h1>
        <p v-if="cartStore.loaded && cartStore.items.length" class="mt-1 text-sm text-muted">
          {{ $t('cart.item_count', { count: cartStore.totalItemCount }) }}
        </p>
      </div>
      <RouterLink
        to="/shop"
        class="inline-flex min-h-10 items-center gap-1.5 rounded-none border border-border-gray bg-surface px-3.5 py-2 text-sm font-semibold text-ink transition-colors hover:border-border-strong hover:bg-surface-hover hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
      >
        <ArrowLeft :size="15" aria-hidden="true" />
        {{ $t('actions.continue_shopping') }}
      </RouterLink>
    </div>

    <CartLoadState class="mt-5" />

    <div v-if="cartStore.isEmpty" class="mt-6">
      <EmptyState
        :title="$t('cart.empty_title')"
        :description="$t('cart.empty_description')"
        :cta-label="$t('actions.continue_shopping')"
        @cta="router.push('/shop')"
      >
        <template #icon>
          <ShoppingBag :size="28" aria-hidden="true" />
        </template>
      </EmptyState>
    </div>

    <div v-else-if="cartStore.loaded && cartStore.items.length" class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,7fr)_minmax(20rem,3fr)] lg:items-start">
      <div class="min-w-0 space-y-4">
        <div
          v-if="lastRemoved"
          role="status"
          class="flex flex-wrap items-center justify-between gap-3 rounded-none border border-border-strong bg-surface px-4 py-2.5 text-sm text-ink"
        >
          <p class="min-w-0 flex-1 truncate">{{ $t('cart.removed_notice', { name: lastRemoved.title }) }}</p>
          <div class="flex shrink-0 items-center gap-2">
            <button
              type="button"
              class="btn-secondary btn-sm min-h-9 rounded-none"
              :disabled="cartStore.mutating"
              @click="undoRemove"
            >
              <RotateCcw :size="13" aria-hidden="true" />
              {{ $t('cart.undo') }}
            </button>
            <button
              type="button"
              class="btn-icon h-8 w-8 rounded-none text-muted hover:text-ink"
              :aria-label="$t('cart.dismiss')"
              @click="lastRemoved = null"
            >
              <X :size="15" aria-hidden="true" />
            </button>
          </div>
        </div>

        <p
          v-else-if="statusNotice"
          role="status"
          class="rounded-none border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800 dark:border-success/40 dark:bg-success/10 dark:text-success"
        >
          {{ $t(statusNotice) }}
        </p>

        <div class="flex flex-wrap items-center justify-between gap-3">
          <span v-if="cartStore.mutating" role="status" class="inline-flex items-center gap-2 text-xs font-medium text-muted">
            <Loader2 :size="14" class="animate-spin text-primary" aria-hidden="true" />
            {{ $t('cart.updating_totals') }}
          </span>
          <span v-else class="text-xs text-muted"></span>
          <button
            type="button"
            class="min-h-9 rounded-none px-2 text-xs font-semibold text-danger underline underline-offset-4 transition-colors hover:bg-danger/10 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="cartStore.mutating"
            :aria-busy="cartStore.isPending('clear')"
            @click="clearCart"
          >
            {{ $t('cart.clear') }}
          </button>
        </div>

        <section
          v-if="cartStore.availableItems.length"
          class="card overflow-hidden rounded-none"
          :aria-label="$t('cart.available')"
        >
          <header class="flex items-center justify-between border-b border-border-gray bg-surface px-4 py-3 sm:px-5">
            <h2 class="text-sm font-bold uppercase tracking-wider text-ink">{{ $t('cart.available') }}</h2>
            <span class="text-xs font-medium text-muted">{{ cartStore.availableItems.length }}</span>
          </header>

          <div v-for="group in sellerGroups" :key="group.shopId" class="border-b border-border-gray last:border-b-0">
            <div class="flex items-center gap-2 border-b border-border-gray bg-surface-muted/70 px-4 py-2 text-xs font-semibold text-ink sm:px-5">
              <Store :size="14" class="shrink-0 text-info" aria-hidden="true" />
              <span class="truncate">{{ group.shopName }}</span>
            </div>
            <div class="divide-y divide-border-gray px-4 sm:px-5">
              <CartLine
                v-for="item in group.items"
                :key="item.id"
                :item="item"
                @removed="onItemRemoved"
                @updated="onItemUpdated"
              />
            </div>
          </div>
        </section>

        <section
          v-if="cartStore.unavailableItems.length"
          class="card overflow-hidden rounded-none border-amber-400/60 dark:border-amber-500/40"
          :aria-label="$t('cart.needs_attention')"
        >
          <header class="flex items-center justify-between border-b border-border-gray bg-amber-500/10 px-4 py-3 sm:px-5">
            <h2 class="text-sm font-bold uppercase tracking-wider text-amber-900 dark:text-amber-300">{{ $t('cart.needs_attention') }}</h2>
            <span class="text-xs font-semibold text-amber-900 dark:text-amber-300">{{ cartStore.unavailableItems.length }}</span>
          </header>
          <div class="divide-y divide-border-gray px-4 sm:px-5">
            <CartLine
              v-for="item in cartStore.unavailableItems"
              :key="item.id"
              :item="item"
              show-seller
              @removed="onItemRemoved"
              @updated="onItemUpdated"
            />
          </div>
        </section>
      </div>

      <section class="card h-fit space-y-4 rounded-none p-5 sm:p-6 lg:sticky lg:top-36" :aria-label="$t('cart.order_summary')">
        <h2 class="border-b border-border-gray pb-3 text-base font-bold tracking-tight text-ink">
          {{ $t('cart.order_summary') }}
        </h2>

        <QuoteSummary estimated />

        <p class="text-xs leading-relaxed text-muted">{{ $t('cart.shipping_note') }}</p>

        <div class="space-y-2 border-t border-border-gray pt-4">
          <label for="cart-coupon-input" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-text-secondary">
            <Tag :size="13" class="text-info" aria-hidden="true" />
            <span>{{ $t('checkout.coupon_code') }}</span>
          </label>
          <div class="flex items-stretch gap-2">
            <input
              id="cart-coupon-input"
              v-model="couponInput"
              type="text"
              class="input min-h-11 min-w-0 flex-1 rounded-none bg-canvas dark:bg-canvas"
              :placeholder="$t('checkout.coupon_code')"
              @keyup.enter="applyCoupon"
            />
            <button
              type="button"
              class="btn-primary min-h-11 shrink-0 rounded-none px-4 text-xs sm:text-sm"
              :disabled="cartStore.quoteLoading || cartStore.mutating || !couponInput.trim()"
              @click="applyCoupon"
            >
              {{ $t('checkout.apply_coupon') }}
            </button>
          </div>
          <p v-if="cartStore.couponError" role="alert" class="border-l-2 border-danger bg-danger/10 px-2.5 py-1.5 text-xs font-medium text-danger">
            {{ cartStore.couponError }}
          </p>
          <p v-if="cartStore.couponSuccess" role="status" class="border-l-2 border-emerald-600 bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-800 dark:border-success dark:bg-success/10 dark:text-success">
            {{ cartStore.couponSuccess }}
          </p>
          <div
            v-if="cartStore.appliedCoupon"
            class="flex items-center justify-between gap-2 rounded-none border border-primary/40 bg-primary/10 px-3 py-2 text-xs font-bold text-primary dark:text-info"
          >
            <span class="truncate">{{ cartStore.appliedCoupon.code }}</span>
            <button
              type="button"
              class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-none text-primary transition-colors hover:bg-primary/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus dark:text-info"
              :aria-label="$t('cart.remove_coupon')"
              @click="cartStore.removeCoupon"
            >
              <X :size="14" aria-hidden="true" />
            </button>
          </div>
        </div>

        <p
          v-if="cartStore.unavailableItems.length"
          role="status"
          class="border-l-2 border-amber-500 bg-amber-500/10 px-3 py-2 text-xs font-medium text-amber-800 dark:text-amber-300"
        >
          {{ $t('cart.checkout_blocked', { count: cartStore.unavailableItems.length }) }}
        </p>

        <div class="space-y-2 pt-1">
          <RouterLink
            v-if="cartStore.canCheckout"
            to="/checkout"
            class="btn-primary min-h-12 w-full rounded-none text-sm"
          >
            {{ $t('actions.checkout') }}
          </RouterLink>
          <button
            v-else
            type="button"
            class="btn-primary min-h-12 w-full rounded-none text-sm"
            disabled
            :aria-busy="cartStore.quoteLoading || cartStore.mutating"
          >
            {{ $t('actions.checkout') }}
          </button>

          <p class="flex items-center justify-center gap-1.5 pt-1 text-xs text-muted">
            <Lock :size="13" aria-hidden="true" />
            <span>{{ $t('cart.secure_checkout') }}</span>
          </p>
        </div>
      </section>
    </div>

    <div
      v-if="cartStore.loaded && cartStore.items.length"
      data-testid="mobile-cart-sticky-bar"
      class="fixed inset-x-0 bottom-[calc(4.0625rem+env(safe-area-inset-bottom))] z-30 border-t border-border-gray bg-surface/95 px-4 py-2.5 shadow-[0_-8px_24px_rgb(15_23_42_/_0.10)] backdrop-blur lg:hidden"
    >
      <div class="mx-auto flex max-w-lg items-center gap-3">
        <div class="min-w-0 flex-1">
          <p class="text-[11px] font-medium text-muted">{{ $t('cart.estimated_total') }}</p>
          <p class="truncate text-lg font-extrabold text-ink">
            {{ cartStore.quoteLoading || !cartStore.quote ? '—' : formatPrice(cartStore.totalAmount, cartStore.quote.currency) }}
          </p>
        </div>
        <RouterLink
          v-if="cartStore.canCheckout"
          to="/checkout"
          class="btn-primary min-h-11 shrink-0 rounded-none px-5"
        >
          {{ $t('actions.checkout') }}
        </RouterLink>
        <button
          v-else
          type="button"
          class="btn-primary min-h-11 shrink-0 rounded-none px-5"
          disabled
        >
          {{ $t('actions.checkout') }}
        </button>
      </div>
    </div>
  </div>
</template>
