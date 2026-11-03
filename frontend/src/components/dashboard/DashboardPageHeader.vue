<script setup lang="ts">
import AppPageHeader from '@/components/AppPageHeader.vue'
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <AppPageHeader class="dashboard-page-heading" :title="title" :description="description">
    <template v-if="$slots.meta" #meta><slot name="meta" /></template>
    <slot />
  </AppPageHeader>
</template>

<style scoped>
.dashboard-page-heading { min-height: 80px; }
@media (max-width: 639px) { .dashboard-page-heading { min-height: auto; } }
</style>
