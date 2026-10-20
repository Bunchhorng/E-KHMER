<script setup lang="ts">
import { computed } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { clamp } from '@/utils/format'

type PageItem = number | 'left-ellipsis' | 'right-ellipsis'

const props = withDefaults(
  defineProps<{
    page: number
    pageCount: number
    pageSize?: number
    totalItems?: number
    siblings?: number
  }>(),
  { pageSize: 10, siblings: 1 }
)

const emit = defineEmits<{ (e: 'update:page', page: number): void }>()

function selectPage(page: number): void {
  emit('update:page', clamp(page, 1, props.pageCount))
}

function pageItems(): PageItem[] {
  const total = props.pageCount
  if (total <= 1) return [1]
  const current = props.page
  const siblings = Math.max(0, props.siblings)
  const start = Math.max(2, current - siblings)
  const end = Math.min(total - 1, current + siblings)
  const items: PageItem[] = [1]
  if (start > 2) items.push('left-ellipsis')
  for (let i = start; i <= end; i++) items.push(i)
  if (end < total - 1) items.push('right-ellipsis')
  items.push(total)
  return items
}

const rangeStart = computed(() => {
  if (!props.totalItems) return 0
  if (!props.pageSize) return 1
  return Math.min((props.page - 1) * props.pageSize + 1, props.totalItems)
})

const rangeEnd = computed(() => {
  if (!props.totalItems) return 0
  if (!props.pageSize) return props.totalItems
  return Math.min(props.page * props.pageSize, props.totalItems)
})
</script>

<template>
  <div v-if="pageCount > 1" class="flex flex-wrap items-center justify-between gap-4">
    <p v-if="totalItems" class="text-sm text-muted">
      {{ $t('pagination.showing', { from: rangeStart, to: rangeEnd, total: totalItems }) }}
    </p>
    <div class="flex items-center gap-1">
      <button
        type="button"
        class="flex h-10 w-10 items-center justify-center rounded-lg text-muted transition-colors hover:bg-canvas hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="page <= 1"
        :aria-label="$t('pagination.previous')"
        @click="selectPage(page - 1)"
      >
        <ChevronLeft :size="18" />
      </button>
      <button
        v-for="item in pageItems()"
        :key="item"
        type="button"
        class="h-10 w-10 rounded-lg text-sm font-medium transition-colors disabled:cursor-default"
        :class="item === page ? 'bg-primary text-white' : 'text-muted hover:bg-canvas hover:text-primary'"
        :disabled="typeof item !== 'number'"
        :aria-current="item === page ? 'page' : undefined"
        @click="typeof item === 'number' && selectPage(item)"
      >
        {{ typeof item === 'number' ? item : '…' }}
      </button>
      <button
        type="button"
        class="flex h-10 w-10 items-center justify-center rounded-lg text-muted transition-colors hover:bg-canvas hover:text-primary disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="page >= pageCount"
        :aria-label="$t('pagination.next')"
        @click="selectPage(page + 1)"
      >
        <ChevronRight :size="18" />
      </button>
    </div>
  </div>
</template>
