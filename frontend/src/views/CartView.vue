<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ShoppingBag, Lock, X } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
import CartLine from '@/components/CartLine.vue'
import CartLoadState from '@/components/CartLoadState.vue'
import EmptyState from '@/components/EmptyState.vue'
import { formatPrice } from '@/utils/format'

const router = useRouter()
const cartStore = useCartStore()

const couponInput = ref('')

const subtotal = computed(() => cartStore.subtotal)
const discount = computed(() => cartStore.discountAmount)

async function applyCoupon() {
  if (!couponInput.value.trim()) return
  const ok = await cartStore.applyCoupon(couponInput.value)
  if (ok) couponInput.value = ''
}

const itemGroups = computed(() => [
  { title: 'cart.available', items: cartStore.availableItems },
  { title: 'cart.needs_attention', items: cartStore.unavailableItems }
])
</script>

<template>
  <div class="py-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-2xl font-bold text-ink dark:text-ink sm:text-3xl">Shopping Cart</h1>
      <RouterLink to="/shop" class="text-sm font-medium text-primary hover:text-primary-dark">
        Continue shopping
      </RouterLink>
    </div>

    <CartLoadState class="mt-8" />
    <div v-if="cartStore.isEmpty" class="mt-8">
      <EmptyState
        title="Your cart is empty"
        description="Looks like you haven't added anything to your cart yet."
        ctaLabel="Start Shopping"
        @cta="router.push('/shop')"
      >
        <template #icon>
          <ShoppingBag class="h-10 w-10" />
        </template>
      </EmptyState>
    </div>

    <div v-else-if="cartStore.loaded && cartStore.items.length" class="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
      <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
          <p class="text-sm text-muted">{{ $t('cart.item_count', { count: cartStore.totalItemCount }) }}</p>
          <button type="button" class="text-sm text-red-500 underline" :disabled="cartStore.mutating" @click="cartStore.clear()">{{ $t('cart.clear') }}</button>
        </div>
        <template v-for="group in itemGroups" :key="group.title">
          <section v-if="group.items.length" class="card p-4 sm:p-5" :aria-label="$t(group.title)">
            <h2 class="mb-2 text-base font-semibold">{{ $t(group.title) }}</h2>
            <div class="divide-y divide-border-gray">
              <CartLine v-for="item in group.items" :key="item.id" :item="item" />
            </div>
          </section>
        </template>
      </div>

      <div class="card h-fit space-y-3 p-6 lg:sticky lg:top-24">
        <div class="flex justify-between text-sm">
          <span class="text-gray-600 dark:text-muted">{{ $t('checkout.subtotal') }}</span>
          <span class="font-medium text-ink dark:text-ink">{{ formatPrice(subtotal) }}</span>
        </div>
        <div v-if="discount > 0" class="flex justify-between text-sm">
          <span class="text-gray-600 dark:text-muted">{{ $t('checkout.discount') }}</span>
          <span class="font-medium text-red-500">−{{ formatPrice(discount) }}</span>
        </div>
        <div v-else class="flex justify-between text-sm">
          <span class="text-gray-600 dark:text-muted">{{ $t('checkout.discount') }}</span>
          <span class="text-gray-400 dark:text-gray-500"></span>
        </div>
        <div class="flex justify-between text-sm">
          <span class="text-gray-600 dark:text-muted">{{ $t('checkout.tax') }}</span>
          <span class="font-medium text-ink dark:text-ink">{{ formatPrice(cartStore.taxAmount) }}</span>
        </div>

        <div class="border-t border-border-gray !my-2 pt-3 dark:border-border-gray"></div>

        <div class="flex justify-between">
          <span class="font-semibold text-ink dark:text-ink">{{ $t('checkout.total') }}</span>
          <span class="text-xl font-bold text-ink dark:text-ink">{{ formatPrice(cartStore.totalAmount) }}</span>
        </div>

        <div class="space-y-2 pt-2">
          <div class="flex gap-2">
            <input
              v-model="couponInput"
              type="text"
              class="input"
              :placeholder="$t('checkout.coupon_code')"
              @keyup.enter="applyCoupon"
            />
            <button type="button" class="btn-primary btn-sm shrink-0" :disabled="cartStore.couponValidating" @click="applyCoupon">
              {{ $t('checkout.apply_coupon') }}
            </button>
          </div>
          <p v-if="cartStore.couponError" class="text-xs text-red-500">
            {{ cartStore.couponError }}
          </p>
          <p v-if="cartStore.couponSuccess" class="text-xs text-emerald-600">
            {{ cartStore.couponSuccess }}
          </p>
          <div
            v-if="cartStore.appliedCoupon"
            class="chip justify-between !bg-primary/10 !text-primary"
          >
            <span>{{ cartStore.appliedCoupon.code }}</span>
            <button
              type="button"
              class="ml-1 rounded-full p-0.5 hover:bg-primary/20"
              aria-label="Remove coupon"
              @click="cartStore.removeCoupon"
            >
              <X class="h-3 w-3" />
            </button>
          </div>
        </div>

        <RouterLink
          v-if="cartStore.canCheckout"
          to="/checkout"
          class="btn-primary btn-lg w-full"
        >
          Proceed to Checkout
        </RouterLink>
        <button v-else type="button" class="btn-primary btn-lg w-full" disabled>Proceed to Checkout</button>
        <p v-if="cartStore.unavailableItems.length" role="status" class="text-sm text-amber-800 dark:text-amber-300">{{ $t('cart.checkout_blocked', { count: cartStore.unavailableItems.length }) }}</p>

        <p class="flex items-center justify-center gap-1.5 pt-1 text-xs text-gray-500 dark:text-muted">
          <Lock class="h-3.5 w-3.5" />
          Secure checkout · SSL encrypted
        </p>
      </div>
    </div>
  </div>
</template>
