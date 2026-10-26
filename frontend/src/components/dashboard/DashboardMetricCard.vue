<script setup lang="ts">
import type { Component } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import { ChevronRight, TrendingDown, TrendingUp } from 'lucide-vue-next'
import type { DashboardTone } from './types'

defineProps<{ label: string; value: string | number; detail: string; delta?: number | null; icon: Component; tone: DashboardTone; to?: RouteLocationRaw }>()
</script>

<template>
  <component :is="to ? RouterLink : 'article'" :to="to" class="card dashboard-metric" :class="{ 'dashboard-metric-link': to }" :data-tone="tone">
    <div class="flex items-start justify-between gap-3">
      <h2 class="dashboard-metric-label text-muted">{{ label }}</h2>
      <span class="dashboard-metric-icon"><component :is="icon" :size="22" :stroke-width="1.8" aria-hidden="true" /></span>
    </div>
    <p class="dashboard-metric-value font-bold tabular-nums tracking-tight text-ink">{{ value }}</p>
    <div v-if="delta !== undefined" class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-muted">
      <span v-if="delta !== null" class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 font-semibold" :class="delta >= 0 ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'"><component :is="delta >= 0 ? TrendingUp : TrendingDown" class="h-3.5 w-3.5" aria-hidden="true" />{{ Math.abs(delta) }}%</span>
      <span>{{ $t(delta === null ? 'admin.dashboard.no_baseline' : 'admin.dashboard.vs_previous_period') }}</span>
    </div>
    <p class="dashboard-metric-detail text-muted">{{ detail }}</p>
    <span v-if="to" class="dashboard-metric-chevron" aria-hidden="true"><ChevronRight :size="21" :stroke-width="1.8" /></span>
  </component>
</template>

<style scoped>
.dashboard-metric { position: relative; display: block; min-width: 0; min-height: 192px; overflow: hidden; padding: 24px; }
.dashboard-metric-link { transition: border-color .15s, box-shadow .15s; }
.dashboard-metric-link:hover { border-color: rgb(var(--color-primary) / 35%); }
[data-tone='blue'] { --tone: 37 99 235; }
[data-tone='violet'] { --tone: 131 41 244; }
[data-tone='amber'] { --tone: 220 112 0; }
[data-tone='green'] { --tone: 0 155 116; }
[data-tone='rose'] { --tone: 221 47 86; }
.dashboard-metric-icon { display: flex; width: 44px; height: 44px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 12px; background: rgb(var(--tone) / 9%); color: rgb(var(--tone)); }
.dashboard-metric-value { margin-top: 16px; font-size: 32px; line-height: 1.2; overflow-wrap: anywhere; }
.dashboard-metric-label { padding-top: 3px; font-size: 15px; line-height: 1.5; }
.dashboard-metric-detail { margin-top: 6px; font-size: 14px; line-height: 1.6; }
.dashboard-metric-link .dashboard-metric-detail { padding-right: 24px; }
.dashboard-metric-chevron { position: absolute; right: 18px; bottom: 22px; color: rgb(var(--color-muted)); }
@media (max-width: 767px) { .dashboard-metric { min-height: 180px; padding: 18px; } .dashboard-metric-icon { width: 36px; height: 36px; border-radius: 10px; } .dashboard-metric-value { font-size: 28px; } .dashboard-metric-label { font-size: 14px; } .dashboard-metric-detail { font-size: 13px; } .dashboard-metric-chevron { right: 12px; bottom: 18px; } }
@media (max-width: 479px) { .dashboard-metric { padding: 16px; } .dashboard-metric-icon { width: 30px; height: 30px; } .dashboard-metric-icon > svg { width: 18px; height: 18px; } }
</style>
