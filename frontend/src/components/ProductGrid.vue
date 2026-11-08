<script setup lang="ts">
import { computed } from 'vue'
import type { Product } from '@/types'
import ProductCard from './ProductCard.vue'

const props = withDefaults(defineProps<{
  products: Product[]
  density?: 'standard' | 'rail'
  showActions?: boolean
}>(), {
  density: 'standard',
  showActions: true,
})

const emit = defineEmits<{
  (e: 'added-to-cart', payload: { product: Product; variantId: string }): void
  (e: 'wishlist-toggle', productId: string): void
}>()

const gridClass = computed(() => props.density === 'rail'
  ? 'grid grid-cols-2 gap-3 max-[359px]:grid-cols-1 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6'
  : 'grid grid-cols-2 gap-3 max-[359px]:grid-cols-1 sm:gap-5 lg:grid-cols-4 2xl:grid-cols-5')
</script>

<template>
  <div data-testid="product-grid" :class="gridClass">
    <ProductCard
      v-for="product in products"
      :key="product.id"
      :product="product"
      :show-actions="showActions"
      @added-to-cart="emit('added-to-cart', $event)"
      @wishlist-toggle="emit('wishlist-toggle', $event)"
    />
  </div>
</template>
