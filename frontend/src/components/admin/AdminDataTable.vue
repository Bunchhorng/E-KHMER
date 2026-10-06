<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ArrowUpDown, ArrowUp, ArrowDown, Search, MoreHorizontal, SearchX } from 'lucide-vue-next'
import StatusTag from '@/components/StatusTag.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import BasePagination from '@/components/BasePagination.vue'
import DataTableSkeleton from '@/components/DataTableSkeleton.vue'
import EmptyState from '@/components/EmptyState.vue'
import type { TableColumn, TableRow } from '@/types'
import { formatPrice, formatDate } from '@/utils/format'

interface Props {
  columns: TableColumn[]
  rows: TableRow[]
  selectable?: boolean
  searchKeys?: string[]
  searchPlaceholder?: string
  pageSize?: number
  loading?: boolean
  page?: number
  bulkActions?: { label: string; value: string }[]
  rowActions?: { label: string; value: string }[]
  emptyTitle?: string
  emptyDescription?: string
  flat?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  selectable: true,
  searchKeys: () => [],
  pageSize: 10,
  loading: false,
  page: 1,
  columns: () => [],
  rows: () => [],
  bulkActions: () => [],
  rowActions: () => [],
  searchPlaceholder: '',
  emptyTitle: '',
  flat: false
})

const emit = defineEmits<{
  'row-action': [payload: { action: string; row: TableRow }]
  'bulk-action': [payload: { action: string; ids: string[] }]
  'update:page': [page: number]
  'sort-change': [payload: { key: string; direction: 'asc' | 'desc' }]
}>()

const search = ref('')
const selectedIds = ref<string[]>([])
const sortKey = ref('')
const sortDir = ref<'asc' | 'desc'>('asc')
const currentPage = ref(props.page)
const bulkAction = ref('')
const menuOpenId = ref<string | null>(null)

watch(
  () => props.page,
  (p) => {
    currentPage.value = p
  }
)

watch(search, () => {
  currentPage.value = 1
  emit('update:page', 1)
})

const keys = computed(() => (props.searchKeys.length ? props.searchKeys : props.columns.map((c) => c.key)))

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  let list = props.rows
  if (term) {
    list = list.filter((row) => keys.value.some((key) => String(row[key] ?? '').toLowerCase().includes(term)))
  }
  if (sortKey.value) {
    const dir = sortDir.value === 'asc' ? 1 : -1
    list = [...list].sort((a, b) => {
      const av = a[sortKey.value]
      const bv = b[sortKey.value]
      if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir
      return String(av).localeCompare(String(bv)) * dir
    })
  }
  return list
})

const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / Math.max(1, props.pageSize))))

watch(pageCount, (count) => {
  if (currentPage.value > count) currentPage.value = count
})

const visible = computed(() =>
  filtered.value.slice((currentPage.value - 1) * props.pageSize, currentPage.value * props.pageSize)
)

const rangeStart = computed(() => (filtered.value.length === 0 ? 0 : (currentPage.value - 1) * props.pageSize + 1))
const rangeEnd = computed(() => Math.min(currentPage.value * props.pageSize, filtered.value.length))

const isAllSelected = computed(() =>
  visible.value.length > 0 && visible.value.every((row) => selectedIds.value.includes(String(row.id)))
)

const indeterminate = computed(() => {
  const selected = visible.value.filter((row) => selectedIds.value.includes(String(row.id))).length
  return selected > 0 && selected < visible.value.length
})

function toggleAll() {
  const ids = visible.value.map((row) => String(row.id))
  if (isAllSelected.value) {
    selectedIds.value = selectedIds.value.filter((id) => !ids.includes(id))
  } else {
    selectedIds.value = Array.from(new Set([...selectedIds.value, ...ids]))
  }
}

