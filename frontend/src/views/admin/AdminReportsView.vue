<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { FileText, FileSpreadsheet, RefreshCw, DollarSign, ShoppingCart, Users, Package, TrendingUp, XCircle, AlertTriangle, Landmark } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import type { AdminDashboard } from '@/api/admin'
import SalesCategoryChart from '@/components/admin/charts/SalesCategoryChart.vue'
import { downloadResponse } from '@/utils/download'
import { formatCompactNumber, formatPrice } from '@/utils/format'

const { t } = useI18n()

const status = ref('all')
const from = ref('')
const to = ref('')
const downloading = ref('')
const loadingSummary = ref(true)
const summary = ref<{
  revenue: number
  items_revenue: number
  refunded: number
  orders_count: number
  customers_count: number
  units_sold: number
  avg_order_value: number
  payment_methods: { method: string; count: number; amount: number }[]
  low_stock_count: number
  sales_by_category: AdminDashboard['sales_by_category']
  top_selling_products: AdminDashboard['top_selling_products']
} | null>(null)

const categorySales = computed(() => (summary.value?.sales_by_category ?? [])
  .filter(item => item.revenue > 0).map(item => ({ category: item.name, sales: item.revenue })))

const statusOptions = [
  { value: 'all', labelKey: 'admin.reports.all_statuses' },
  { value: 'pending', labelKey: 'status.pending' },
  { value: 'confirmed', labelKey: 'status.confirmed' },
  { value: 'processing', labelKey: 'status.processing' },
  { value: 'shipped', labelKey: 'status.shipped' },
  { value: 'delivered', labelKey: 'status.delivered' },
  { value: 'cancelled', labelKey: 'status.cancelled' },
  { value: 'refunded', labelKey: 'status.refunded' }
]

const cards = computed(() => {
  const s = summary.value
  return [
    { key: 'revenue', label: t('admin.reports.revenue'), value: s ? formatPrice(s.revenue) : '—', icon: DollarSign },
    { key: 'orders', label: t('admin.reports.orders_count'), value: s ? String(s.orders_count) : '—', icon: ShoppingCart },
    { key: 'customers', label: t('admin.reports.customers_count'), value: s ? String(s.customers_count) : '—', icon: Users },
    { key: 'units', label: t('admin.reports.units_sold'), value: s ? String(s.units_sold) : '—', icon: Package },
    { key: 'avg', label: t('admin.reports.avg_order_value'), value: s ? formatPrice(s.avg_order_value) : '—', icon: TrendingUp },
    { key: 'items', label: t('admin.reports.items_revenue'), value: s ? formatPrice(s.items_revenue) : '—', icon: FileSpreadsheet },
    { key: 'refunded', label: t('admin.reports.refunded'), value: s ? formatPrice(s.refunded) : '—', icon: XCircle },
    { key: 'low_stock', label: t('admin.reports.low_stock'), value: s ? String(s.low_stock_count) : '—', icon: AlertTriangle }
  ]
})

