<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ArrowUpRight, Heart, MapPin, Package, ShoppingBag, Star } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { accountApi } from '@/api/account'
import { ordersApi, type ApiOrderListItem } from '@/api/orders'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import EmptyState from '@/components/EmptyState.vue'
import ProductCard from '@/components/ProductCard.vue'
import AccountOrderList from '@/components/account/AccountOrderList.vue'
import AccountState from '@/components/account/AccountState.vue'
import { mapCatalogProduct } from '@/utils/product'
import type { Product } from '@/types'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const cart = useCartStore()
const wishlist = useWishlistStore()
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const orders = ref<ApiOrderListItem[]>([])
const summary = ref({ orders_count: 0, reviews_count: 0, wishlist_count: 0 })
const firstName = computed(() => auth.user?.name?.split(' ')[0] ?? '')
const cards = computed(() => [
  { key: 'account.total_orders', value: summary.value.orders_count, icon: ShoppingBag, to: '/account/orders', tint: 'bg-primary/10 text-primary' },
  { key: 'account.wishlist_items', value: summary.value.wishlist_count, icon: Heart, to: '/account/wishlist', tint: 'bg-rose-500/10 text-rose-500' },
  { key: 'nav.reviews', value: summary.value.reviews_count, icon: Star, to: '/account/reviews', tint: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' }
])
const products = computed(() => wishlist.products.slice(0, 4).map(mapCatalogProduct))
async function load() {
  loading.value = true; error.value = ''
  try {
    const [dashboard, recent] = await Promise.all([accountApi.getDashboard(), ordersApi.list()])
    summary.value = dashboard.data
    orders.value = recent.data.data.slice(0, 4)
  } catch { error.value = t('account.ui.load_error') }
  finally { loading.value = false }
}
async function addFromWishlist(product: Product, payload: { variantId?: string }) {
  if (!payload.variantId) return
  actionError.value = ''
  try {
    await cart.addItem({ variantId: payload.variantId, quantity: 1 })
    await wishlist.remove(product.id)
  } catch { actionError.value = t('account.ui.action_error') }
}
onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <section class="hero-gradient flex flex-col justify-between gap-5 rounded-2xl p-5 text-white sm:flex-row sm:items-center sm:p-7">
      <div><p class="text-xs font-medium uppercase tracking-widest text-blue-200">E-KHMER</p><h2 class="mt-2 text-xl font-bold sm:text-2xl">{{ $t('account.welcome_back_name', { name: firstName }) }}</h2><p class="mt-2 max-w-xl text-sm leading-relaxed text-blue-100">{{ $t('account.today_summary') }}</p></div>
      <RouterLink to="/shop" class="btn-secondary w-fit shrink-0 !border-white/20 !bg-white/10 !text-white hover:!bg-white/20">{{ $t('account.ui.shop') }}<ArrowUpRight class="h-4 w-4" /></RouterLink>
    </section>
    <AccountState :loading="loading" :error="error" @retry="load">
      <div class="grid gap-3 sm:grid-cols-3 sm:gap-4">
        <RouterLink v-for="card in cards" :key="card.key" :to="card.to" class="card group flex items-center gap-4 p-5 transition hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="card.tint"><component :is="card.icon" class="h-5 w-5" /></span>
          <div class="min-w-0 flex-1"><p class="text-xs text-muted">{{ $t(card.key) }}</p><p class="mt-1 text-2xl font-bold">{{ card.value }}</p></div><ArrowUpRight class="h-4 w-4 shrink-0 text-muted group-hover:text-primary" />
        </RouterLink>
      </div>
      <section class="space-y-4">
        <div class="account-toolbar"><h2 class="text-lg font-semibold">{{ $t('account.recent_orders') }}</h2><RouterLink to="/account/orders" class="btn-ghost btn-sm min-h-10 text-primary">{{ $t('actions.view_all') }}<ArrowUpRight class="h-4 w-4" /></RouterLink></div>
        <AccountOrderList v-if="orders.length" :orders="orders" />
        <EmptyState v-else :title="$t('account.no_orders_title')" :description="$t('account.no_orders_description')" :cta-label="$t('account.start_shopping')" @cta="router.push('/shop')"><template #icon><Package class="h-7 w-7" /></template></EmptyState>
      </section>
    </AccountState>
    <section class="space-y-4">
      <div class="account-toolbar"><h2 class="text-lg font-semibold">{{ $t('account.your_wishlist') }}</h2><RouterLink to="/account/wishlist" class="btn-ghost btn-sm min-h-10 text-primary">{{ $t('actions.view_all') }}<ArrowUpRight class="h-4 w-4" /></RouterLink></div>
      <p v-if="actionError" role="alert" class="text-sm text-red-500">{{ actionError }}</p>
      <AccountState :loading="wishlist.loading" :error="wishlist.error ? $t('account.ui.load_error') : ''" @retry="wishlist.fetchProducts()">
        <div v-if="products.length" class="grid grid-cols-2 gap-3 xl:grid-cols-4"><ProductCard v-for="product in products" :key="product.id" :product="product" @add-to-cart="addFromWishlist(product, $event)" @wishlist-toggle="wishlist.toggle($event)" /></div>
        <EmptyState v-else :title="$t('account.wishlist_empty_title')" :description="$t('account.wishlist_empty_description')" :cta-label="$t('account.explore_products')" @cta="router.push('/shop')"><template #icon><Heart class="h-7 w-7" /></template></EmptyState>
      </AccountState>
    </section>
    <RouterLink to="/account/addresses" class="card flex items-center gap-4 p-5 hover:border-primary/40"><MapPin class="h-5 w-5 shrink-0 text-primary" /><div class="flex-1"><p class="text-sm font-semibold">{{ $t('account.my_addresses') }}</p><p class="mt-1 text-xs text-muted">{{ $t('account.manage_addresses_hint') }}</p></div><ArrowUpRight class="h-4 w-4 text-muted" /></RouterLink>
  </div>
</template>
