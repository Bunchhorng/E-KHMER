<script setup lang="ts">
import { AlertCircle, Loader2, RefreshCw } from 'lucide-vue-next'
withDefaults(defineProps<{ loading?: boolean; error?: string }>(), { loading: false, error: '' })
defineEmits<{ retry: [] }>()
</script>
<template>
  <div v-if="loading" role="status" class="card flex min-h-48 flex-col items-center justify-center gap-3 p-8 text-sm text-muted"><Loader2 class="h-6 w-6 animate-spin text-primary" />{{ $t('common.loading') }}</div>
  <div v-else-if="error" role="alert" class="card flex min-h-48 flex-col items-center justify-center gap-3 p-8 text-center"><AlertCircle class="h-7 w-7 text-red-500" /><p class="max-w-md text-sm text-muted">{{ error }}</p><button type="button" class="btn-secondary" @click="$emit('retry')"><RefreshCw class="h-4 w-4" />{{ $t('actions.retry') }}</button></div>
  <slot v-else />
</template>