const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string) {
  toast.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

async function loadSummary() {
  loadingSummary.value = true
  summary.value = null
  try {
    const { data: resp } = await adminApi.getReportsSummary({
      from: from.value || undefined,
      to: to.value || undefined
    })
    summary.value = resp.data
  } catch {
    showToast(t('admin.reports.toast_error'))
  } finally {
    loadingSummary.value = false
  }
}

async function exportFile(type: 'orders' | 'products' | 'payments', format: 'csv' | 'pdf') {
  if (downloading.value) return
  downloading.value = `${type}-${format}`
  try {
    let response: Awaited<ReturnType<typeof adminApi.getOrdersCsv>> | undefined
    if (type === 'orders') {
      response =
        format === 'csv'
          ? await adminApi.getOrdersCsv(status.value, from.value || undefined, to.value || undefined)
          : await adminApi.getOrdersPdf(status.value, from.value || undefined, to.value || undefined)
    } else if (type === 'products') {
      response = await adminApi.getProductsCsv(from.value || undefined, to.value || undefined)
    } else {
      response = await adminApi.getPaymentsCsv(status.value, from.value || undefined, to.value || undefined)
    }
    downloadResponse(response, `${type}-${new Date().toISOString().slice(0, 10)}.${format === 'pdf' ? 'pdf' : 'csv'}`)
    showToast(t('admin.reports.toast_exported', { type: t(`admin.reports.type_${type}`) }))
  } catch {
    showToast(t('admin.reports.toast_error'))
  } finally {
    downloading.value = ''
  }
}

onMounted(() => loadSummary())
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-ink">{{ $t('admin.reports.title') }}</h1>
      <p class="mt-0.5 text-sm text-gray-500">{{ $t('admin.reports.subtitle') }}</p>
    </div>

    <div class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-semibold text-ink">{{ $t('admin.reports.filters') }}</h2>
        <button type="button" class="btn-outline btn-sm" :disabled="loadingSummary" @click="loadSummary">
          <RefreshCw class="h-4 w-4" />
          {{ $t('actions.refresh') }}
        </button>
      </div>

      <div class="mt-5 grid gap-4 sm:grid-cols-4">
        <div>
          <label class="label" for="report-status">{{ $t('admin.reports.order_status') }}</label>
          <select id="report-status" v-model="status" class="input">
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ $t(opt.labelKey) }}
            </option>
          </select>
        </div>
        <div>
          <label class="label" for="report-from">{{ $t('admin.reports.from') }}</label>
          <input id="report-from" v-model="from" type="date" class="input" />
        </div>
        <div>
          <label class="label" for="report-to">{{ $t('admin.reports.to') }}</label>
          <input id="report-to" v-model="to" type="date" class="input" />
        </div>
        <div class="flex items-end">
          <button type="button" class="btn-primary w-full" @click="loadSummary">
            <RefreshCw class="h-4 w-4" />
            {{ $t('actions.apply') }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="loadingSummary" class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="i in 8" :key="i" class="card animate-pulse p-5">
        <div class="h-3 w-2/3 rounded bg-gray-200 dark:bg-surface-hover"></div>
        <div class="mt-4 h-7 w-1/2 rounded bg-gray-200 dark:bg-surface-hover"></div>
      </div>
    </div>

    <div v-else-if="summary" class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="card in cards" :key="card.key" class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-sm text-gray-500 dark:text-muted">{{ card.label }}</div>
            <div class="mt-2 truncate text-xl font-extrabold text-ink dark:text-ink">{{ card.value }}</div>
          </div>
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <component :is="card.icon" class="h-5 w-5" />
          </div>
        </div>
      </div>
    </div>

    <div class="card p-6">
      <h2 class="text-base font-semibold text-ink">{{ $t('admin.reports.exports') }}</h2>

      <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="rounded-xl border border-border-gray p-5">
          <h3 class="text-sm font-semibold text-ink">{{ $t('admin.reports.orders_report') }}</h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ $t('admin.reports.orders_description') }}</p>
          <div class="mt-4 flex flex-wrap gap-2">
            <button
              type="button"
              class="btn-primary btn-sm"
              :disabled="downloading === 'orders-pdf'"
              @click="exportFile('orders', 'pdf')"
            >
              <FileText v-if="downloading !== 'orders-pdf'" class="h-4 w-4" />
              {{ downloading === 'orders-pdf' ? $t('admin.reports.downloading') : $t('admin.reports.download_pdf') }}
            </button>
            <button
              type="button"
              class="btn-secondary btn-sm"
              :disabled="downloading === 'orders-csv'"
              @click="exportFile('orders', 'csv')"
            >
              <FileSpreadsheet v-if="downloading !== 'orders-csv'" class="h-4 w-4" />
              {{ downloading === 'orders-csv' ? $t('admin.reports.downloading') : $t('admin.reports.download_csv') }}
            </button>
          </div>
        </div>

        <div class="rounded-xl border border-border-gray p-5">
          <h3 class="text-sm font-semibold text-ink">{{ $t('admin.reports.products_report') }}</h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ $t('admin.reports.products_description') }}</p>
          <div class="mt-4">
            <button
              type="button"
              class="btn-secondary btn-sm"
              :disabled="downloading === 'products-csv'"
              @click="exportFile('products', 'csv')"
            >
              <FileSpreadsheet v-if="downloading !== 'products-csv'" class="h-4 w-4" />
              {{ downloading === 'products-csv' ? $t('admin.reports.downloading') : $t('admin.reports.download_csv') }}
            </button>
          </div>
        </div>

        <div class="rounded-xl border border-border-gray p-5">
          <h3 class="text-sm font-semibold text-ink">{{ $t('admin.reports.payments_report') }}</h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ $t('admin.reports.payments_description') }}</p>
          <div class="mt-4">
            <button
              type="button"
              class="btn-secondary btn-sm"
              :disabled="downloading === 'payments-csv'"
              @click="exportFile('payments', 'csv')"
            >
              <FileSpreadsheet v-if="downloading !== 'payments-csv'" class="h-4 w-4" />
              {{ downloading === 'payments-csv' ? $t('admin.reports.downloading') : $t('admin.reports.download_csv') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="summary?.payment_methods.length" class="card p-6">
      <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-ink">
        <Landmark class="h-5 w-5 text-primary" />
        {{ $t('admin.reports.payment_methods') }}
      </h2>
      <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <li v-for="pm in summary.payment_methods" :key="pm.method" class="rounded-xl bg-canvas p-4">
          <div class="text-sm font-semibold capitalize text-ink dark:text-ink">{{ pm.method }} ({{ formatCompactNumber(pm.count) }})</div>
          <div class="mt-1 text-lg font-extrabold text-primary">{{ formatPrice(pm.amount) }}</div>
        </li>
      </ul>
    </div>

    <section v-if="summary && !loadingSummary" class="space-y-4">
      <div><h2 class="text-lg font-semibold text-ink">{{ t('admin.overview.sales_analysis') }}</h2><p class="mt-1 text-xs text-muted">{{ t('admin.overview.sales_analysis_description') }}</p></div>
      <div class="grid gap-5 xl:grid-cols-2">
        <div class="card p-5"><h3 class="mb-4 text-sm font-semibold">{{ t('admin.chart.sales_by_category') }}</h3><SalesCategoryChart v-if="categorySales.length" :data="categorySales" /><p v-else class="py-10 text-center text-sm text-muted">{{ t('admin.dashboard.no_sales') }}</p></div>
        <div class="card p-5"><h3 class="mb-4 text-sm font-semibold">{{ t('admin.dashboard.top_selling') }}</h3><div v-if="summary.top_selling_products?.length" class="divide-y divide-border-gray"><div v-for="product in summary.top_selling_products" :key="product.product_id" class="flex items-center justify-between gap-3 py-4"><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ product.product_name }}</p><p class="mt-1 text-xs text-muted">{{ t('admin.dashboard.sold_count', { count: product.total_qty }) }}</p></div><span class="shrink-0 text-sm font-semibold">{{ formatPrice(product.revenue) }}</span></div></div><p v-else class="py-10 text-center text-sm text-muted">{{ t('admin.dashboard.no_sales') }}</p></div>
      </div>
    </section>

    <transition name="fade">
      <div v-if="toast" class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover">
        {{ toast }}
      </div>
    </transition>
  </div>
</template>
