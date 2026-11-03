<script setup lang="ts">
import { computed, ref } from 'vue'
import { Heart, ShoppingCart } from 'lucide-vue-next'
import { useWishlistStore } from '@/stores/wishlist'
import { useCartStore } from '@/stores/cart'
import type { Product } from '@/types'
import { formatPrice } from '@/utils/format'
import { swatchColor } from '@/utils/color'
import StarRating from './StarRating.vue'
import AppImage from './AppImage.vue'

const props = withDefaults(
  defineProps<{
    product: Product
    showActions?: boolean
  }>(),
  { showActions: true }
)

const emit = defineEmits<{
  (e: 'added-to-cart', payload: { product: Product; variantId: string }): void
  (e: 'wishlist-toggle', productId: string): void
}>()

const wishlistStore = useWishlistStore()
const cart = useCartStore()
const adding = ref(false)
const added = ref(false)

const isWishlisted = computed(() => wishlistStore.isWishlisted(props.product.id))

const availableVariantId = computed(() => props.product.variants.find((v) => v.isInStock)?.id)
const mutationKey = computed(() => `add:${availableVariantId.value}`)
const pending = computed(() => adding.value || cart.isPending(mutationKey.value))

const coverImage = computed(() => props.product.images[0])

const discountPercent = computed(() => {
  const p = props.product
  if (p.discountPercent != null && p.discountPercent > 0) return p.discountPercent
  if (p.compareAtPrice && p.compareAtPrice > p.price) {
    return Math.round((1 - p.price / p.compareAtPrice) * 100)
  }
  return 0
})

async function handleAddToCart(): Promise<void> {
  const variantId = availableVariantId.value
  if (!variantId || pending.value) return
  const product = props.product
  adding.value = true
  added.value = false
  try {
    if (await cart.addItem({ variantId, quantity: 1 })) {
      added.value = true
      emit('added-to-cart', { product, variantId })
    }
  } finally { adding.value = false }
}

function handleWishlistToggle(): void {
  emit('wishlist-toggle', props.product.id)
}
</script>

<template>
  <div class="card feature-glow group flex h-full flex-col overflow-hidden transition-shadow duration-300 hover:shadow-lg dark:bg-surface">
    <div class="relative aspect-[4/3] shrink-0 overflow-hidden bg-canvas sm:aspect-square dark:bg-surface-hover/50">
      <RouterLink :to="`/product/${props.product.slug}`" class="block h-full w-full" :aria-label="`${$t('actions.view_all')}: ${props.product.title}`">
        <AppImage
          :src="coverImage?.url"
          :alt="coverImage?.alt ?? props.product.title"
          class="h-full w-full transition-transform duration-300 group-hover:scale-105"
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
        class="absolute right-3 top-3 flex h-11 w-11 items-center justify-center rounded-full bg-white shadow-md transition-colors dark:bg-surface-hover dark:shadow-black/40"
        :class="isWishlisted ? 'text-red-500' : 'text-gray-400 hover:text-red-500 dark:text-muted'"
        :aria-label="isWishlisted ? $t('product.remove_from_wishlist') : $t('product.add_to_wishlist')"
        @click="handleWishlistToggle"
      >
        <Heart :size="18" :fill="isWishlisted ? 'currentColor' : 'none'" />
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
      <div class="mt-2 flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
        <span class="min-w-0 break-words text-base font-bold text-ink dark:text-gray-50">{{ formatPrice(props.product.price) }}</span>
        <span v-if="props.product.compareAtPrice" class="min-w-0 break-words text-sm text-gray-400 line-through dark:text-gray-500">
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
      <div v-if="showActions" class="mt-auto pt-3">
        <button
          type="button"
          class="btn-primary btn-sm !min-h-11 w-full dark:bg-blue-500 dark:hover:bg-blue-600"
          :disabled="!props.product.isInStock || !availableVariantId || pending"
          :aria-busy="pending"
          @click="handleAddToCart"
        >
          <ShoppingCart :size="15" />
          {{ pending ? $t('cart.adding') : props.product.isInStock ? $t('actions.add_to_cart') : $t('product.out_of_stock') }}
        </button>
        <p v-if="added" role="status" class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">{{ $t('cart.added') }}</p>
      </div>
    </div>
  </div>
</template>
