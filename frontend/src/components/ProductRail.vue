<script setup lang="ts">
import { ArrowRight, PackageOpen } from 'lucide-vue-next'
import { useWishlistStore } from '@/stores/wishlist'
import type { Product } from '@/types'
import EmptyState from './EmptyState.vue'
import ProductGrid from './ProductGrid.vue'

withDefaults(
  defineProps<{
    title: string
    subtitle?: string
    products?: Product[]
    viewAllLink?: string
  }>(),
  { subtitle: '', products: () => [], viewAllLink: '/shop' }
)

const wishlistStore = useWishlistStore()

function handleWishlist(productId: string): void {
  wishlistStore.toggle(productId)
}
</script>

<template>
  <section v-if="products.length > 0">
    <div class="mb-5 flex items-end justify-between gap-4">
      <div>
        <h2 class="section-title">{{ title }}</h2>
        <p v-if="subtitle" class="section-subtitle">{{ subtitle }}</p>
      </div>
      <RouterLink
        :to="viewAllLink"
        class="flex shrink-0 items-center gap-1 text-sm font-semibold text-primary transition-colors hover:text-primary-dark"
      >
        {{ $t('actions.view_all') }}
        <ArrowRight :size="16" />
      </RouterLink>
    </div>
    <ProductGrid :products="products" density="rail" @wishlist-toggle="handleWishlist" />
  </section>
  <EmptyState v-else :title="$t('account.no_products_title')">
    <template #icon>
      <PackageOpen :size="28" />
    </template>
  </EmptyState>
</template>
