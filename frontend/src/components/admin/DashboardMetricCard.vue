<script setup lang="ts">
import type { Component } from 'vue'
import { TrendingDown, TrendingUp } from 'lucide-vue-next'

defineProps<{ label: string; value: string; detail: string; delta?: number | null; icon: Component; tone: string }>()
</script>

<template>
  <article class="card min-w-0 p-5">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-sm font-medium text-gray-500 dark:text-muted">{{ label }}</p>
        <p class="mt-2 break-words text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ value }}</p>
      </div>
      <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="tone"><component :is="icon" class="h-5 w-5" aria-hidden="true" /></span>
    </div>
    <div v-if="delta !== undefined" class="mt-3 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs">
      <span v-if="delta !== null" class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-semibold" :class="delta >= 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400'"><component :is="delta >= 0 ? TrendingUp : TrendingDown" class="h-3.5 w-3.5" aria-hidden="true" />{{ Math.abs(delta) }}%</span>
      <span class="text-gray-500 dark:text-muted">{{ $t(delta === null ? 'admin.dashboard.no_baseline' : 'admin.dashboard.vs_previous_period') }}</span>
    </div>
    <p class="mt-3 text-xs leading-relaxed text-gray-500 dark:text-muted">{{ detail }}</p>
  </article>
</template>
