<script setup lang="ts">
import { computed } from 'vue'
import { Loader2, Trash2 } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
import type { CartItem } from '@/types'
import AssetImage from './AssetImage.vue'
import QuantityCounter from './QuantityCounter.vue'
import { formatPrice } from '@/utils/format'

const props = defineProps<{ item: CartItem; compact?: boolean }>()
const cart = useCartStore()
const variantLabel = computed(() => props.item.variantName || props.item.variant?.attributes.map(a => `${a.name}: ${a.value}`).join(', ') || '')
const canAdjust = computed(() => props.item.available || props.item.unavailableReason === 'insufficient_stock')
const updating = computed(() => cart.isPending(`update:${props.item.id}`))
const removing = computed(() => cart.isPending(`remove:${props.item.id}`))
const pending = computed(() => updating.value || removing.value || cart.isPending('clear'))
async function updateQuantity(quantity: number) { await cart.updateQuantity(props.item.id, quantity) }
async function removeItem() { await cart.removeItem(props.item.id) }
</script>

<template>
  <div
    class="flex items-start gap-3 py-3.5 transition-opacity"
    :class="{
      'bg-amber-500/10 px-2.5': !item.available,
      'opacity-70': pending
    }"
    :data-cart-item="item.id"
  >
    <RouterLink
      v-if="item.available && item.slug"
      :to="`/product/${item.slug}`"
      class="shrink-0 rounded-none border border-border-gray bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
    >
      <AssetImage :src="item.image" :alt="item.title" class="rounded-none" :class="compact ? 'h-20 w-20' : 'h-24 w-24'" />
    </RouterLink>
    <AssetImage
      v-else
      :src="item.image"
      :alt="item.title"
      class="shrink-0 rounded-none border border-border-gray"
      :class="compact ? 'h-20 w-20' : 'h-24 w-24'"
    />

    <div class="min-w-0 flex-1">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
          <RouterLink
            v-if="item.available && item.slug"
            :to="`/product/${item.slug}`"
            class="line-clamp-2 break-words text-sm font-semibold leading-snug text-ink transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
          >
            {{ item.title }}
          </RouterLink>
          <p v-else class="line-clamp-2 break-words text-sm font-semibold leading-snug text-ink">{{ item.title }}</p>
          <p v-if="variantLabel" class="mt-0.5 truncate text-xs text-text-secondary">{{ variantLabel }}</p>
          <p v-if="item.available" class="mt-1 text-xs text-muted">{{ formatPrice(item.unitPrice) }}</p>
        </div>

        <button
          type="button"
          class="btn-icon -mr-1.5 -mt-1.5 shrink-0 rounded-none text-muted hover:bg-danger/10 hover:text-danger focus-visible:ring-2 focus-visible:ring-border-focus sm:h-8 sm:w-8"
          :aria-label="$t('cart.remove_item')"
          :disabled="pending"
          :aria-busy="removing"
          @click="removeItem"
        >
          <Loader2 v-if="removing" :size="15" class="animate-spin" aria-hidden="true" />
          <Trash2 v-else :size="15" aria-hidden="true" />
        </button>
      </div>

      <p
        v-if="!item.available"
        class="mt-2 border-l-2 border-amber-500 bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-800 dark:text-amber-300"
      >
        {{ $t(`cart.reasons.${item.unavailableReason ?? 'variant_deleted'}`, { count: item.availableQuantity ?? 0 }) }}
      </p>

      <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
        <QuantityCounter
          v-if="canAdjust"
          :size="compact ? 'sm' : 'md'"
          :model-value="item.quantity"
          :max="item.availableQuantity ?? 99"
          :disabled="pending"
          :aria-busy="pending"
          @update:model-value="updateQuantity"
        />
        <span v-else class="text-xs font-medium text-muted">{{ $t('cart.quantity', { count: item.quantity }) }}</span>

        <span v-if="item.available" class="ml-auto shrink-0 text-sm font-bold text-ink">
          {{ formatPrice(item.unitPrice * item.quantity) }}
        </span>
      </div>
    </div>
  </div>
</template>
