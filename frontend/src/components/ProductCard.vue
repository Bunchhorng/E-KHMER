<script setup lang="ts">
import { computed } from 'vue'
import { Heart, ShoppingCart } from 'lucide-vue-next'
import { useWishlistStore } from '@/stores/wishlist'
import type { Product } from '@/types'
import { formatPrice } from '@/utils/format'
import { swatchColor } from '@/utils/color'
import StarRating from './StarRating.vue'

const props = withDefaults(
  defineProps<{
    product: Product
    showActions?: boolean
  }>(),
  { showActions: true }
)

const emit = defineEmits<{
  (e: 'add-to-cart', payload: { product: Product; variantId?: string }): void
  (e: 'wishlist-toggle', productId: string): void
}>()

const wishlistStore = useWishlistStore()

const isWishlisted = computed(() => wishlistStore.isWishlisted(props.product.id))

const availableVariantId = computed(() => props.product.variants.find((v) => v.isInStock)?.id)

const coverImage = computed(() => props.product.images[0])

const discountPercent = computed(() => {
  const p = props.product
  if (p.discountPercent != null && p.discountPercent > 0) return p.discountPercent
  if (p.compareAtPrice && p.compareAtPrice > p.price) {
    return Math.round((1 - p.price / p.compareAtPrice) * 100)
  }
  return 0
})

function handleAddToCart(): void {
  emit('add-to-cart', { product: props.product, variantId: availableVariantId.value })
}

function handleWishlistToggle(): void {
  emit('wishlist-toggle', props.product.id)
}
</script>

<template>
  <div class="card feature-glow group flex flex-col overflow-hidden transition-shadow duration-300 hover:shadow-lg dark:bg-surface">
    <div class="relative aspect-square overflow-hidden bg-canvas dark:bg-surface-hover/50">
      <RouterLink :to="`/product/${props.product.slug}`" class="block h-full w-full">
        <img
          v-if="coverImage"
          :src="coverImage.url"
          :alt="coverImage.alt ?? props.product.title"
          loading="lazy"
          class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        />
      </RouterLink>
      <div v-if="discountPercent > 0 || props.product.isNew" class="absolute left-3 top-3 flex flex-col items-start gap-1">
        <span v-if="discountPercent > 0" class="rounded-md bg-accent px-2 py-1 text-xs font-bold text-ink dark:text-gray-900">
          -{{ discountPercent }}%
        </span>
        <span v-if="props.product.isNew" class="rounded-md bg-primary px-2 py-1 text-xs font-bold text-white dark:bg-blue-500">
          {{ $t('product.new') }}
        </span>
      </div>
      <button
        type="button"
        class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center rounded-full bg-white shadow-md transition-colors dark:bg-surface-hover dark:shadow-black/40"
        :class="isWishlisted ? 'text-red-500' : 'text-gray-400 hover:text-red-500 dark:text-muted'"
        :aria-label="isWishlisted ? $t('product.remove_from_wishlist') : $t('product.add_to_wishlist')"
        @click="handleWishlistToggle"
      >
        <Heart :size="16" :fill="isWishlisted ? 'currentColor' : 'none'" />
      </button>
    </div>

    <div class="flex flex-1 flex-col p-4">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-primary">
        {{ props.product.brand.name }}
      </p>
      <RouterLink
        :to="`/product/${props.product.slug}`"
        class="mt-1 line-clamp-2 text-sm font-semibold text-ink transition-colors hover:text-primary dark:text-ink"
      >
        {{ props.product.title }}
      </RouterLink>
      <div class="mt-1.5 flex items-center gap-1.5">
        <StarRating :value="props.product.rating" :size="12" />
        <span class="text-xs text-gray-500 dark:text-muted">({{ props.product.reviewCount }})</span>
      </div>
      <div class="mt-2 flex items-baseline gap-2">
        <span class="text-base font-bold text-ink dark:text-gray-50">{{ formatPrice(props.product.price) }}</span>
        <span v-if="props.product.compareAtPrice" class="text-sm text-gray-400 line-through dark:text-gray-500">
          {{ formatPrice(props.product.compareAtPrice) }}
        </span>
      </div>
      <ul v-if="props.product.colors.length" class="mt-2 flex flex-wrap items-center gap-1.5">
        <li
          v-for="color in props.product.colors"
          :key="color"
          class="h-3 w-3 rounded-full ring-1 ring-gray-300 dark:ring-gray-600"
          :style="{ backgroundColor: swatchColor(null, color) }"
          :title="color"
        ></li>
      </ul>
      <button
        v-if="showActions"
        type="button"
        class="btn-primary btn-sm mt-3 w-full dark:bg-blue-500 dark:hover:bg-blue-600"
        :disabled="!props.product.isInStock"
        @click="handleAddToCart"
      >
        <ShoppingCart :size="15" />
        {{ props.product.isInStock ? $t('actions.add_to_cart') : $t('product.out_of_stock') }}
      </button>
    </div>
  </div>
</template>