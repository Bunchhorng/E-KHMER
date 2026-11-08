<script setup lang="ts">
import { ref, watch } from 'vue'
import { ImageOff } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  src?: string | null
  alt: string
  fit?: 'cover' | 'contain'
  loading?: 'eager' | 'lazy'
}>(), { src: null, fit: 'cover', loading: 'lazy' })

const failed = ref(false)
watch(() => props.src, () => { failed.value = false })
</script>

<template>
  <div class="shrink-0 overflow-hidden bg-surface-muted" :data-image-state="src && !failed ? 'loaded' : 'fallback'">
    <img
      v-if="src && !failed"
      :src="src"
      :alt="alt"
      :loading="loading"
      decoding="async"
      class="h-full w-full"
      :class="fit === 'cover' ? 'object-cover' : 'object-contain'"
      @error="failed = true"
    />
    <span v-else class="flex h-full w-full items-center justify-center bg-surface-muted text-muted" role="img" :aria-label="alt">
      <ImageOff class="h-5 w-5" aria-hidden="true" />
    </span>
  </div>
</template>
