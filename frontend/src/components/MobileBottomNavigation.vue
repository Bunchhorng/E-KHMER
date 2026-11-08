<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { House, LayoutGrid, ShoppingBag, Store, User, X } from 'lucide-vue-next'
import { categoriesApi } from '@/api'
import { useCartStore } from '@/stores/cart'

const route = useRoute()
const cart = useCartStore()
const categorySheetOpen = ref(false)
const categories = ref<{ id: number; name: string; slug: string }[]>([])
const loadingCategories = ref(false)
let previousBodyOverflow = ''

const shouldHideNavigation = computed(() => ['checkout', 'bakong-payment', 'order-success', 'order-tracking'].includes(String(route.name)))
const isShopRoute = computed(() => route.name === 'shop')

function itemClass(active: boolean): string {
  return active
    ? 'text-primary'
    : 'text-muted hover:text-ink dark:hover:text-white'
}

function closeCategorySheet(): void {
  categorySheetOpen.value = false
}

async function toggleCategorySheet(): Promise<void> {
  categorySheetOpen.value = !categorySheetOpen.value
  if (!categorySheetOpen.value || categories.value.length || loadingCategories.value) return

  loadingCategories.value = true
  try {
    const { data } = await categoriesApi.getTree()
    categories.value = data.data
  } finally {
    loadingCategories.value = false
  }
}

watch(categorySheetOpen, (open) => {
  if (open) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = previousBodyOverflow
  }
})

watch(() => route.fullPath, closeCategorySheet)

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') closeCategorySheet()
}

