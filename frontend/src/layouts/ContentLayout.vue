<script setup lang="ts">
withDefaults(defineProps<{ as?: 'div' | 'main' }>(), { as: 'div' })
</script>

<template>
  <component :is="as" class="container-app min-w-0 pb-[env(safe-area-inset-bottom)]">
    <slot />
  </component>
</template>