function toggleRow(id: string) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((x) => x !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

function toggleSort(col: TableColumn) {
  if (sortKey.value === col.key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = col.key
    sortDir.value = 'asc'
  }
  emit('sort-change', { key: sortKey.value, direction: sortDir.value })
}

function handleBulkApply() {
  if (!bulkAction.value) return
  emit('bulk-action', { action: bulkAction.value, ids: [...selectedIds.value] })
  bulkAction.value = ''
  selectedIds.value = []
}

function openMenu(row: TableRow) {
  menuOpenId.value = menuOpenId.value === String(row.id) ? null : String(row.id)
}

function runRowAction(action: string, row: TableRow) {
  emit('row-action', { action, row })
  menuOpenId.value = null
}

function onPageChange(p: number) {
  currentPage.value = p
  emit('update:page', p)
}

function cellValue(row: TableRow, col: TableColumn): string {
  const v = row[col.key]
  if (v === null || v === undefined) return ''
  return String(v)
}
</script>

<template>
  <div class="card overflow-hidden" :class="{ 'rounded-none': props.flat }">
    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="relative w-full max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="text" class="input h-9 w-full pl-9" :placeholder="props.searchPlaceholder || $t('admin.table.search_placeholder')" />
      </div>
      <div v-if="selectedIds.length && props.bulkActions.length" class="flex items-center gap-2">
        <select v-model="bulkAction" class="select h-9 w-40">
          <option value="" disabled>{{ $t('admin.table.bulk_actions') }}</option>
          <option v-for="a in props.bulkActions" :key="a.value" :value="a.value">{{ a.label }}</option>
        </select>
        <button class="btn-primary btn-sm" @click="handleBulkApply">{{ $t('actions.apply') }}</button>
        <span class="text-xs text-gray-500 dark:text-muted">{{ $t('admin.table.selected_count', { count: selectedIds.length }) }}</span>
      </div>
    </div>

    <DataTableSkeleton v-if="props.loading" :rows="props.pageSize" :columns="props.columns.length" />

    <template v-else-if="filtered.length">
      <div class="scrollbar-none max-h-[520px] overflow-auto">
        <table class="w-full min-w-[640px] text-sm">
          <thead class="sticky top-0 z-10 bg-canvas">
            <tr class="border-b border-border-gray">
              <th v-if="props.selectable" class="w-10 px-3 py-3">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate="indeterminate"
                  class="h-4 w-4 accent-primary"
                  @change="toggleAll()"
                />
              </th>
              <th
                v-for="col in props.columns"
                :key="col.key"
                class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-muted"
                :style="col.width ? { width: col.width } : undefined"
              >
                <button
                  v-if="col.sortable"
                  class="inline-flex items-center gap-1 uppercase tracking-wide transition-colors hover:text-primary"
                  @click="toggleSort(col)"
                >
                  {{ col.label }}
                  <ArrowUp v-if="sortKey === col.key && sortDir === 'asc'" class="h-3.5 w-3.5" />
                  <ArrowDown v-else-if="sortKey === col.key && sortDir === 'desc'" class="h-3.5 w-3.5" />
                  <ArrowUpDown v-else class="h-3.5 w-3.5 text-gray-300 dark:text-gray-500" />
                </button>
                <span v-else>{{ col.label }}</span>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border-gray">
            <tr v-for="row in visible" :key="String(row.id)" class="transition-colors hover:bg-canvas/50">
              <td v-if="props.selectable" class="px-3 py-3">
                <input
                  type="checkbox"
                  :checked="selectedIds.includes(String(row.id))"
                  class="h-4 w-4 accent-primary"
                  @change="toggleRow(String(row.id))"
                />
              </td>
              <template v-for="col in props.columns" :key="col.key">
                <td v-if="col.type === 'image'" class="px-4 py-3 align-middle">
                  <img :src="String(row[col.key])" class="h-10 w-10 rounded-lg object-cover" alt="" />
                </td>
                <td v-else-if="col.type === 'status'" class="px-4 py-3 align-middle">
                  <StatusTag :status="String(row[col.key])" />
                </td>
                <td v-else-if="col.type === 'badge'" class="px-4 py-3 align-middle">
                  <BaseBadge variant="neutral">{{ String(row[col.key]) }}</BaseBadge>
                </td>
                <td v-else-if="col.type === 'currency'" class="px-4 py-3 align-middle font-medium text-ink">
                  {{ formatPrice(Number(row[col.key])) }}
                </td>
                <td v-else-if="col.type === 'number'" class="px-4 py-3 align-middle text-ink">
                  {{ String(row[col.key]) }}
                </td>
                <td v-else-if="col.type === 'date'" class="px-4 py-3 align-middle text-ink opacity-80">
                  {{ formatDate(String(row[col.key])) }}
                </td>
                <td v-else-if="col.type === 'actions'" class="px-4 py-3 align-middle text-right">
                  <div class="relative">
                    <button class="btn-icon h-8 w-8" @click="openMenu(row)">
                      <MoreHorizontal class="h-4 w-4" />
                    </button>
                    <div
                      v-if="menuOpenId === String(row.id) && props.rowActions.length"
                      class="absolute right-0 top-9 z-20 w-44 overflow-hidden rounded-xl border border-border-gray bg-surface py-1 shadow-popover"
                    >
                      <button
                        v-for="a in props.rowActions"
                        :key="a.value"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-ink dark:text-muted dark:hover:bg-surface-hover dark:hover:text-ink"
                        @click="runRowAction(a.value, row)"
                      >
                        {{ a.label }}
                      </button>
                    </div>
                  </div>
                </td>
                <td v-else class="px-4 py-3 align-middle text-ink opacity-90">
                  {{ cellValue(row, col) }}
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between gap-3 border-t border-border-gray p-4">
        <span class="text-xs text-gray-500 dark:text-muted">{{ $t('admin.table.showing_range', { from: rangeStart, to: rangeEnd, total: filtered.length }) }}</span>
        <BasePagination
          :page="currentPage"
          :page-count="pageCount"
          :total-items="filtered.length"
          :page-size="props.pageSize"
          @update:page="onPageChange"
        />
      </div>
    </template>

    <EmptyState v-else :title="props.emptyTitle || $t('admin.table.no_results')" :description="props.emptyDescription" cta-label="">
      <template #icon>
        <SearchX class="h-10 w-10 text-gray-300" />
      </template>
    </EmptyState>
  </div>
</template>