watch(categorySheetOpen, (open) => {
  if (open) window.addEventListener('keydown', onKeydown)
  else window.removeEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  if (categorySheetOpen.value) document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <nav
    v-if="!shouldHideNavigation"
    aria-label="Mobile navigation"
    class="fixed inset-x-0 bottom-0 z-40 border-t border-border-gray bg-surface/95 px-1 pb-[env(safe-area-inset-bottom)] pt-1 shadow-[0_-8px_24px_rgb(15_23_42_/_0.08)] backdrop-blur lg:hidden"
  >
    <div class="mx-auto grid max-w-lg grid-cols-5">
      <RouterLink to="/" class="mobile-nav-item" :class="itemClass(route.name === 'home')">
        <House :size="21" :stroke-width="route.name === 'home' ? 2.5 : 2" />
        <span>{{ $t('nav.home') }}</span>
      </RouterLink>

      <button type="button" class="mobile-nav-item" :class="itemClass(categorySheetOpen || isShopRoute)" :aria-expanded="categorySheetOpen" aria-controls="mobile-category-sheet" @click="toggleCategorySheet">
        <LayoutGrid :size="21" :stroke-width="categorySheetOpen || isShopRoute ? 2.5 : 2" />
        <span>{{ $t('nav.shop_by_category') }}</span>
      </button>

      <RouterLink to="/shops" class="mobile-nav-item" :class="itemClass(route.name === 'shops' || route.name === 'shop-storefront')">
        <Store :size="21" :stroke-width="route.name === 'shops' || route.name === 'shop-storefront' ? 2.5 : 2" />
        <span>{{ $t('storefront.title') }}</span>
      </RouterLink>

      <RouterLink to="/cart" class="mobile-nav-item relative" :class="itemClass(route.name === 'cart')">
        <ShoppingBag :size="21" :stroke-width="route.name === 'cart' ? 2.5 : 2" />
        <span v-if="cart.totalItemCount > 0" class="mobile-nav-badge">{{ cart.totalItemCount > 99 ? '99+' : cart.totalItemCount }}</span>
        <span>{{ $t('nav.cart') }}</span>
      </RouterLink>

      <RouterLink to="/account" class="mobile-nav-item" :class="itemClass(route.path.startsWith('/account'))">
        <User :size="21" :stroke-width="route.path.startsWith('/account') ? 2.5 : 2" />
        <span>{{ $t('nav.account') }}</span>
      </RouterLink>
    </div>
  </nav>

  <Teleport to="body">
    <Transition name="category-sheet">
      <div v-if="categorySheetOpen" class="fixed inset-0 z-50 lg:hidden">
        <button type="button" class="absolute inset-0 h-full w-full bg-slate-950/40" :aria-label="$t('actions.close')" @click="closeCategorySheet"></button>
        <section id="mobile-category-sheet" role="dialog" aria-modal="true" :aria-label="$t('nav.shop_by_category')" class="absolute inset-x-0 bottom-0 max-h-[min(76dvh,38rem)] overflow-hidden rounded-t-[1.5rem] bg-surface shadow-raised">
          <header class="flex items-center justify-between border-b border-border-gray px-5 py-4">
            <div>
              <p class="section-eyebrow">{{ $t('home.browse') }}</p>
              <h2 class="mt-1 text-lg font-bold text-ink">{{ $t('nav.shop_by_category') }}</h2>
            </div>
            <button type="button" class="btn-icon" :aria-label="$t('actions.close')" @click="closeCategorySheet"><X :size="20" /></button>
          </header>
          <div class="grid max-h-[calc(min(76dvh,38rem)-5.5rem)] grid-cols-2 gap-2 overflow-y-auto p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <RouterLink to="/shop" class="category-sheet-link" @click="closeCategorySheet">
              <LayoutGrid :size="18" />
              {{ $t('nav.all_products') }}
            </RouterLink>
            <RouterLink v-for="category in categories" :key="category.id" :to="{ name: 'shop', query: { category: category.slug } }" class="category-sheet-link" @click="closeCategorySheet">
              <LayoutGrid :size="18" />
              <span class="truncate">{{ $te(`categories.${category.slug.replace(/-/g, '_')}`) ? $t(`categories.${category.slug.replace(/-/g, '_')}`) : category.name }}</span>
            </RouterLink>
            <p v-if="loadingCategories" class="col-span-2 py-5 text-center text-sm text-muted">{{ $t('common.loading') }}</p>
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.mobile-nav-item {
  position: relative;
  display: inline-flex;
  min-height: 3.75rem;
  min-width: 0;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.1rem;
  padding: 0.25rem;
  font-size: 0.625rem;
  font-weight: 700;
  line-height: 1.15;
  text-align: center;
  transition: color 0.15s ease, transform 0.15s ease;
}
.mobile-nav-item:active { transform: scale(0.94); }
.mobile-nav-badge {
  position: absolute;
  top: 0.05rem;
  right: calc(50% - 1.25rem);
  display: inline-flex;
  min-width: 1.1rem;
  height: 1.1rem;
  align-items: center;
  justify-content: center;
  border: 2px solid rgb(var(--color-surface));
  border-radius: 9999px;
  background: rgb(var(--color-primary));
  padding: 0 0.18rem;
  color: white;
  font-size: 0.55rem;
  font-weight: 800;
  line-height: 1;
}
.category-sheet-link {
  display: flex;
  min-height: 3.25rem;
  min-width: 0;
  align-items: center;
  gap: 0.55rem;
  border: 1px solid rgb(var(--color-border));
  border-radius: 0.75rem;
  background: rgb(var(--color-canvas));
  padding: 0.65rem;
  color: rgb(var(--color-ink));
  font-size: 0.75rem;
  font-weight: 700;
}
.category-sheet-link:active { background: rgb(var(--color-surface-active)); color: rgb(var(--color-primary)); }
.category-sheet-enter-active, .category-sheet-leave-active { transition: opacity 0.2s ease; }
.category-sheet-enter-active section, .category-sheet-leave-active section { transition: transform 0.25s ease; }
.category-sheet-enter-from, .category-sheet-leave-to { opacity: 0; }
.category-sheet-enter-from section, .category-sheet-leave-to section { transform: translateY(100%); }
</style>
