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
    class="inline-flex items-stretch rounded-none border border-border-strong bg-surface"
    :class="size === 'sm' ? 'h-[46px] sm:h-[38px]' : 'h-[46px]'"
  >
    <button
      type="button"
      class="flex items-center justify-center rounded-none border-r border-border-gray text-text-secondary transition-colors hover:bg-surface-hover hover:text-primary active:bg-surface-active disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-secondary"
      :class="size === 'sm' ? 'min-h-11 min-w-11 px-2 sm:min-h-9 sm:min-w-9' : 'min-h-11 min-w-11 px-3'"
      :disabled="disabled || modelValue <= min"
      aria-label="Decrease quantity"
      @click="setValue(modelValue - 1)"
    >
      <Minus :size="size === 'sm' ? 14 : 15" aria-hidden="true" />
    </button>
    <span
      class="flex min-w-9 items-center justify-center px-1.5 text-center font-semibold tabular-nums text-ink"
      :class="[size === 'sm' ? 'text-xs' : 'text-sm', { 'opacity-50': disabled }]"
    >
      {{ modelValue }}
    </span>
    <button
      type="button"
      class="flex items-center justify-center rounded-none border-l border-border-gray text-text-secondary transition-colors hover:bg-surface-hover hover:text-primary active:bg-surface-active disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-text-secondary"
      :class="size === 'sm' ? 'min-h-11 min-w-11 px-2 sm:min-h-9 sm:min-w-9' : 'min-h-11 min-w-11 px-3'"
      :disabled="disabled || modelValue >= max"
      aria-label="Increase quantity"
      @click="setValue(modelValue + 1)"
    >
      <Plus :size="size === 'sm' ? 14 : 15" aria-hidden="true" />
    </button>
  </div>
</template>
