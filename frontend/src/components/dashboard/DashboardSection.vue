<script setup lang="ts">
import type { Component } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'
import type { DashboardTone } from './types'

withDefaults(defineProps<{ title: string; description?: string; to?: RouteLocationRaw; icon?: Component; tone?: DashboardTone; headingId?: string }>(), { tone: 'blue' })
</script>

<template>
  <article class="card dashboard-section min-w-0 overflow-hidden" :aria-labelledby="headingId">
    <header class="dashboard-section-header flex items-center justify-between gap-3 border-b border-border-gray">
      <div class="flex min-w-0 items-center gap-4">
        <span v-if="icon" class="dashboard-section-icon" :data-tone="tone"><component :is="icon" :size="25" :stroke-width="1.8" aria-hidden="true" /></span>
        <div class="min-w-0">
          <h2 :id="headingId" class="font-semibold text-ink">{{ title }}</h2>
          <p v-if="description" class="mt-1 text-sm leading-relaxed text-muted">{{ description }}</p>
        </div>
      </div>
      <RouterLink v-if="to" :to="to" class="inline-flex shrink-0 items-center gap-2 py-1 text-sm font-medium text-primary hover:underline">
        {{ $t('actions.view_all') }}<ArrowRight class="h-4 w-4" aria-hidden="true" />
      </RouterLink>
      <slot name="actions" />
    </header>
    <slot />
  </article>
</template>

<style scoped>
.dashboard-section-header { min-height: 72px; padding: 18px 24px; background: rgb(var(--color-canvas) / 60%); }
h2 { font-size: 18px; line-height: 1.4; letter-spacing: -.3px; }
.dashboard-section-icon { display: flex; width: 44px; height: 44px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 12px; color: rgb(var(--tone)); background: rgb(var(--tone) / 9%); }
[data-tone='blue'] { --tone: 30 99 255; }
[data-tone='violet'] { --tone: 131 41 244; }
[data-tone='amber'] { --tone: 220 112 0; }
[data-tone='green'] { --tone: 0 155 116; }
[data-tone='rose'] { --tone: 221 47 86; }
@media (max-width: 639px) { .dashboard-section-header { flex-wrap: wrap; padding: 16px; } h2 { font-size: 17px; } .dashboard-section-icon { width: 42px; height: 42px; border-radius: 12px; } }
</style>
