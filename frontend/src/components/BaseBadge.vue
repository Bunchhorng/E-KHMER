<script setup lang="ts">
type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

const props = withDefaults(
  defineProps<{
    variant?: BadgeVariant
    size?: 'sm' | 'md'
    dot?: boolean
    square?: boolean
  }>(),
  { variant: 'neutral', size: 'sm', dot: false, square: false }
)

const variantClasses: Record<BadgeVariant, string> = {
  success:
    'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-success/15 dark:text-success dark:ring-success/30',
  warning:
    'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
  danger:
    'bg-red-50 text-red-600 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/30',
  info: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30',
  neutral:
    'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-surface dark:text-muted dark:ring-border-gray'
}

const dotClasses: Record<BadgeVariant, string> = {
  success: 'bg-success',
  warning: 'bg-amber-500',
  danger: 'bg-red-500',
  info: 'bg-blue-500',
  neutral: 'bg-gray-400'
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-full font-medium ring-1 ring-inset"
    :class="[
      variantClasses[props.variant],
      { '!rounded-none': props.square },
      props.size === 'sm' ? 'px-2.5 py-0.5 text-xs' : 'px-3 py-1 text-sm'
    ]"
  >
    <span
      v-if="props.dot"
      class="rounded-full"
      :class="[dotClasses[props.variant], { '!rounded-none': props.square }, props.size === 'sm' ? 'h-1.5 w-1.5' : 'h-2 w-2']"
    ></span>
    <slot />
  </span>
</template>
