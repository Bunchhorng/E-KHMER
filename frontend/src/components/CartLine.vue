<script setup lang="ts">
import { computed } from 'vue'
import { Trash2 } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
import type { CartItem } from '@/types'
import AssetImage from './AssetImage.vue'
import QuantityCounter from './QuantityCounter.vue'
import { formatPrice } from '@/utils/format'

const props = defineProps<{ item: CartItem; compact?: boolean }>()
const cart = useCartStore()
const variantLabel = computed(() => props.item.variantName || props.item.variant?.attributes.map(a => `${a.name}: ${a.value}`).join(', ') || '')
const canAdjust = computed(() => props.item.available || props.item.unavailableReason === 'insufficient_stock')
const pending = computed(() => cart.isPending(`update:${props.item.id}`) || cart.isPending(`remove:${props.item.id}`) || cart.isPending('clear'))
async function updateQuantity(quantity: number) { await cart.updateQuantity(props.item.id, quantity) }
async function removeItem() { await cart.removeItem(props.item.id) }
</script>

<template>
  <div class="flex gap-3 py-4" :class="{ 'rounded-lg bg-amber-50/50 px-2 dark:bg-amber-500/5': !item.available }" :data-cart-item="item.id">
    <RouterLink v-if="item.available && item.slug" :to="`/product/${item.slug}`" class="shrink-0">
      <AssetImage :src="item.image" :alt="item.title" class="rounded-lg" :class="compact ? 'h-20 w-16' : 'h-24 w-20'" />
    </RouterLink>
    <AssetImage v-else :src="item.image" :alt="item.title" class="rounded-lg" :class="compact ? 'h-20 w-16' : 'h-24 w-20'" />
    <div class="min-w-0 flex-1">
      <RouterLink v-if="item.available && item.slug" :to="`/product/${item.slug}`" class="line-clamp-2 text-sm font-semibold text-ink hover:text-primary">{{ item.title }}</RouterLink>
      <p v-else class="text-sm font-semibold text-ink">{{ item.title }}</p>
      <p v-if="variantLabel" class="mt-0.5 text-xs text-muted">{{ variantLabel }}</p>
      <p v-if="!item.available" class="mt-1 text-xs font-medium text-amber-800 dark:text-amber-300">
        {{ $t(`cart.reasons.${item.unavailableReason ?? 'variant_deleted'}`, { count: item.availableQuantity ?? 0 }) }}
      </p>
      <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
        <span v-if="item.available" class="text-xs text-muted">{{ formatPrice(item.unitPrice) }}</span>
        <QuantityCounter v-if="canAdjust" size="sm" :model-value="item.quantity" :max="item.availableQuantity ?? 99" :disabled="pending" :aria-busy="pending" @update:model-value="updateQuantity" />
        <span v-else class="text-xs text-muted">{{ $t('cart.quantity', { count: item.quantity }) }}</span>
        <span v-if="item.available" class="text-sm font-semibold text-ink">{{ formatPrice(item.unitPrice * item.quantity) }}</span>
      </div>
    </div>
    <button type="button" class="btn-icon btn-sm shrink-0 self-start text-red-500" :aria-label="$t('cart.remove_item')" :disabled="pending" :aria-busy="cart.isPending(`remove:${item.id}`)" @click="removeItem">
      <Trash2 :size="16" />
    </button>
  </div>
</template>
