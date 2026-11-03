<script setup lang="ts">
import AppImage from '@/components/AppImage.vue'
defineProps<{ src: string | null | undefined; alt: string }>()
</script>

<template>
  <AppImage :src="src" :alt="alt" fit="contain" />
</template>
