<script setup lang="ts">
import DashboardPageHeader from '@/components/dashboard/DashboardPageHeader.vue'
defineProps<{ title: string; description?: string }>()
</script>
<template>
  <DashboardPageHeader :title="title" :description="description">
    <template v-if="$slots.default" #default><slot /></template>
  </DashboardPageHeader>
</template>
