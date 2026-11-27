<script setup lang="ts">
import { computed } from 'vue'
import { Loader2, Store, Trash2 } from 'lucide-vue-next'
import { useCartStore } from '@/stores/cart'
import type { CartItem } from '@/types'
import AssetImage from './AssetImage.vue'
import QuantityCounter from './QuantityCounter.vue'
import { formatPrice } from '@/utils/format'

const props = defineProps<{ item: CartItem; compact?: boolean; showSeller?: boolean }>()
const emit = defineEmits<{
  (e: 'removed', item: CartItem): void
  (e: 'updated', item: CartItem): void
}>()

const cart = useCartStore()
const variantAttributes = computed(() => props.item.variant?.attributes ?? [])
const fallbackVariantName = computed(() => (variantAttributes.value.length ? '' : props.item.variantName || ''))
const canAdjust = computed(() => props.item.available || props.item.unavailableReason === 'insufficient_stock')
const updating = computed(() => cart.isPending(`update:${props.item.id}`))
const removing = computed(() => cart.isPending(`remove:${props.item.id}`))
const pending = computed(() => updating.value || removing.value || cart.isPending('clear'))

async function updateQuantity(quantity: number) {
  const ok = await cart.updateQuantity(props.item.id, quantity)
  if (ok) emit('updated', props.item)
}

async function removeItem() {
  const snapshot = { ...props.item }
  const ok = await cart.removeItem(props.item.id)
  if (ok) emit('removed', snapshot)
}
</script>

<template>
  <div
    class="grid grid-cols-[auto_minmax(0,1fr)_auto] gap-x-3 gap-y-2.5 transition-opacity"
    :class="[
      compact
        ? 'py-3.5'
        : 'py-4 sm:grid-cols-[auto_minmax(0,1fr)_auto_minmax(5.75rem,auto)_auto] sm:items-center sm:gap-4',
      {
        'bg-amber-500/10 px-3': !item.available,
        'opacity-70': pending
      }
    ]"
    :data-cart-item="item.id"
  >
    <RouterLink
      v-if="item.available && item.slug"
      :to="`/product/${item.slug}`"
      class="col-start-1 row-span-2 shrink-0 self-start rounded-none border border-border-gray bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
      :class="{ 'sm:row-span-1 sm:self-center': !compact }"
    >
      <AssetImage :src="item.image" :alt="item.title" class="rounded-none" :class="compact ? 'h-20 w-20' : 'h-20 w-20 sm:h-24 sm:w-24'" />
    </RouterLink>
    <AssetImage
      v-else
      :src="item.image"
      :alt="item.title"
      class="col-start-1 row-span-2 shrink-0 self-start rounded-none border border-border-gray"
      :class="[compact ? 'h-20 w-20' : 'h-20 w-20 sm:h-24 sm:w-24 sm:row-span-1 sm:self-center']"
    />

    <div class="col-start-2 row-start-1 min-w-0">
      <RouterLink
        v-if="item.available && item.slug"
        :to="`/product/${item.slug}`"
        class="line-clamp-2 break-words text-sm font-semibold leading-snug text-ink transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-border-focus"
      >
        {{ item.title }}
      </RouterLink>
      <p v-else class="line-clamp-2 break-words text-sm font-semibold leading-snug text-ink">{{ item.title }}</p>

      <p v-if="showSeller && item.shopName" class="mt-1 inline-flex items-center gap-1.5 text-xs text-muted">
        <Store :size="12" class="shrink-0 text-info" aria-hidden="true" />
        <span class="truncate">{{ item.shopName }}</span>
      </p>

      <div v-if="variantAttributes.length" class="mt-1.5 flex flex-wrap gap-1.5">
        <span
          v-for="attr in variantAttributes"
          :key="`${attr.name}-${attr.value}`"
          class="inline-flex max-w-full items-center rounded-none border border-border-gray bg-surface-muted px-1.5 py-0.5 text-[11px] font-medium text-text-secondary"
        >
          <span class="truncate">{{ attr.name }}: {{ attr.value }}</span>
        </span>
      </div>
      <p v-else-if="fallbackVariantName" class="mt-1 truncate text-xs text-text-secondary">{{ fallbackVariantName }}</p>

      <p v-if="item.available" class="mt-1 text-xs text-muted">
        <span v-if="!compact" class="sr-only">{{ $t('cart.unit_price') }}: </span>{{ formatPrice(item.unitPrice) }}
      </p>

      <p
        v-if="!item.available"
        class="mt-2 border-l-2 border-amber-500 bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-800 dark:text-amber-300"
      >
        {{ $t(`cart.reasons.${item.unavailableReason ?? 'variant_deleted'}`, { count: item.availableQuantity ?? 0 }) }}
      </p>
    </div>

    <button
      type="button"
      class="btn-icon col-start-3 row-start-1 -mr-1.5 -mt-1.5 shrink-0 self-start rounded-none text-muted hover:bg-danger/10 hover:text-danger focus-visible:ring-2 focus-visible:ring-border-focus"
      :class="compact ? 'sm:h-8 sm:w-8' : 'sm:col-start-5 sm:row-start-1 sm:m-0 sm:self-center'"
      :aria-label="$t('cart.remove_item')"
      :disabled="pending"
      :aria-busy="removing"
      @click="removeItem"
    >
      <Loader2 v-if="removing" :size="15" class="animate-spin" aria-hidden="true" />
      <Trash2 v-else :size="15" aria-hidden="true" />
    </button>

    <div
      class="col-span-2 col-start-2 row-start-2 flex flex-wrap items-center justify-between gap-2"
      :class="{ 'sm:contents': !compact }"
    >
      <div :class="{ 'sm:col-start-3 sm:row-start-1 sm:justify-self-center': !compact }">
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
      </div>

      <div :class="compact ? 'ml-auto shrink-0' : 'ml-auto shrink-0 sm:col-start-4 sm:row-start-1 sm:ml-0 sm:text-right'">
        <span v-if="item.available" class="whitespace-nowrap text-sm font-bold text-ink">
          {{ formatPrice(item.unitPrice * item.quantity) }}
        </span>
        <span v-else-if="!compact" class="hidden text-xs text-muted sm:inline">—</span>
      </div>
    </div>
  </div>
</template>
