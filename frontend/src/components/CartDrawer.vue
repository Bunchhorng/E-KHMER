<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Loader2, ShoppingBag, X } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
import { useUiStore } from '@/stores/ui'
import EmptyState from './EmptyState.vue'
import CartLine from './CartLine.vue'
import CartLoadState from './CartLoadState.vue'
import QuoteSummary from './QuoteSummary.vue'

const route = useRoute()
const router = useRouter()
const cartStore = useCartStore()
const uiStore = useUiStore()
const drawerRef = ref<HTMLElement | null>(null)
const drawerCloseRef = ref<HTMLButtonElement | null>(null)
let previousBodyOverflow = ''
let previousFocusedElement: HTMLElement | null = null

function closeCartDrawer(): void {
  uiStore.closeCartDrawer()
}

function startShopping(): void {
  closeCartDrawer()
  router.push('/shop')
}

function onDrawerKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    closeCartDrawer()
    return
  }
  if (event.key !== 'Tab' || !drawerRef.value) return
  const focusable = Array.from(
    drawerRef.value.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
  )
  if (!focusable.length) return
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

watch(
  () => uiStore.cartDrawerOpen,
  (open) => {
    if (open) {
      previousFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null
      previousBodyOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
      window.addEventListener('keydown', onDrawerKeydown)
      nextTick(() => drawerCloseRef.value?.focus())
    } else {
      window.removeEventListener('keydown', onDrawerKeydown)
      document.body.style.overflow = previousBodyOverflow
      const target = previousFocusedElement
      previousFocusedElement = null
      if (target && document.contains(target)) {
        nextTick(() => target.focus())
      }
    }
  }
)

watch(() => route.fullPath, closeCartDrawer)

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onDrawerKeydown)
  if (uiStore.cartDrawerOpen) document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="uiStore.cartDrawerOpen" class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-slate-950/50 backdrop-blur-[1px]" aria-hidden="true" @click="closeCartDrawer"></div>
        <aside
          ref="drawerRef"
          role="dialog"
          aria-modal="true"
          aria-labelledby="cart-drawer-title"
          class="panel fixed inset-y-0 right-0 z-50 flex h-[100dvh] w-full flex-col !rounded-none !border-y-0 !border-r-0 !p-0 bg-surface shadow-raised sm:w-[27.5rem] sm:max-w-[92vw]"
        >
          <header class="flex shrink-0 items-center justify-between gap-3 border-b border-border-gray bg-surface px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] sm:px-5 sm:py-3.5">
            <h2 id="cart-drawer-title" class="flex min-w-0 items-center gap-2.5 text-base font-bold tracking-tight text-ink sm:text-lg">
              <span class="truncate">{{ $t('cart.your_cart') }}</span>
              <span
                v-if="cartStore.totalItemCount > 0"
                class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-none bg-primary px-1.5 text-xs font-bold tabular-nums text-white"
              >
                {{ cartStore.totalItemCount }}
              </span>
            </h2>
            <button
              ref="drawerCloseRef"
              type="button"
              class="btn-icon shrink-0 rounded-none"
              :aria-label="$t('cart.close_cart')"
              @click="closeCartDrawer"
            >
              <X :size="20" aria-hidden="true" />
            </button>
          </header>

          <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-3 sm:px-5">
            <CartLoadState />
            <EmptyState
              v-if="cartStore.isEmpty"
              compact
              :title="$t('cart.empty_title')"
              :description="$t('cart.empty_description')"
              :cta-label="$t('actions.continue_shopping')"
              @cta="startShopping"
            >
              <template #icon>
                <ShoppingBag :size="22" aria-hidden="true" />
              </template>
            </EmptyState>

            <ul v-else-if="cartStore.loaded" class="divide-y divide-border-gray">
              <li v-for="item in cartStore.items" :key="item.id">
                <CartLine :item="item" compact />
              </li>
            </ul>
          </div>

          <footer
            v-if="cartStore.items.length > 0"
            class="shrink-0 space-y-3 border-t border-border-gray bg-surface-muted/60 px-4 pt-3.5 pb-[max(0.875rem,env(safe-area-inset-bottom))] sm:px-5 sm:py-4"
          >
            <QuoteSummary estimated />

            <p class="text-xs leading-relaxed text-muted">{{ $t('cart.shipping_note') }}</p>

            <p
              v-if="cartStore.unavailableItems.length"
              role="status"
              class="border-l-2 border-amber-500 bg-amber-500/10 px-2.5 py-1.5 text-xs font-medium text-amber-800 dark:text-amber-300"
            >
              {{ $t('cart.checkout_blocked', { count: cartStore.unavailableItems.length }) }}
            </p>

            <div class="grid gap-2 pt-0.5">
              <RouterLink
                v-if="cartStore.canCheckout"
                to="/checkout"
                class="btn-primary min-h-11 w-full rounded-none"
                @click="closeCartDrawer"
              >
                {{ $t('actions.checkout') }}
              </RouterLink>
              <button
                v-else
                type="button"
                class="btn-primary min-h-11 w-full rounded-none"
                disabled
                :aria-busy="cartStore.quoteLoading || cartStore.mutating"
              >
                <Loader2
                  v-if="cartStore.quoteLoading || cartStore.mutating"
                  :size="16"
                  class="animate-spin"
                  aria-hidden="true"
                />
                {{ $t('actions.checkout') }}
              </button>

              <RouterLink
                to="/cart"
                class="btn-secondary min-h-11 w-full rounded-none"
                @click="closeCartDrawer"
              >
                {{ $t('actions.view_cart') }}
              </RouterLink>
            </div>
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
