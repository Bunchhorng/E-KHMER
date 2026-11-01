<script setup lang="ts">
import { useRouter } from 'vue-router'
import { ShoppingBag, X } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'
import EmptyState from './EmptyState.vue'
import CartLine from './CartLine.vue'
import CartLoadState from './CartLoadState.vue'
import QuoteSummary from './QuoteSummary.vue'

const router = useRouter()
const cartStore = useCartStore()
const uiStore = useUiStore()

function closeCartDrawer(): void {
  uiStore.closeCartDrawer()
}

function startShopping(): void {
  closeCartDrawer()
  router.push('/shop')
}

</script>

<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="uiStore.cartDrawerOpen" class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-black/40" @click="closeCartDrawer"></div>
        <aside class="panel fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col bg-white shadow-xl dark:bg-surface">
          <header class="flex shrink-0 items-center justify-between border-b border-border-gray px-5 py-4 dark:border-border-gray">
            <h2 class="flex items-center gap-2 text-lg font-semibold text-ink dark:text-ink">
              {{ $t('cart.your_cart') }}
              <span
                v-if="cartStore.totalItemCount > 0"
                class="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-white dark:bg-blue-500"
              >
                {{ cartStore.totalItemCount }}
              </span>
            </h2>
            <button type="button" class="btn-icon" :aria-label="$t('cart.close_cart')" @click="closeCartDrawer">
              <X :size="20" />
            </button>
          </header>

          <div class="flex-1 overflow-y-auto p-4">
            <CartLoadState />
            <EmptyState
              v-if="cartStore.isEmpty"
              :title="$t('cart.empty_title')"
              :description="$t('cart.empty_description')"
              :cta-label="$t('actions.start_shopping')"
              @cta="startShopping"
            >
              <template #icon>
                <ShoppingBag :size="28" />
              </template>
            </EmptyState>

            <ul v-else-if="cartStore.loaded" class="divide-y divide-border-gray dark:divide-border-gray">
              <li v-for="item in cartStore.items" :key="item.id">
                <CartLine :item="item" compact />
              </li>
            </ul>
          </div>

          <footer v-if="cartStore.items.length > 0" class="shrink-0 space-y-3 border-t border-border-gray p-5 dark:border-border-gray">
            <QuoteSummary />
            <p class="text-xs text-muted">{{ $t('cart.delivery_note') }}</p>
            <RouterLink v-if="cartStore.canCheckout" to="/checkout" class="btn-primary w-full dark:bg-blue-500 dark:hover:bg-blue-600" @click="closeCartDrawer">
              {{ $t('actions.checkout') }}
            </RouterLink>
            <button v-else type="button" class="btn-primary w-full" disabled>{{ $t('actions.checkout') }}</button>
            <p v-if="cartStore.unavailableItems.length" role="status" class="text-xs text-amber-800 dark:text-amber-300">{{ $t('cart.checkout_blocked', { count: cartStore.unavailableItems.length }) }}</p>
            <p class="text-center">
              <RouterLink
                to="/cart"
                class="text-sm font-semibold text-primary transition-colors hover:text-primary-dark"
                @click="closeCartDrawer"
              >
                {{ $t('actions.view_cart') }}
              </RouterLink>
            </p>
          </footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.2s ease;
}
.drawer-enter-active .panel,
.drawer-leave-active .panel {
  transition: transform 0.25s ease;
}
.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}
.drawer-enter-from .panel,
.drawer-leave-to .panel {
  transform: translateX(100%);
}
</style>
