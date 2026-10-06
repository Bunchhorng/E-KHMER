<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { Heart } from 'lucide-vue-next'
import EmptyState from '@/components/EmptyState.vue'
import ProductCard from '@/components/ProductCard.vue'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import { computed } from 'vue'
import { mapCatalogProduct } from '@/utils/product'
import type { Product } from '@/types'

interface AddToCartPayload {
  variantId?: string
}

const router = useRouter()
const cartStore = useCartStore()
const wishlist = useWishlistStore()

const count = computed(() => wishlist.count)

const products = computed(() => wishlist.products.map(mapCatalogProduct))

async function addToCart(product: Product, payload: AddToCartPayload) {
  if (!payload.variantId) return
  await cartStore.addItem({ variantId: payload.variantId, quantity: 1 })
  await wishlist.remove(product.id)
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-2xl font-bold text-ink dark:text-ink">{{ $t('account.wishlist_count', { count }) }}</h1>
      <RouterLink to="/shop" class="btn-outline btn-sm w-fit">{{ $t('account.add_from_collection') }}</RouterLink>
    </div>

    <div v-if="products.length" class="grid grid-cols-2 gap-5 md:grid-cols-3 xl:grid-cols-4">
      <ProductCard
        v-for="p in products"
        :key="p.id"
        :product="p"
        @add-to-cart="(e) => addToCart(p, e)"
        @wishlist-toggle="wishlist.toggle($event)"
      />
    </div>

    <EmptyState
      v-else
      :title="$t('account.wishlist_cta_title')"
      :description="$t('account.wishlist_cta_description')"
      :cta-label="$t('account.start_shopping')"
      @cta="router.push('/shop')"
    >
      <template #icon>
        <Heart class="h-10 w-10 text-gray-300" />
      </template>
    </EmptyState>
  </div>
</template>
