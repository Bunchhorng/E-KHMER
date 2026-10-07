<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>
<template>
  <header class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
      <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ title }}</h1>
      <p v-if="description" class="mt-2 max-w-2xl text-sm leading-relaxed text-muted">{{ description }}</p>
    </div>
    <div v-if="$slots.default" class="flex flex-wrap items-center gap-2"><slot /></div>
  </header>
</template>
