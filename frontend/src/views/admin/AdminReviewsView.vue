<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AdminDataTable from '@/components/admin/AdminDataTable.vue'
import type { TableColumn, TableRow } from '@/types'
import { adminApi } from '@/api/admin'
import type { AdminReview } from '@/api/admin'

const { t } = useI18n()

type Tab = 'pending' | 'approved' | 'rejected'

const loading = ref(true)
const activeTab = ref<Tab>('pending')
const pending = ref<AdminReview[]>([])
const approved = ref<AdminReview[]>([])
const rejected = ref<AdminReview[]>([])
const busyIds = new Set<number>()
let bulkBusy = false

const columns = computed<TableColumn[]>(() => [
  { key: 'author', label: t('admin.reviews.customer') },
  { key: 'product', label: t('admin.reviews.product') },
  { key: 'rating', label: t('admin.reviews.rating'), type: 'number' },
  { key: 'title', label: t('admin.reviews.review') },
  { key: 'status', label: t('order.status'), type: 'status' },
  { key: 'date', label: t('order.date'), type: 'date' },
  { key: 'actions', label: '', type: 'actions' }
])

const bulkActions = computed(() =>
  activeTab.value === 'pending'
    ? [{ label: t('admin.reviews.bulk.approve_selected'), value: 'approve' }, { label: t('actions.delete'), value: 'delete' }]
    : []
)

const rowActions = computed(() =>
  activeTab.value === 'pending'
    ? [{ label: t('status.approved'), value: 'approve' }, { label: t('admin.reviews.row.reject'), value: 'reject' }, { label: t('actions.delete'), value: 'delete' }]
    : activeTab.value === 'approved'
      ? [{ label: t('admin.reviews.row.reject'), value: 'reject' }, { label: t('actions.delete'), value: 'delete' }]
      : [{ label: t('status.approved'), value: 'approve' }, { label: t('actions.delete'), value: 'delete' }]
)

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1)
}

function toRow(r: AdminReview): TableRow {
  return {
    id: r.id,
    author: r.user.name,
    product: r.product?.name ?? t('admin.reviews.unknown_product'),
    rating: r.rating,
    title: r.title ?? '',
    status: capitalize(r.status),
    date: r.created_at
  }
}

const activeRows = computed<TableRow[]>(() =>
  ({ pending: pending.value, approved: approved.value, rejected: rejected.value })[activeTab.value].map(toRow)
)

const pendingCount = computed(() => pending.value.length)

