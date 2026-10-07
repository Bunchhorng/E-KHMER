<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ArrowUpRight } from 'lucide-vue-next'
import type { RouteLocationRaw } from 'vue-router'

defineProps<{ title: string; description?: string; to?: RouteLocationRaw }>()
</script>

<template>
  <article class="card min-w-0 overflow-hidden">
    <header class="flex items-start justify-between gap-3 border-b border-border-gray px-5 py-4">
      <div class="min-w-0">
        <h2 class="text-sm font-semibold text-ink sm:text-base">{{ title }}</h2>
        <p v-if="description" class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-muted">{{ description }}</p>
      </div>
      <RouterLink v-if="to" :to="to" class="inline-flex shrink-0 items-center gap-1 py-1 text-xs font-semibold text-primary hover:underline">
        {{ $t('actions.view_all') }}<ArrowUpRight class="h-3.5 w-3.5" aria-hidden="true" />
      </RouterLink>
      <slot name="actions" />
    </header>
    <slot />
  </article>
</template>
