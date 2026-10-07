<script setup lang="ts">
import { PackageSearch, RefreshCw } from 'lucide-vue-next'
defineProps<{ loading: boolean; error?: string; empty?: boolean; title?: string; description?: string }>()
defineEmits<{ retry: [] }>()
</script>
<template>
  <div v-if="loading" role="status" class="space-y-3 p-6" aria-busy="true"><div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-xl bg-border-gray/30"></div><span class="sr-only">{{ $t('common.loading') }}</span></div>
  <div v-else-if="error" role="alert" class="p-8 text-center"><p class="text-sm text-red-600 dark:text-red-400">{{ error }}</p><button class="btn-secondary mt-4" @click="$emit('retry')"><RefreshCw :size="16" />{{ $t('actions.retry') }}</button></div>
  <div v-else-if="empty" class="px-6 py-14 text-center"><PackageSearch :size="32" class="mx-auto mb-4 text-muted" /><h2 class="font-semibold">{{ title }}</h2><p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">{{ description }}</p><div v-if="$slots.default" class="mt-6"><slot /></div></div>
</template>