const toast = ref('')
const toastError = ref(false)
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string, error = false) {
  toast.value = msg
  toastError.value = error
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

async function loadReviews(status?: string) {
  loading.value = true
  try {
    const params = status ? { status } : {}
    const { data: resp } = await adminApi.listReviews(params)
    return resp.data
  } catch {
    showToast(t('admin.reviews.toast.load_error'), true)
    return []
  } finally {
    loading.value = false
  }
}

async function loadAllReviews() {
  const [pendingData, approvedData, rejectedData] = await Promise.all([
    loadReviews('pending'),
    loadReviews('approved'),
    loadReviews('rejected')
  ])
  pending.value = pendingData
  approved.value = approvedData
  rejected.value = rejectedData
}

function removeFromLists(id: number) {
  pending.value = pending.value.filter(r => r.id !== id)
  approved.value = approved.value.filter(r => r.id !== id)
  rejected.value = rejected.value.filter(r => r.id !== id)
}

async function approveReview(id: number, notify = true): Promise<boolean> {
  if (busyIds.has(id)) return false
  busyIds.add(id)
  try {
    const { data: resp } = await adminApi.approveReview(id)
    const review = resp.data
    removeFromLists(id)
    approved.value.unshift(review)
    if (notify) showToast(t('admin.reviews.toast.approved_review', { author: review.user.name }))
    return true
  } catch {
    if (notify) showToast(t('admin.reviews.toast.approve_error'), true)
    return false
  } finally {
    busyIds.delete(id)
  }
}

async function rejectReview(id: number) {
  if (busyIds.has(id)) return
  busyIds.add(id)
  try {
    const { data: resp } = await adminApi.rejectReview(id)
    removeFromLists(id)
    rejected.value.unshift(resp.data)
    showToast(t('admin.reviews.toast.rejected_review', { author: resp.data.user.name }))
  } catch {
    showToast(t('admin.reviews.toast.reject_error'), true)
  } finally {
    busyIds.delete(id)
  }
}

async function deleteReview(id: number, notify = true): Promise<boolean> {
  if (busyIds.has(id)) return false
  busyIds.add(id)
  try {
    await adminApi.deleteReview(id)
    removeFromLists(id)
    if (notify) showToast(t('admin.reviews.toast.deleted_review'))
    return true
  } catch {
    if (notify) showToast(t('admin.reviews.toast.delete_error'), true)
    return false
  } finally {
    busyIds.delete(id)
  }
}

function onRowAction(payload: { action: string; row: TableRow }) {
  const id = Number(payload.row.id)
  if (payload.action === 'approve') approveReview(id)
  else if (payload.action === 'reject') rejectReview(id)
  else if (payload.action === 'delete') deleteReview(id)
}

async function onBulkAction(payload: { action: string; ids: string[] }) {
  if (bulkBusy || !['approve', 'delete'].includes(payload.action)) return
  bulkBusy = true
  try {
    const action = payload.action === 'approve' ? approveReview : deleteReview
    const results = await Promise.all(payload.ids.map(id => action(Number(id), false)))
    if (results.every(Boolean)) {
      showToast(t(`admin.reviews.toast.${payload.action === 'approve' ? 'approved_count' : 'deleted_count'}`, { count: results.length }))
    } else {
      showToast(t(`admin.reviews.toast.${payload.action === 'approve' ? 'approve_error' : 'delete_error'}`), true)
    }
  } finally {
    bulkBusy = false
  }
}

onMounted(loadAllReviews)
onUnmounted(() => { if (toastTimer) clearTimeout(toastTimer) })
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-ink">{{ $t('admin.reviews.title') }}</h1>
      <p class="mt-1 text-sm text-gray-500">
        {{ t('admin.reviews.awaiting_moderation', pendingCount) }}
      </p>
    </div>

    <div class="card flex w-fit items-center gap-1 p-1">
      <button
        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
        :class="activeTab === 'pending' ? 'bg-primary text-white' : 'text-gray-600 hover:text-ink'"
        @click="activeTab = 'pending'"
      >
        {{ $t('status.pending') }} ({{ pendingCount }})
      </button>
      <button
        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
        :class="activeTab === 'rejected' ? 'bg-primary text-white' : 'text-gray-600 hover:text-ink'"
        @click="activeTab = 'rejected'"
      >
        {{ $t('status.rejected') }} ({{ rejected.length }})
      </button>
      <button
        class="rounded-lg px-4 py-2 text-sm font-semibold transition"
        :class="activeTab === 'approved' ? 'bg-primary text-white' : 'text-gray-600 hover:text-ink'"
        @click="activeTab = 'approved'"
      >
        {{ $t('status.approved') }} ({{ approved.length }})
      </button>
    </div>

    <AdminDataTable
      :columns="columns"
      :rows="activeRows"
      :loading="loading"
      :search-keys="['author', 'product', 'title']"
      :search-placeholder="t('admin.reviews.search_placeholder')"
      :page-size="8"
      :bulk-actions="bulkActions"
      :row-actions="rowActions"
      @row-action="onRowAction"
      @bulk-action="onBulkAction"
    />

    <transition name="fade">
      <div
        v-if="toast"
        :role="toastError ? 'alert' : 'status'"
        class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover"
      >
        {{ toast }}
      </div>
    </transition>
  </div>
</template>
