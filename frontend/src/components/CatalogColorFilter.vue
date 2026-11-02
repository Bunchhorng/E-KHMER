<script setup lang="ts">
import { computed } from 'vue'
import type { FacetValue } from '@/api/catalog'
import { swatchColor } from '@/utils/color'

interface ColorFilterOption {
  slug: string
  value: string
  name: string
  count: number
  swatch: string
}

const props = defineProps<{ options: FacetValue[]; selected: string[] }>()
const emit = defineEmits<{ toggle: [value: string] }>()
const colors = computed<ColorFilterOption[]>(() => props.options.map(option => ({
  slug: option.slug,
  value: option.value ?? option.name,
  name: option.name,
  count: option.count,
  swatch: swatchColor(option.swatch_color && CSS.supports('color', option.swatch_color) ? option.swatch_color : null, option.name),
})))
</script>

<template>
  <fieldset class="space-y-2" data-color-filter>
    <legend class="label">{{ $t('shop.color') }}</legend>
    <div class="flex flex-wrap gap-2">
      <button
        v-for="color in colors"
        :key="color.value"
        type="button"
        class="inline-flex min-h-9 items-center gap-2 rounded-lg border-2 px-2 py-1 text-xs transition"
        :class="selected.includes(color.value) ? 'border-primary bg-primary/5 text-primary' : 'border-border-gray text-muted hover:border-gray-400'"
        :aria-pressed="selected.includes(color.value)"
        :title="color.name"
        @click="emit('toggle', color.value)"
      >
        <span aria-hidden="true" class="h-5 w-5 shrink-0 rounded-full border border-gray-300" :style="{ backgroundColor: color.swatch }" data-color-swatch></span>
        <span>{{ color.name }}</span>
      </button>
    </div>
  </fieldset>
</template>
