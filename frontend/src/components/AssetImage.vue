<script setup lang="ts">
import { ref, watch } from 'vue'
import { Package } from 'lucide-vue-next'

const props = defineProps<{ src: string | null | undefined; alt: string }>()
const failed = ref(false)
watch(() => props.src, () => { failed.value = false })
</script>

<template>
  <div class="shrink-0 overflow-hidden">
    <img v-if="src && !failed" :src="src" :alt="alt" class="h-full w-full object-contain" @error="failed = true" />
    <span v-else class="flex h-full w-full items-center justify-center bg-canvas text-gray-400 dark:text-muted" role="img" :aria-label="alt"><Package class="h-5 w-5" aria-hidden="true" /></span>
  </div>
</template>
