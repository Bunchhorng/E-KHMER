<script setup lang="ts">
import StarRating from '@/components/StarRating.vue'
defineProps<{ value?: number; name: string }>()
const emit = defineEmits<{ change: [value: number | undefined] }>()
</script>
<template>
  <fieldset class="space-y-2">
    <legend class="label">{{ $t('shop.rating') }}</legend>
    <label v-for="rating in [4.5, 4, 3.5]" :key="rating" class="flex cursor-pointer items-center gap-2 text-sm text-muted">
      <input type="radio" :name="name" :value="rating" :checked="value === rating" class="h-4 w-4 accent-primary" @change="emit('change', rating)" />
      <StarRating :value="rating" size="sm" aria-hidden="true" />
      <span>{{ rating.toFixed(1) }} {{ $t('shop.up') }}</span>
    </label>
    <label class="flex cursor-pointer items-center gap-2 text-sm text-muted">
      <input type="radio" :name="name" :checked="value === undefined" class="h-4 w-4 accent-primary" @change="emit('change', undefined)" />
      <span>{{ $t('shop.any') }}</span>
    </label>
  </fieldset>
</template>
