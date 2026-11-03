<script setup lang="ts">
import { ArrowRight, PackageOpen } from 'lucide-vue-next'
import { useWishlistStore } from '@/stores/wishlist'
import type { Product } from '@/types'
import EmptyState from './EmptyState.vue'
import ProductCard from './ProductCard.vue'

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
    <div class="grid grid-cols-2 gap-3 max-[359px]:grid-cols-1 sm:gap-4 sm:grid-cols-3 lg:grid-cols-6">
      <ProductCard
        v-for="product in products"
        :key="product.id"
        :product="product"
        @wishlist-toggle="handleWishlist"
      />
    </div>
  </section>
  <EmptyState v-else :title="$t('account.no_products_title')">
    <template #icon>
      <PackageOpen :size="28" />
    </template>
  </EmptyState>
</template>
