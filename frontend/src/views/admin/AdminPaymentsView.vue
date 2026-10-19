<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { X, CreditCard } from 'lucide-vue-next'
import AdminDataTable from '@/components/admin/AdminDataTable.vue'
import type { TableColumn, TableRow } from '@/types'
import { adminApi } from '@/api/admin'
import type { AdminPayment } from '@/api/admin'
import { formatDate, formatPrice } from '@/utils/format'

const { t } = useI18n()
const route = useRoute(); const router = useRouter()
const attentionOnly = computed(() => route.query.attention === '1')
function toggleAttention() { void router.replace({ query: { ...route.query, attention: attentionOnly.value ? undefined : '1' } }) }
function paymentStatus(payment: AdminPayment): string {
  if (['failed', 'refund_pending'].includes(payment.status)) return payment.status
  const orderStatus = payment.order_payment_status
  return orderStatus && ['partial', 'refund_pending'].includes(orderStatus) ? orderStatus : payment.status
}

const loading = ref(true)
const payments = ref<AdminPayment[]>([])
const totalCount = ref(0)
const filter = ref('all')
const detail = ref<AdminPayment | null>(null)
const detailLoading = ref(false)

const statusFilters = ['all', 'pending', 'completed', 'failed', 'partial', 'refund_pending', 'refunded']

const columns = computed<TableColumn[]>(() => [
  { key: 'order_number', label: t('admin.payments.order'), sortable: true },
  { key: 'customer', label: t('admin.payments.customer') },
  { key: 'method', label: t('admin.payments.method'), type: 'badge' },
  { key: 'status', label: t('admin.payments.status'), type: 'status', sortable: true },
  { key: 'amount', label: t('admin.payments.amount'), type: 'currency', sortable: true },
  { key: 'paid_at', label: t('admin.payments.date'), type: 'date', sortable: true },
  { key: 'actions', label: '', type: 'actions' }
])

const rows = computed<TableRow[]>(() =>
  filteredPayments.value.map((p) => ({
    id: p.id,
    order_number: p.order_number ?? '—',
    customer: p.customer_name ?? '—',
    method: p.method ?? '—',
    status: capitalize(paymentStatus(p)),
    amount: p.amount,
    paid_at: p.paid_at ?? p.created_at
  }))
)

const filteredPayments = computed(() => {
  if (filter.value === 'all') return payments.value
  return payments.value.filter((p) => paymentStatus(p) === filter.value)
})

const rowActions = computed(() => [{ label: t('admin.payments.row.details'), value: 'view' }])

function capitalize(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1)
}

const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string) {
  toast.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

async function loadPayments() {
  loading.value = true
  try {
    const { data: resp } = await adminApi.listPayments({ attention: attentionOnly.value || undefined })
    payments.value = resp.data
    totalCount.value = resp.meta.total
  } catch {
    showToast(t('admin.payments.toast.load_error'))
  } finally {
    loading.value = false
  }
}

async function onRowAction(payload: { action: string; row: TableRow }) {
  const id = Number(payload.row.id)
  detailLoading.value = true
  try {
    const { data: resp } = await adminApi.getPayment(id)
    detail.value = resp.data
  } catch {
    showToast(t('admin.payments.toast.load_error'))
  } finally {
    detailLoading.value = false
  }
}

watch(attentionOnly, () => { filter.value = 'all'; void loadPayments() }, { immediate: true })
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-ink">{{ $t('admin.payments.title') }}</h1>
        <span class="chip">{{ totalCount }} {{ $t('admin.payments.total') }}</span>
      </div>
    </div>

    <label class="flex items-center gap-2 text-sm"><input type="checkbox" :checked="attentionOnly" @change="toggleAttention" />{{ t('admin.overview.attention_only') }}</label>
    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="status in statusFilters"
        :key="status"
        type="button"
        :class="filter === status ? 'bg-primary text-white' : 'bg-surface text-gray-600 hover:bg-surface-hover dark:text-muted'"
        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
        @click="filter = status"
      >
        {{ $t(`admin.payments.filter.${status}`) }}
      </button>
    </div>

    <AdminDataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      :search-keys="['order_number', 'customer']"
      :search-placeholder="t('admin.payments.search_placeholder')"
      :page-size="10"
      :row-actions="rowActions"
      @row-action="onRowAction"
    />

    <div v-if="detail" class="card p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="flex items-center gap-2 text-base font-semibold text-ink">
          <CreditCard class="h-5 w-5 text-primary" />
          {{ $t('admin.payments.detail_title') }}
        </h2>
        <button class="btn-icon h-8 w-8" type="button" @click="detail = null">
          <X class="h-4 w-4" />
        </button>
      </div>

      <div v-if="detailLoading" class="text-sm text-gray-500 dark:text-muted">{{ $t('common.loading') }}</div>

      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="detail-item">
          <span class="detail-label">{{ $t('admin.payments.order') }}</span>
          <router-link :to="`/admin/orders/${detail.order_id}`" class="font-semibold text-primary hover:underline">
            {{ detail.order_number ?? '—' }}
          </router-link>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('admin.payments.customer') }}</span>
          <span class="detail-value">{{ detail.customer_name ?? '—' }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('admin.payments.method') }}</span>
          <span class="detail-value">{{ detail.method ?? '—' }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('admin.payments.status') }}</span>
          <span class="detail-value">{{ capitalize(paymentStatus(detail)) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('admin.payments.amount') }}</span>
          <span class="detail-value font-semibold">{{ formatPrice(detail.amount) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('admin.payments.transaction_id') }}</span>
          <span class="detail-value break-all">{{ detail.transaction_id ?? '—' }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('admin.payments.date') }}</span>
          <span class="detail-value">{{ formatDate(detail.paid_at ?? detail.created_at) }}</span>
        </div>
      </div>

      <div v-if="detail?.transactions?.length" class="mt-5">
        <h3 class="mb-3 text-sm font-semibold text-ink">{{ $t('admin.payments.transactions') }}</h3>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[520px] text-sm">
            <thead>
              <tr class="border-y border-border-gray bg-gray-50 dark:bg-surface-hover/40">
                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-muted">{{ $t('admin.payments.tx_type') }}</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-muted">{{ $t('admin.payments.tx_status') }}</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-muted">{{ $t('admin.payments.amount') }}</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-muted">{{ $t('admin.payments.tx_date') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border-gray">
              <tr v-for="tx in detail.transactions" :key="tx.id">
                <td class="px-4 py-2.5">{{ tx.type }}</td>
                <td class="px-4 py-2.5">{{ tx.status }}</td>
                <td class="px-4 py-2.5 text-right font-medium">{{ formatPrice(tx.amount) }}</td>
                <td class="px-4 py-2.5 text-right text-gray-500 dark:text-muted">{{ formatDate(tx.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <transition name="fade">
      <div v-if="toast" class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover">
        {{ toast }}
      </div>
    </transition>
  </div>
</template>

<style scoped>
.detail-item {
  @apply flex flex-col gap-1 rounded-xl bg-canvas p-3;
}
.detail-label {
  @apply text-xs text-gray-500 dark:text-muted;
}
.detail-value {
  @apply text-sm text-ink dark:text-ink;
}
</style>
