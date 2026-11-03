<script setup lang="ts">
/**
 * The shared page header for storefront account and operations pages.
 * It deliberately provides structure, not a product-specific visual, so each
 * area can keep its context while preserving alignment and hierarchy.
 */
withDefaults(defineProps<{
  title: string
  description?: string
  eyebrow?: string
}>(), { description: '', eyebrow: '' })
</script>

<template>
  <header class="page-header">
    <div class="page-header-copy">
      <p v-if="eyebrow" class="section-eyebrow">{{ eyebrow }}</p>
      <h1 class="page-title" :class="{ 'mt-2': eyebrow }">{{ title }}</h1>
      <p v-if="description" class="page-description">{{ description }}</p>
      <slot name="meta" />
    </div>
    <div v-if="$slots.default" class="page-actions"><slot /></div>
  </header>
</template>
