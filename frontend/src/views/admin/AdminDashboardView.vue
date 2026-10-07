<script setup lang="ts">
import type { Component } from 'vue'
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { AlertCircle, ArrowUpRight, Check, CircleDollarSign, Clock3, Layers3, Package, RefreshCw, ShoppingBag, Star, Store, Tag, Users, Wallet, X } from 'lucide-vue-next'
import RevenueChart from '@/components/admin/charts/RevenueChart.vue'
import OrdersTrendChart from '@/components/admin/charts/OrdersTrendChart.vue'
import OrderStatusChart from '@/components/admin/charts/OrderStatusChart.vue'
import PaymentStatusChart from '@/components/admin/charts/PaymentStatusChart.vue'
import SalesCategoryChart from '@/components/admin/charts/SalesCategoryChart.vue'
import DashboardMetricCard from '@/components/admin/DashboardMetricCard.vue'
import DashboardSection from '@/components/admin/DashboardSection.vue'
import BaseModal from '@/components/BaseModal.vue'
import StatusTag from '@/components/StatusTag.vue'
import { useAdminDashboard } from '@/composables/useAdminDashboard'
import type { AdminShop } from '@/api/admin'
import { formatCompactNumber, formatPrice } from '@/utils/format'

interface MetricCard { key: string; label: string; value: string; detail: string; delta?: number | null; icon: Component; tone: string }
const { t, locale } = useI18n()
const {
  dashboard, loading, error, range, customFrom, customTo, rangeError, lastUpdated, autoRefresh,
  pendingShops, shopCounts, shopsLoading, shopsError, shopActionId, actionError, actionSuccess,
  applyRange, onRangeChange, refresh, loadShops, updateShopStatus
} = useAdminDashboard()
const rejectShop = ref<AdminShop | null>(null)
const rejectionReason = ref('')
const metrics = computed(() => dashboard.value?.metrics)
const period = computed(() => dashboard.value?.period)
const rangeOptions = ['today', 'yesterday', '7', '30', 'this_month', 'last_month', 'custom']
const dateLocale = computed(() => locale.value === 'km' ? 'km-KH' : 'en-US')
const dateLabel = (date: string | null | undefined) => date ? new Date(date.length === 10 ? `${date}T00:00:00` : date).toLocaleDateString(dateLocale.value, { month: 'short', day: 'numeric', year: 'numeric' }) : '—'
const dayLabel = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(dateLocale.value, { month: 'short', day: 'numeric' })
const periodLabel = computed(() => period.value ? `${dateLabel(period.value.from)} – ${dateLabel(period.value.to)}` : '')
const updatedLabel = computed(() => lastUpdated.value?.toLocaleTimeString(dateLocale.value, { hour: '2-digit', minute: '2-digit' }) ?? '')
const revenueTrend = computed(() => (dashboard.value?.revenue_trend ?? []).map(item => ({ label: dayLabel(item.date), revenue: item.revenue })))
const ordersTrend = computed(() => (dashboard.value?.orders_trend ?? []).map(item => ({ label: dayLabel(item.date), orders: item.orders })))
const categorySales = computed(() => (dashboard.value?.sales_by_category ?? []).filter(item => item.revenue > 0).map(item => ({ category: item.name, sales: item.revenue })))
const hasRevenue = computed(() => revenueTrend.value.some(item => item.revenue > 0))
const hasOrders = computed(() => ordersTrend.value.some(item => item.orders > 0))
const cards = computed<MetricCard[]>(() => {
  const p = period.value
  const m = metrics.value
  if (!p || !m) return []
  return [
    { key: 'revenue', label: t('admin.dashboard.period_revenue'), value: formatPrice(p.revenue), detail: `${t('admin.dashboard.total_revenue')}: ${formatPrice(m.total_revenue)}`, delta: p.revenue_delta, icon: CircleDollarSign, tone: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300' },
    { key: 'orders', label: t('admin.nav.orders'), value: formatCompactNumber(p.orders_count), detail: `${t('admin.dashboard.total_orders')}: ${formatCompactNumber(m.orders_count)}`, delta: p.orders_delta, icon: ShoppingBag, tone: 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300' },
    { key: 'customers', label: t('admin.dashboard.new_customers'), value: formatCompactNumber(p.customers_count), detail: `${t('admin.dashboard.total_customers')}: ${formatCompactNumber(m.customers_count)}`, delta: p.customers_delta, icon: Users, tone: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300' },
    { key: 'average', label: t('admin.dashboard.average_order'), value: formatPrice(p.average_order_value), detail: t('admin.dashboard.paid_orders_only'), icon: Wallet, tone: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300' }
  ]
})
const revenueTotals = computed(() => metrics.value ? [
  { label: t('admin.dashboard.today_revenue'), value: metrics.value.today_revenue },
  { label: t('admin.dashboard.week_revenue'), value: metrics.value.week_revenue },
  { label: t('admin.dashboard.month_revenue'), value: metrics.value.month_revenue }
] : [])
const catalogTotals = computed(() => metrics.value ? [
  { label: t('admin.nav.products'), value: metrics.value.total_products, icon: Package, route: 'admin-products' },
  { label: t('admin.nav.categories'), value: metrics.value.total_categories, icon: Layers3, route: 'admin-categories' },
  { label: t('admin.nav.brands'), value: metrics.value.total_brands, icon: Tag, route: 'admin-brands' }
] : [])
const operationTotals = computed(() => metrics.value ? [
  { label: t('admin.dashboard.pending_orders'), value: metrics.value.pending_orders },
  { label: t('admin.dashboard.processing_orders'), value: metrics.value.processing_orders },
  { label: t('admin.dashboard.completed_orders'), value: metrics.value.completed_orders },
  { label: t('admin.dashboard.cancelled_orders'), value: metrics.value.cancelled_orders }
] : [])

function openRejection(shop: AdminShop) {
  if (shopActionId.value !== null) return
  rejectShop.value = shop
  rejectionReason.value = ''
  actionError.value = ''
}
function closeRejection() {
  if (shopActionId.value !== null) return
  rejectShop.value = null
  rejectionReason.value = ''
}
async function confirmRejection() {
  if (!rejectShop.value || !rejectionReason.value.trim()) return
  if (await updateShopStatus(rejectShop.value, 'rejected', rejectionReason.value.trim())) closeRejection()
}
</script>

<template>
  <div class="mx-auto max-w-[1600px] space-y-5 sm:space-y-6" :aria-busy="loading">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ t('admin.dashboard.title') }}</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.description') }}</p>
        <p v-if="lastUpdated" class="mt-2 text-xs text-gray-400 dark:text-muted">{{ t('admin.dashboard.updated_at', { time: updatedLabel }) }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <label class="inline-flex items-center gap-2 text-xs text-gray-500 dark:text-muted"><input v-model="autoRefresh" type="checkbox" class="accent-primary" />{{ t('admin.dashboard.auto_refresh') }}</label>
        <RouterLink :to="{ name: 'admin-reports' }" class="btn-secondary btn-sm"><ArrowUpRight class="h-4 w-4" aria-hidden="true" />{{ t('admin.nav.reports') }}</RouterLink>
        <button type="button" class="btn-icon h-9 w-9" :disabled="loading || shopsLoading" :aria-label="t('actions.refresh')" :title="t('actions.refresh')" @click="refresh"><RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading || shopsLoading }" aria-hidden="true" /></button>
      </div>
    </header>

    <section class="card flex flex-wrap items-end justify-between gap-4 px-5 py-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block text-xs font-medium text-gray-500 dark:text-muted">{{ t('admin.dashboard.date_range') }}
          <select v-model="range" class="select mt-1.5 min-w-40 text-sm" @change="onRangeChange"><option v-for="option in rangeOptions" :key="option" :value="option">{{ t(`admin.dashboard.range.${option}`) }}</option></select>
        </label>
        <form v-if="range === 'custom'" class="flex flex-wrap items-end gap-3" @submit.prevent="applyRange">
          <label class="block text-xs font-medium text-gray-500 dark:text-muted">{{ t('admin.dashboard.from') }}<input v-model="customFrom" type="date" class="input mt-1.5 text-sm" required /></label>
          <label class="block text-xs font-medium text-gray-500 dark:text-muted">{{ t('admin.dashboard.to') }}<input v-model="customTo" type="date" class="input mt-1.5 text-sm" :min="customFrom || undefined" required /></label>
          <button type="submit" class="btn-primary btn-sm" :disabled="loading">{{ t('actions.apply') }}</button>
        </form>
      </div>
      <p v-if="periodLabel" class="text-xs font-medium text-gray-500 dark:text-muted">{{ periodLabel }}</p>
      <p v-if="rangeError" role="alert" class="w-full text-sm text-red-600 dark:text-red-400">{{ rangeError }}</p>
    </section>

    <div v-if="error" role="alert" class="flex flex-wrap items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300">
      <AlertCircle class="h-5 w-5 shrink-0" aria-hidden="true" /><p class="flex-1">{{ error }} <span v-if="dashboard">{{ t('admin.dashboard.stale_data') }}</span></p><button type="button" class="font-semibold underline" @click="refresh">{{ t('actions.retry') }}</button>
    </div>
    <div v-if="!dashboard && !error" class="space-y-5" role="status" :aria-label="t('common.loading')">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div v-for="i in 4" :key="i" class="card h-44 animate-pulse bg-gray-100 dark:bg-surface-hover"></div></div>
      <div class="card h-80 animate-pulse bg-gray-100 dark:bg-surface-hover"></div>
    </div>

    <template v-if="dashboard && metrics && period">
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><DashboardMetricCard v-for="card in cards" :key="card.key" :label="card.label" :value="card.value" :detail="card.detail" :delta="card.delta" :icon="card.icon" :tone="card.tone" /></section>
      <section class="card grid divide-y divide-border-gray sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        <div v-for="item in revenueTotals" :key="item.label" class="flex items-center justify-between gap-4 px-5 py-4"><span class="text-sm text-gray-500 dark:text-muted">{{ item.label }}</span><span class="text-lg font-semibold tabular-nums text-ink">{{ formatPrice(item.value) }}</span></div>
      </section>
      <section class="grid gap-5 xl:grid-cols-12">
        <DashboardSection class="xl:col-span-7" :title="t('admin.chart.revenue_trend')" :description="t('admin.dashboard.paid_orders_only')"><div class="p-5"><RevenueChart v-if="hasRevenue" :data="revenueTrend" /><p v-else class="flex h-72 items-center justify-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_sales') }}</p></div></DashboardSection>
        <DashboardSection class="xl:col-span-5" :title="t('admin.dashboard.orders_trend')" :description="periodLabel"><div class="p-5"><OrdersTrendChart v-if="hasOrders" :data="ordersTrend" /><p v-else class="flex h-72 items-center justify-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_orders') }}</p></div></DashboardSection>
      </section>
      <section class="grid gap-5 lg:grid-cols-3">
        <DashboardSection :title="t('admin.chart.orders_by_status')" :description="t('admin.dashboard.selected_period')"><div class="p-5"><OrderStatusChart v-if="dashboard.status_distribution.length" :data="dashboard.status_distribution" /><p v-else class="flex h-72 items-center justify-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_orders') }}</p></div></DashboardSection>
        <DashboardSection :title="t('admin.chart.payments_by_status')" :description="t('admin.dashboard.payments_period')"><div class="p-5"><PaymentStatusChart :data="dashboard.payment_status_distribution" /></div></DashboardSection>
        <DashboardSection :title="t('admin.chart.sales_by_category')" :description="t('admin.dashboard.item_sales')"><div class="p-5"><SalesCategoryChart v-if="categorySales.length" :data="categorySales" /><p v-else class="flex h-72 items-center justify-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_sales') }}</p></div></DashboardSection>
      </section>

      <DashboardSection :title="t('admin.dashboard.live_operations')" :description="t('admin.dashboard.live_description')" :to="{ name: 'admin-orders' }">
        <div class="grid grid-cols-2 gap-4 p-5 md:grid-cols-6">
          <div v-for="item in operationTotals" :key="item.label"><p class="text-xs text-gray-500 dark:text-muted">{{ item.label }}</p><p class="mt-2 text-2xl font-semibold tabular-nums text-ink">{{ formatCompactNumber(item.value) }}</p></div>
          <RouterLink :to="{ name: 'admin-inventory', query: { stock_status: 'low' } }" class="rounded-lg bg-amber-50 px-3 py-2 hover:ring-1 hover:ring-amber-300 dark:bg-amber-500/10"><p class="text-xs text-amber-700 dark:text-amber-300">{{ t('admin.dashboard.low_stock_alerts') }}</p><p class="mt-1 text-2xl font-semibold text-amber-700 dark:text-amber-300">{{ metrics.low_stock_products }}</p></RouterLink>
          <RouterLink :to="{ name: 'admin-inventory', query: { stock_status: 'out' } }" class="rounded-lg bg-red-50 px-3 py-2 hover:ring-1 hover:ring-red-300 dark:bg-red-500/10"><p class="text-xs text-red-600 dark:text-red-300">{{ t('admin.dashboard.out_of_stock') }}</p><p class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-300">{{ metrics.out_of_stock_products }}</p></RouterLink>
        </div>
      </DashboardSection>
      <section class="grid gap-5 xl:grid-cols-2">
        <DashboardSection :title="t('admin.dashboard.low_stock_alerts')" :description="t('admin.dashboard.low_stock_description')" :to="{ name: 'admin-inventory', query: { stock_status: 'low' } }">
          <div v-if="dashboard.low_stock.length" class="max-h-96 divide-y divide-border-gray overflow-y-auto">
            <RouterLink v-for="item in dashboard.low_stock" :key="item.id" :to="{ name: 'admin-inventory', query: { q: item.sku ?? '', stock_status: 'low' } }" class="flex items-center gap-3 px-5 py-3.5 hover:bg-canvas/50">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="item.is_out_of_stock ? 'bg-red-50 text-red-500 dark:bg-red-500/10' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10'"><Package class="h-5 w-5" aria-hidden="true" /></span>
              <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-ink">{{ item.product_name }}</p><p class="mt-1 truncate text-xs text-gray-500 dark:text-muted">{{ item.sku }} · {{ item.variant_name }}</p></div>
              <div class="shrink-0 text-right"><p class="text-sm font-semibold" :class="item.is_out_of_stock ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400'">{{ item.is_out_of_stock ? t('admin.dashboard.out_of_stock') : t('admin.dashboard.count_left', { count: item.available_quantity }) }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ t('admin.dashboard.threshold', { count: item.low_stock_threshold }) }}</p></div>
            </RouterLink>
          </div>
          <div v-else class="flex items-center justify-center gap-2 px-5 py-12 text-sm text-gray-500 dark:text-muted"><Check class="h-5 w-5 text-emerald-500" aria-hidden="true" />{{ t('admin.dashboard.no_low_stock') }}</div>
        </DashboardSection>
        <DashboardSection :title="t('admin.dashboard.top_selling')" :description="t('admin.dashboard.item_sales')" :to="{ name: 'admin-products' }">
          <div v-if="dashboard.top_selling_products.length" class="divide-y divide-border-gray">
            <div v-for="(product, index) in dashboard.top_selling_products" :key="`${product.product_id}-${product.product_name}`" class="flex items-center gap-3 px-5 py-4"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary">{{ index + 1 }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-ink">{{ product.product_name }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ t('admin.dashboard.sold_count', { count: product.total_qty }) }}</p></div><p class="shrink-0 text-sm font-semibold tabular-nums text-ink">{{ formatPrice(product.revenue) }}</p></div>
          </div>
          <p v-else class="px-5 py-12 text-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_sales') }}</p>
        </DashboardSection>
      </section>

      <DashboardSection :title="t('admin.dashboard.recent_orders')" :description="periodLabel" :to="{ name: 'admin-orders' }">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[780px] text-sm">
            <thead class="border-b border-border-gray bg-canvas/60 text-left text-xs font-medium text-gray-500 dark:text-muted"><tr><th scope="col" class="px-5 py-3">{{ t('order.order') }}</th><th scope="col" class="px-5 py-3">{{ t('admin.orders.customer') }}</th><th scope="col" class="px-5 py-3">{{ t('order.items') }}</th><th scope="col" class="px-5 py-3 text-right">{{ t('order.total') }}</th><th scope="col" class="px-5 py-3">{{ t('admin.orders.payment_method') }}</th><th scope="col" class="px-5 py-3">{{ t('order.status') }}</th><th scope="col" class="px-5 py-3">{{ t('order.date') }}</th></tr></thead>
            <tbody class="divide-y divide-border-gray">
              <tr v-if="!dashboard.recent_orders.length"><td colspan="7" class="px-5 py-12 text-center text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_orders') }}</td></tr>
              <tr v-for="order in dashboard.recent_orders" :key="order.id" class="hover:bg-canvas/50">
                <td class="px-5 py-4"><RouterLink :to="{ name: 'admin-order-detail', params: { id: order.id } }" class="font-semibold text-primary hover:underline">{{ order.order_number }}</RouterLink></td>
                <td class="px-5 py-4"><p class="font-medium text-ink">{{ order.customer_name ?? order.user?.name ?? t('admin.dashboard.guest_customer') }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ order.email ?? order.user?.email }}</p></td>
                <td class="px-5 py-4 text-gray-500 dark:text-muted">{{ order.items_count }}</td><td class="px-5 py-4 text-right font-semibold tabular-nums text-ink">{{ formatPrice(order.total, order.currency) }}</td>
                <td class="px-5 py-4"><StatusTag :status="order.payment_status" /></td><td class="px-5 py-4"><StatusTag :status="order.status" /></td><td class="whitespace-nowrap px-5 py-4 text-xs text-gray-500 dark:text-muted">{{ dateLabel(order.placed_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </DashboardSection>
      <section class="grid gap-5 lg:grid-cols-3">
        <DashboardSection :title="t('admin.dashboard.recent_customers')" :description="t('admin.dashboard.selected_period')" :to="{ name: 'admin-customers' }">
          <div v-if="dashboard.recent_customers.length" class="divide-y divide-border-gray"><RouterLink v-for="customer in dashboard.recent_customers" :key="customer.id" :to="{ name: 'admin-customer-detail', params: { id: customer.id } }" class="flex items-center gap-3 px-5 py-4 hover:bg-canvas/50"><img v-if="customer.avatar" :src="customer.avatar" alt="" class="h-10 w-10 rounded-full object-cover" /><span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">{{ customer.name.charAt(0).toUpperCase() }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-ink">{{ customer.name }}</p><p class="mt-1 truncate text-xs text-gray-500 dark:text-muted">{{ customer.email }}</p><p class="mt-1 text-xs text-gray-400 dark:text-muted">{{ dateLabel(customer.created_at) }}</p></div></RouterLink></div>
          <p v-else class="px-5 py-12 text-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_customers') }}</p>
        </DashboardSection>
        <DashboardSection :title="t('admin.dashboard.recent_reviews')" :description="t('admin.dashboard.selected_period')" :to="{ name: 'admin-reviews' }">
          <div v-if="dashboard.recent_reviews.length" class="divide-y divide-border-gray"><div v-for="review in dashboard.recent_reviews" :key="review.id" class="px-5 py-4"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate text-sm font-semibold text-ink">{{ review.product_name }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ review.user_name }} · {{ dateLabel(review.created_at) }}</p></div><span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-amber-600"><Star class="h-3.5 w-3.5" aria-hidden="true" />{{ review.rating }}/5</span></div><p class="mt-2 line-clamp-2 text-xs leading-relaxed text-gray-500 dark:text-muted">{{ review.title ?? review.body }}</p><div class="mt-2"><StatusTag :status="review.status" /></div></div></div>
          <p v-else class="px-5 py-12 text-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_reviews') }}</p>
        </DashboardSection>
        <DashboardSection :title="t('admin.dashboard.recent_payments')" :description="t('admin.dashboard.payments_period')" :to="{ name: 'admin-payments' }">
          <div v-if="dashboard.recent_payments.length" class="divide-y divide-border-gray"><RouterLink v-for="payment in dashboard.recent_payments" :key="payment.id" :to="{ name: 'admin-order-detail', params: { id: payment.order_id } }" class="block px-5 py-4 hover:bg-canvas/50"><div class="flex items-center justify-between gap-2"><span class="text-sm font-semibold text-primary">{{ payment.order_number }}</span><span class="text-sm font-semibold tabular-nums text-ink">{{ formatPrice(payment.amount, payment.currency ?? 'USD') }}</span></div><div class="mt-2 flex items-center justify-between gap-2"><p class="text-xs capitalize text-gray-500 dark:text-muted">{{ payment.method ?? '—' }} · {{ dateLabel(payment.paid_at ?? payment.created_at) }}</p><StatusTag :status="payment.status" /></div></RouterLink></div>
          <p v-else class="px-5 py-12 text-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_payments') }}</p>
        </DashboardSection>
      </section>

      <section class="grid gap-5 xl:grid-cols-2">
        <DashboardSection :title="t('admin.dashboard.pending_shops')" :description="t('admin.dashboard.live_description')" :to="{ name: 'admin-shops' }">
          <p v-if="shopsError" role="alert" class="m-5 text-sm text-red-600 dark:text-red-400">{{ shopsError }} <button type="button" class="font-semibold underline" @click="loadShops">{{ t('actions.retry') }}</button></p>
          <p v-if="actionError" role="alert" class="mx-5 my-3 text-sm text-red-600 dark:text-red-400">{{ actionError }}</p><p v-if="actionSuccess" role="status" class="mx-5 my-3 text-sm text-emerald-600 dark:text-emerald-400">{{ actionSuccess }}</p>
          <p v-if="shopsLoading && !shopCounts" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-muted">{{ t('common.loading') }}</p>
          <div v-else-if="pendingShops.length" class="divide-y divide-border-gray"><div v-for="shop in pendingShops" :key="shop.id" class="flex flex-wrap items-center gap-3 px-5 py-4"><img v-if="shop.logo" :src="shop.logo" :alt="shop.name" class="h-10 w-10 rounded-lg border border-border-gray object-cover" /><span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary">{{ shop.name.charAt(0).toUpperCase() }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-ink">{{ shop.name }}</p><p class="mt-1 truncate text-xs text-gray-500 dark:text-muted">{{ shop.email ?? shop.city ?? shop.code }}</p></div><div class="flex shrink-0 gap-2"><button type="button" class="btn-primary btn-sm" :disabled="shopActionId !== null || shopsLoading" @click="updateShopStatus(shop, 'active')"><Check class="h-3.5 w-3.5" aria-hidden="true" />{{ t('admin.dashboard.approve') }}</button><button type="button" class="btn-secondary btn-sm !text-red-600" :disabled="shopActionId !== null || shopsLoading" @click="openRejection(shop)">{{ t('admin.dashboard.reject') }}</button></div></div></div>
          <p v-else-if="!shopsError" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_pending_shops') }}</p>
        </DashboardSection>
        <DashboardSection :title="t('admin.dashboard.marketplace_summary')" :description="t('admin.dashboard.live_description')">
          <div class="grid grid-cols-3 gap-4 p-5"><RouterLink v-for="item in catalogTotals" :key="item.route" :to="{ name: item.route }" class="rounded-xl border border-border-gray p-3 transition-colors hover:border-primary/30"><component :is="item.icon" class="h-5 w-5 text-primary" aria-hidden="true" /><p class="mt-3 text-xl font-semibold text-ink">{{ formatCompactNumber(item.value) }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ item.label }}</p></RouterLink></div>
          <div v-if="shopCounts" class="grid grid-cols-3 gap-3 border-t border-border-gray px-5 py-4"><div><Store class="h-4 w-4 text-primary" aria-hidden="true" /><p class="mt-2 text-xl font-semibold text-ink">{{ shopCounts.total }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ t('admin.dashboard.total_shops') }}</p></div><div><Check class="h-4 w-4 text-emerald-600" aria-hidden="true" /><p class="mt-2 text-xl font-semibold text-ink">{{ shopCounts.active }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ t('admin.dashboard.active_shops') }}</p></div><div><Clock3 class="h-4 w-4 text-amber-600" aria-hidden="true" /><p class="mt-2 text-xl font-semibold text-ink">{{ shopCounts.pending }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ t('admin.dashboard.pending_shops') }}</p></div></div>
          <p v-else class="px-5 pb-5 text-xs text-gray-500 dark:text-muted">{{ t(shopsLoading ? 'common.loading' : 'admin.dashboard.shops_unavailable') }}</p>
        </DashboardSection>
      </section>
    </template>

    <BaseModal :model-value="rejectShop !== null" size="sm" :title="t('admin.dashboard.reject_title')" :close-on-backdrop="shopActionId === null" @update:model-value="closeRejection">
      <p class="text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.reject_description', { name: rejectShop?.name }) }}</p><p v-if="actionError" role="alert" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ actionError }}</p>
      <label class="mt-4 block text-sm font-medium text-ink" for="rejection-reason">{{ t('admin.dashboard.rejection_reason') }}</label><textarea id="rejection-reason" v-model="rejectionReason" class="input mt-2 min-h-24 resize-y" maxlength="500" :disabled="shopActionId !== null" />
      <template #footer><div class="flex justify-end gap-2"><button type="button" class="btn-secondary btn-sm" :disabled="shopActionId !== null" @click="closeRejection">{{ t('actions.cancel') }}</button><button type="button" class="btn-danger btn-sm" :disabled="!rejectionReason.trim() || shopActionId !== null" @click="confirmRejection"><X class="h-4 w-4" aria-hidden="true" />{{ t('admin.dashboard.reject') }}</button></div></template>
    </BaseModal>
  </div>
</template>
