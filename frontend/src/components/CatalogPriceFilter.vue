<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import type { PriceRange } from '@/api/catalog'
import { formatPrice } from '@/utils/format'

const props = defineProps<{ range: PriceRange | null; min?: number; max?: number; loading?: boolean; idPrefix: string }>()
const emit = defineEmits<{ change: [value: { min?: number; max?: number }]; retry: [] }>()
const minimum = ref('')
const maximum = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
let editing = false
let lastSent: { min?: number; max?: number } | null = null
const available = computed(() => props.range?.min != null && props.range?.max != null)
const error = computed(() => {
  const min = minimum.value === '' ? undefined : Number(minimum.value)
  const max = maximum.value === '' ? undefined : Number(maximum.value)
  if ((min !== undefined && (!Number.isFinite(min) || min < 0)) || (max !== undefined && (!Number.isFinite(max) || max < 0))) return 'Enter a valid price of zero or more.'
  if (min !== undefined && max !== undefined && min > max) return 'Maximum price must be greater than or equal to minimum price.'
  return ''
})
// Explicit URL/input prices may extend beyond the current catalog bounds.
// Never clamp a shared URL to a narrower range returned by other filters.
const sliderMin = computed(() => Math.min(props.range?.min ?? 0, Number(minimum.value) || 0))
const sliderMax = computed(() => Math.max(props.range?.max ?? 0, Number(maximum.value) || 0, Number(minimum.value) || 0))
watch(() => [props.min, props.max, props.range?.min, props.range?.max], () => {
  // An acknowledgement of the previous edit must not overwrite text already
  // being entered for the next edit. External navigation/reset still wins.
  if (editing && lastSent && props.min === lastSent.min && props.max === lastSent.max) return
  clearTimeout(timer)
  editing = false
  minimum.value = String(props.min ?? props.range?.min ?? '')
  maximum.value = String(props.max ?? props.range?.max ?? '')
}, { immediate: true })

function update() {
  clearTimeout(timer)
  editing = true
  if (error.value) return
  timer = setTimeout(commit, 250)
}
function commit() {
  clearTimeout(timer)
  if (!editing || error.value) return
  editing = false
  lastSent = {
    min: minimum.value === '' ? undefined : Number(minimum.value),
    max: maximum.value === '' ? undefined : Number(maximum.value),
  }
  emit('change', lastSent)
}
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <fieldset class="space-y-3" :aria-busy="loading" :data-price-filter="idPrefix">
    <legend class="label">{{ $t('shop.price_range') }}</legend>
    <p v-if="loading" role="status" class="text-xs text-muted">Updating price range…</p>
    <template v-if="available">
      <p class="text-xs text-muted">{{ formatPrice(range!.min!) }} – {{ formatPrice(range!.max!) }}</p>
      <label class="sr-only" :for="`${idPrefix}-min-slider`">Minimum price slider</label>
      <input :id="`${idPrefix}-min-slider`" v-model="minimum" type="range" :min="sliderMin" :max="sliderMax" step="0.01" class="w-full accent-primary" @input="update" @change="commit" />
      <label class="sr-only" :for="`${idPrefix}-max-slider`">Maximum price slider</label>
      <input :id="`${idPrefix}-max-slider`" v-model="maximum" type="range" :min="sliderMin" :max="sliderMax" step="0.01" class="w-full accent-primary" @input="update" @change="commit" />
      <div class="grid grid-cols-2 gap-2">
        <label class="text-xs text-muted" :for="`${idPrefix}-min`">Minimum price
          <input :id="`${idPrefix}-min`" v-model="minimum" type="number" inputmode="decimal" min="0" step="0.01" class="input mt-1 w-full" :aria-invalid="Boolean(error)" :aria-describedby="error ? `${idPrefix}-error` : undefined" @input="update" @change="commit" />
        </label>
        <label class="text-xs text-muted" :for="`${idPrefix}-max`">Maximum price
          <input :id="`${idPrefix}-max`" v-model="maximum" type="number" inputmode="decimal" min="0" step="0.01" class="input mt-1 w-full" :aria-invalid="Boolean(error)" :aria-describedby="error ? `${idPrefix}-error` : undefined" @input="update" @change="commit" />
        </label>
      </div>
      <p v-if="error" :id="`${idPrefix}-error`" role="alert" class="text-xs text-red-600">{{ error }}</p>
    </template>
    <p v-else-if="!loading && range" class="text-sm text-muted">No purchasable prices match these filters.</p>
    <p v-else-if="!loading" role="alert" class="text-sm text-red-600">Price range unavailable. <button type="button" class="underline" @click="emit('retry')">Retry</button></p>
  </fieldset>
</template>
