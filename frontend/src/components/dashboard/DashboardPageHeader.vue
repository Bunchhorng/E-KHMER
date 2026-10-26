<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <header class="dashboard-page-heading flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
      <h1 class="font-bold tracking-tight text-ink">{{ title }}</h1>
      <p v-if="description" class="mt-2 max-w-3xl text-muted">{{ description }}</p>
      <slot name="meta" />
    </div>
    <div v-if="$slots.default" class="dashboard-page-actions flex flex-wrap items-center gap-3"><slot /></div>
  </header>
</template>

<style scoped>
.dashboard-page-heading { min-height: 80px; }
h1 { font-size: 32px; line-height: 1.25; letter-spacing: -.8px; }
p { font-size: 16px; line-height: 1.6; }
.dashboard-page-actions :deep(.btn-primary), .dashboard-page-actions :deep(.btn-secondary) { min-height: 44px; border-radius: 8px; padding-inline: 20px; font-size: 14px; }
@media (max-width: 639px) { .dashboard-page-heading { min-height: auto; } h1 { font-size: 30px; letter-spacing: -.8px; } p { font-size: 14px; } .dashboard-page-actions :deep(.btn-primary), .dashboard-page-actions :deep(.btn-secondary) { min-height: 42px; padding-inline: 14px; font-size: 14px; } }
</style>
