<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next'
import { clamp } from '@/utils/format'

const props = withDefaults(
  defineProps<{
    modelValue: number
    min?: number
    max?: number
    disabled?: boolean
    size?: 'sm' | 'md'
  }>(),
  { min: 1, max: 99, disabled: false, size: 'md' }
)

const emit = defineEmits<{ (e: 'update:modelValue', value: number): void }>()

function setValue(value: number): void {
  emit('update:modelValue', clamp(value, props.min, props.max))
}
</script>

<template>
  <div
    class="inline-flex items-center rounded-lg border border-border-gray"
    :class="size === 'sm' ? 'h-9' : 'h-11'"
  >
    <button
      type="button"
      class="flex h-full items-center justify-center text-gray-500 transition-colors hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
      :class="size === 'sm' ? 'min-w-9 px-2' : 'min-w-11 px-3'"
      :disabled="disabled || modelValue <= min"
      aria-label="Decrease quantity"
      @click="setValue(modelValue - 1)"
    >
      <Minus :size="size === 'sm' ? 13 : 15" />
    </button>
    <span
      class="w-8 text-center font-semibold tabular-nums text-ink"
      :class="size === 'sm' ? 'text-xs' : 'text-sm'"
    >
      {{ modelValue }}
    </span>
    <button
      type="button"
      class="flex h-full items-center justify-center text-gray-500 transition-colors hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
      :class="size === 'sm' ? 'min-w-9 px-2' : 'min-w-11 px-3'"
      :disabled="disabled || modelValue >= max"
      aria-label="Increase quantity"
      @click="setValue(modelValue + 1)"
    >
      <Plus :size="size === 'sm' ? 13 : 15" />
    </button>
  </div>
</template>
