<script setup lang="ts">
import { PackageOpen } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    title: string
    description?: string
    ctaLabel?: string
    compact?: boolean
  }>(),
  { description: '', ctaLabel: '', compact: false }
)

const emit = defineEmits<{ (e: 'cta'): void }>()
</script>

<template>
  <div
    class="rounded-none border-2 border-dashed border-border-gray bg-surface text-center dark:bg-canvas"
    :class="compact ? 'px-5 py-9' : 'px-6 py-14'"
  >
    <div
      class="mx-auto flex items-center justify-center rounded-none border border-border-gray bg-canvas text-primary dark:bg-surface"
      :class="compact ? 'h-12 w-12' : 'h-16 w-16'"
    >
      <slot name="icon">
        <PackageOpen :size="compact ? 22 : 28" />
      </slot>
    </div>
    <h3 class="mt-4 font-semibold text-ink" :class="compact ? 'text-base' : 'text-lg'">{{ title }}</h3>
    <p v-if="description" class="mt-1 text-sm text-text-secondary">{{ description }}</p>
    <button
      v-if="ctaLabel"
      type="button"
      class="btn-primary mt-5 rounded-none"
      :class="compact ? 'min-h-11 px-5 text-sm' : 'btn-sm'"
      @click="emit('cta')"
    >
      {{ ctaLabel }}
    </button>
    <div v-if="$slots.actions" class="mt-5">
      <slot name="actions" />
    </div>
  </div>
</template>