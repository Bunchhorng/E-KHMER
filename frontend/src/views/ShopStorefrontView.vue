<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { MapPin, Search } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { shopsApi, type PublicShop } from '@/api/shops'
import type { CatalogProduct } from '@/api/catalog'
import { extractErrorMessage } from '@/api/errors'
import { mapCatalogProduct } from '@/utils/product'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import AssetImage from '@/components/AssetImage.vue'
import BasePagination from '@/components/BasePagination.vue'
import EmptyState from '@/components/EmptyState.vue'
import ProductCard from '@/components/ProductCard.vue'
const { t } = useI18n()
const route = useRoute(); const router = useRouter()
const cart = useCartStore(); const wishlist = useWishlistStore()
const shop = ref<PublicShop | null>(null); const items = ref<CatalogProduct[]>([])
const products = computed(() => items.value.map(mapCatalogProduct))
const search = ref(''); const loading = ref(true); const error = ref('')
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 })
let request = 0
async function load() {
  const current = ++request; loading.value = true; error.value = ''; search.value = String(route.query.q ?? '')
  try {
    const slug = String(route.params.slug)
    const [details, listing] = await Promise.all([shopsApi.get(slug), shopsApi.products(slug, {
      q: search.value || undefined, sort: String(route.query.sort ?? 'newest'), stock: route.query.stock === '1' ? 1 : 0,
      min: typeof route.query.min === 'string' ? route.query.min : undefined, max: typeof route.query.max === 'string' ? route.query.max : undefined,
      page: Number(route.query.page) || 1
    })])
    if (current !== request) return
    shop.value = details.data.data; items.value = listing.data.data; meta.value = listing.data.meta
    document.title = `${shop.value.name} · E-KHMER`
  } catch (cause) { if (current === request) error.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { if (current === request) loading.value = false }
}
function filter(values: Record<string, string | undefined>) { router.push({ query: { ...route.query, ...values, page: undefined } }) }
watch(() => route.fullPath, load, { immediate: true })
onBeforeUnmount(() => request++)
</script>
<template>
  <main class="container-app space-y-6 py-6">
    <RouterLink to="/shops" class="text-sm text-primary">← {{ t('storefront.title') }}</RouterLink>
    <p v-if="error" role="alert" class="card p-5 text-red-600">{{ error }} <button class="ml-2 underline" @click="load">{{ t('actions.retry') }}</button></p>
    <div v-else-if="loading" class="card h-80 animate-pulse bg-border-gray/20" aria-busy="true"></div>
    <template v-else-if="shop">
      <section class="card overflow-hidden rounded-2xl"><AssetImage v-if="shop.banner" :src="shop.banner" :alt="shop.name" class="h-40 w-full bg-canvas sm:h-56" /><div class="p-5 sm:p-7"><div class="flex items-center gap-4"><AssetImage :src="shop.logo" :alt="shop.name" class="h-20 w-20 rounded-2xl border border-border-gray bg-canvas" /><div><h1 class="text-2xl font-bold sm:text-3xl">{{ shop.name }}</h1><p class="mt-2 text-sm text-muted">{{ t('storefront.product_count', { count: shop.products_count }) }}<span v-if="shop.rating_count"> · ★ {{ shop.rating_avg }} ({{ shop.rating_count }})</span></p></div></div><p v-if="shop.description" class="mt-5 max-w-3xl whitespace-pre-line text-sm leading-relaxed text-muted">{{ shop.description }}</p><div class="mt-4 flex flex-wrap gap-4 text-xs text-muted"><p v-if="shop.city || shop.address_line" class="flex items-center gap-1"><MapPin :size="14" />{{ [shop.address_line, shop.city, shop.province].filter(Boolean).join(', ') }}</p><span v-if="shop.phone">{{ shop.phone }}</span><span v-if="shop.email">{{ shop.email }}</span></div></div></section>
      <div class="card flex flex-wrap items-end gap-4 p-4">
        <form class="flex min-w-48 flex-1 gap-2" @submit.prevent="filter({ q: search.trim() || undefined })"><input v-model="search" class="input min-w-0 flex-1" maxlength="120" :aria-label="t('storefront.search_products')" :placeholder="t('storefront.search_products')" /><button class="btn-secondary"><Search :size="18" /><span class="sr-only">{{ t('actions.search') }}</span></button></form>
        <label><span class="sr-only">{{ t('storefront.sort') }}</span><select :value="route.query.sort ?? 'newest'" class="select" @change="filter({ sort: ($event.target as HTMLSelectElement).value })"><option value="newest">{{ t('storefront.newest') }}</option><option value="price-asc">{{ t('shop.sort_price_asc') }}</option><option value="price-desc">{{ t('shop.sort_price_desc') }}</option><option value="rating">{{ t('shop.sort_rating') }}</option></select></label>
        <label class="flex items-center gap-2 py-3 text-sm"><input type="checkbox" :checked="route.query.stock === '1'" @change="filter({ stock: ($event.target as HTMLInputElement).checked ? '1' : undefined })" />{{ t('shop.in_stock_only') }}</label>
      </div>
      <div v-if="products.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4"><ProductCard v-for="product in products" :key="product.id" :product="product" @add-to-cart="$event.variantId && cart.addItem({ variantId: $event.variantId, quantity: 1 })" @wishlist-toggle="wishlist.toggle($event)" /></div>
      <EmptyState v-else :title="t('shop.no_match_title')" :description="t('shop.no_match_description')" />
      <BasePagination :page="meta.current_page" :page-count="meta.last_page" :total-items="meta.total" :page-size="meta.per_page" @update:page="router.push({ query: { ...route.query, page: $event } })" />
    </template>
  </main>
</template>

