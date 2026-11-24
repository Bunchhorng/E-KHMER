<script setup lang="ts">
import type { Component } from 'vue'
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { AlertCircle, ArrowUpRight, Check, CircleDollarSign, Clock3, Headphones, Layers3, Package, RefreshCw, ShoppingBag, Star, Store, Tag, Users, Wallet, X } from 'lucide-vue-next'
import RevenueChart from '@/components/admin/charts/RevenueChart.vue'
import OrdersTrendChart from '@/components/admin/charts/OrdersTrendChart.vue'
import OrderStatusChart from '@/components/admin/charts/OrderStatusChart.vue'
import PaymentStatusChart from '@/components/admin/charts/PaymentStatusChart.vue'
import DashboardMetricCard from '@/components/dashboard/DashboardMetricCard.vue'
import DashboardSection from '@/components/dashboard/DashboardSection.vue'
import DashboardPageHeader from '@/components/dashboard/DashboardPageHeader.vue'
import type { DashboardTone } from '@/components/dashboard/types'
import BaseModal from '@/components/BaseModal.vue'
import StatusTag from '@/components/StatusTag.vue'
import { useAdminDashboard } from '@/composables/useAdminDashboard'
import type { AdminShop } from '@/api/admin'
import { formatCompactNumber, formatPrice } from '@/utils/format'

interface MetricCard { key: string; label: string; value: string; detail: string; delta?: number | null; icon: Component; tone: DashboardTone }
const { t, locale } = useI18n()
const {
  dashboard, loading, error, range, customFrom, customTo, rangeError, lastUpdated, autoRefresh,
  pendingShops, shopCounts, shopsLoading, shopsError, shopActionId, actionError, actionSuccess,
  applyRange, onRangeChange, refresh, loadShops, updateShopStatus
} = useAdminDashboard()
const rejectShop = ref<AdminShop | null>(null)
const approveShop = ref<AdminShop | null>(null)
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
const hasRevenue = computed(() => revenueTrend.value.some(item => item.revenue > 0))
const hasOrders = computed(() => ordersTrend.value.some(item => item.orders > 0))
const cards = computed<MetricCard[]>(() => {
  const p = period.value
  const m = metrics.value
  if (!p || !m) return []
  return [
    { key: 'revenue', label: t('admin.overview.paid_order_value'), value: formatPrice(p.revenue), detail: `${t('admin.overview.lifetime_paid_value')}: ${formatPrice(m.total_revenue)}`, delta: p.revenue_delta, icon: CircleDollarSign, tone: 'blue' },
    { key: 'orders', label: t('admin.nav.orders'), value: formatCompactNumber(p.orders_count), detail: `${t('admin.dashboard.total_orders')}: ${formatCompactNumber(m.orders_count)}`, delta: p.orders_delta, icon: ShoppingBag, tone: 'violet' },
    { key: 'customers', label: t('admin.dashboard.new_customers'), value: formatCompactNumber(p.customers_count), detail: `${t('admin.dashboard.total_customers')}: ${formatCompactNumber(m.customers_count)}`, delta: p.customers_delta, icon: Users, tone: 'green' },
    { key: 'average', label: t('admin.dashboard.average_order'), value: formatPrice(p.average_order_value), detail: t('admin.dashboard.paid_orders_only'), icon: Wallet, tone: 'amber' }
  ]
})
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

const attentionCards = computed(() => [
  { label: t('admin.overview.payment_exceptions'), value: dashboard.value?.attention?.payment_exceptions ?? 0, route: 'admin-payments', query: { attention: '1' }, icon: AlertCircle, tone: 'danger' },
  { label: t('admin.overview.pending_reviews'), value: dashboard.value?.attention?.pending_reviews ?? 0, route: 'admin-reviews', icon: Star, tone: 'warning' },
  { label: t('admin.overview.open_returns'), value: dashboard.value?.attention?.open_returns ?? 0, route: 'admin-returns', icon: Package, tone: 'info' },
  { label: t('admin.overview.open_support'), value: dashboard.value?.attention?.open_support ?? 0, route: 'admin-support', icon: Headphones, tone: 'primary' }
])

function openApproval(shop: AdminShop) {
  if (shopActionId.value !== null) return
  approveShop.value = shop
  actionError.value = ''
}
function closeApproval() {
  if (shopActionId.value !== null) return
  approveShop.value = null
}
async function confirmApproval() {
  if (!approveShop.value) return
  if (await updateShopStatus(approveShop.value, 'active')) closeApproval()
}

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
  <div class="admin-enterprise-dashboard space-y-4 sm:space-y-5" :aria-busy="loading">
    <DashboardPageHeader class="enterprise-dashboard-header" :title="t('admin.dashboard.title')" :description="t('admin.overview.description')">
      <template #meta>
        <p v-if="lastUpdated" class="enterprise-updated-at">{{ t('admin.dashboard.updated_at', { time: updatedLabel }) }}</p>
      </template>
      <div class="enterprise-dashboard-toolbar">
        <label class="enterprise-toolbar-field">
          <span>{{ t('admin.dashboard.date_range') }}</span>
          <select v-model="range" :aria-label="t('admin.dashboard.date_range')" class="select" @change="onRangeChange"><option v-for="option in rangeOptions" :key="option" :value="option">{{ t(`admin.dashboard.range.${option}`) }}</option></select>
        </label>
        <label class="enterprise-auto-refresh"><input v-model="autoRefresh" type="checkbox" class="accent-primary" />{{ t('admin.dashboard.auto_refresh') }}</label>
        <button type="button" class="btn-secondary enterprise-refresh-button" :disabled="loading || shopsLoading" @click="refresh"><RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading || shopsLoading }" aria-hidden="true" /><span>{{ t('actions.refresh') }}</span></button>
        <RouterLink :to="{ name: 'admin-reports' }" class="btn-primary enterprise-reports-button"><ArrowUpRight class="h-4 w-4" aria-hidden="true" />{{ t('admin.nav.reports') }}</RouterLink>
      </div>
    </DashboardPageHeader>

    <section v-if="range === 'custom'" class="enterprise-filter-panel">
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyRange">
        <label class="enterprise-toolbar-field">{{ t('admin.dashboard.from') }}<input v-model="customFrom" type="date" class="input" required /></label>
        <label class="enterprise-toolbar-field">{{ t('admin.dashboard.to') }}<input v-model="customTo" type="date" class="input" :min="customFrom || undefined" required /></label>
        <button type="submit" class="btn-primary enterprise-apply-button" :disabled="loading">{{ t('actions.apply') }}</button>
      </form>
      <p v-if="rangeError" role="alert" class="w-full text-sm text-red-600 dark:text-red-400">{{ rangeError }}</p>
    </section>
    <p v-else-if="periodLabel" class="enterprise-period-label">{{ periodLabel }}</p>

    <div v-if="error" role="alert" class="enterprise-dashboard-alert flex flex-wrap items-center gap-3 border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-500/25 dark:bg-red-500/10 dark:text-red-300">
      <AlertCircle class="h-5 w-5 shrink-0" aria-hidden="true" /><p class="flex-1">{{ error }} <span v-if="dashboard">{{ t('admin.dashboard.stale_data') }}</span></p><button type="button" class="font-semibold underline" @click="refresh">{{ t('actions.retry') }}</button>
    </div>
    <div v-if="!dashboard && !error" class="space-y-5" role="status" :aria-label="t('common.loading')">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><div v-for="i in 4" :key="i" class="card h-44 animate-pulse bg-gray-100 dark:bg-surface-hover"></div></div>
      <div class="card h-80 animate-pulse bg-gray-100 dark:bg-surface-hover"></div>
    </div>

    <template v-if="dashboard && metrics && period">
      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><DashboardMetricCard v-for="card in cards" :key="card.key" :label="card.label" :value="card.value" :detail="card.detail" :delta="card.delta" :icon="card.icon" :tone="card.tone" /></section>

      <section aria-labelledby="admin-attention-title" class="enterprise-attention-section">
        <div class="enterprise-section-title"><div><h2 id="admin-attention-title">{{ t('admin.overview.needs_attention') }}</h2><p>{{ t('admin.overview.live_queues') }}</p></div></div>
        <div class="grid grid-cols-1 gap-px border border-border-gray bg-border-gray sm:grid-cols-2 xl:grid-cols-4">
          <RouterLink v-for="item in attentionCards" :key="item.route" :to="{ name: item.route, query: item.query }" class="enterprise-attention-card" :data-tone="item.tone">
            <span class="enterprise-attention-icon"><component :is="item.icon" class="h-5 w-5" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1"><p>{{ item.label }}</p><strong>{{ formatCompactNumber(item.value) }}</strong></div>
            <ArrowUpRight class="h-4 w-4 shrink-0" aria-hidden="true" />
          </RouterLink>
        </div>
      </section>
      <section class="grid gap-5 xl:grid-cols-2">
        <DashboardSection :title="t('admin.overview.payment_exceptions')" :description="t('admin.overview.payment_exceptions_description')" :to="{ name: 'admin-payments', query: { attention: '1' } }">
          <div v-if="dashboard.payment_exceptions?.length" class="divide-y divide-border-gray">
            <RouterLink v-for="payment in dashboard.payment_exceptions" :key="payment.id" :to="{ name: 'admin-order-detail', params: { id: payment.order_id } }" class="enterprise-data-row block px-5 py-4"><div class="flex items-center justify-between gap-2"><span class="text-sm font-semibold text-primary">{{ payment.order_number }}</span><span class="text-sm font-semibold tabular-nums">{{ formatPrice(payment.amount, payment.currency) }}</span></div><div class="mt-2 flex items-center justify-between gap-2"><span class="text-xs capitalize text-muted">{{ payment.method }} · {{ dateLabel(payment.updated_at) }}</span><StatusTag :status="payment.status" square /></div></RouterLink>
          </div>
          <p v-else class="px-5 py-10 text-center text-sm text-muted">{{ t('admin.overview.no_payment_exceptions') }}</p>
        </DashboardSection>
        <DashboardSection :title="t('admin.overview.pending_reviews')" :description="t('admin.overview.live_queues')" :to="{ name: 'admin-reviews' }">
          <div v-if="dashboard.pending_reviews?.length" class="divide-y divide-border-gray"><div v-for="review in dashboard.pending_reviews" :key="review.id" class="enterprise-data-row px-5 py-4"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate text-sm font-semibold text-ink">{{ review.product_name }}</p><p class="mt-1 text-xs text-muted">{{ review.user_name }} · {{ dateLabel(review.created_at) }}</p></div><span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-amber-600"><Star class="h-3.5 w-3.5" aria-hidden="true" />{{ review.rating }}/5</span></div><p class="mt-2 line-clamp-2 text-xs text-muted">{{ review.title ?? review.body }}</p><RouterLink :to="{ name: 'admin-reviews' }" class="mt-2 inline-block text-xs font-semibold text-primary hover:underline">{{ t('admin.overview.moderate_review') }}</RouterLink></div></div>
          <p v-else class="px-5 py-10 text-center text-sm text-muted">{{ t('admin.overview.no_pending_reviews') }}</p>
        </DashboardSection>
      </section>
      <section class="grid gap-5 xl:grid-cols-2">
        <DashboardSection :title="t('admin.dashboard.pending_shops')" :description="t('admin.dashboard.live_description')" :to="{ name: 'admin-shops' }">
          <p v-if="shopsError" role="alert" class="m-5 text-sm text-red-600 dark:text-red-400">{{ shopsError }} <button type="button" class="font-semibold underline" @click="loadShops">{{ t('actions.retry') }}</button></p>
          <p v-if="actionError" role="alert" class="mx-5 my-3 text-sm text-red-600 dark:text-red-400">{{ actionError }}</p><p v-if="actionSuccess" role="status" class="mx-5 my-3 text-sm text-emerald-600 dark:text-emerald-400">{{ actionSuccess }}</p>
          <p v-if="shopsLoading && !shopCounts" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-muted">{{ t('common.loading') }}</p>
          <div v-else-if="pendingShops.length" class="divide-y divide-border-gray"><div v-for="shop in pendingShops" :key="shop.id" class="enterprise-shop-row flex flex-wrap items-center gap-3 px-5 py-4"><img v-if="shop.logo" :src="shop.logo" :alt="shop.name" class="h-10 w-10 border border-border-gray object-cover" /><span v-else class="flex h-10 w-10 shrink-0 items-center justify-center bg-primary/10 text-sm font-semibold text-primary">{{ shop.name.charAt(0).toUpperCase() }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-ink">{{ shop.name }}</p><p class="mt-1 truncate text-xs text-gray-500 dark:text-muted">{{ shop.email ?? shop.city ?? shop.code }}</p></div><div class="flex shrink-0 gap-2"><button type="button" class="btn-primary btn-sm" :disabled="shopActionId !== null || shopsLoading" @click="openApproval(shop)"><Check class="h-3.5 w-3.5" aria-hidden="true" />{{ t('admin.dashboard.approve') }}</button><button type="button" class="btn-secondary btn-sm !text-red-600" :disabled="shopActionId !== null || shopsLoading" @click="openRejection(shop)">{{ t('admin.dashboard.reject') }}</button></div></div></div>
          <p v-else-if="!shopsError" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_pending_shops') }}</p>
        </DashboardSection>
        <DashboardSection :title="t('admin.dashboard.marketplace_summary')" :description="t('admin.dashboard.live_description')">
          <div class="grid grid-cols-1 gap-px border-b border-border-gray bg-border-gray sm:grid-cols-3"><RouterLink v-for="item in catalogTotals" :key="item.route" :to="{ name: item.route }" class="enterprise-summary-card"><component :is="item.icon" class="h-5 w-5 text-primary" aria-hidden="true" /><p>{{ formatCompactNumber(item.value) }}</p><span>{{ item.label }}</span></RouterLink></div>
          <div v-if="shopCounts" class="grid grid-cols-3 gap-3 border-t border-border-gray px-5 py-4"><div><Store class="h-4 w-4 text-primary" aria-hidden="true" /><p class="mt-2 text-xl font-semibold text-ink">{{ shopCounts.total }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ t('admin.dashboard.total_shops') }}</p></div><div><Check class="h-4 w-4 text-emerald-600" aria-hidden="true" /><p class="mt-2 text-xl font-semibold text-ink">{{ shopCounts.active }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ t('admin.dashboard.active_shops') }}</p></div><div><Clock3 class="h-4 w-4 text-amber-600" aria-hidden="true" /><p class="mt-2 text-xl font-semibold text-ink">{{ shopCounts.pending }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ t('admin.dashboard.pending_shops') }}</p></div></div>
          <p v-else class="px-5 pb-5 text-xs text-gray-500 dark:text-muted">{{ t(shopsLoading ? 'common.loading' : 'admin.dashboard.shops_unavailable') }}</p>
        </DashboardSection>
      </section>
      <section class="grid gap-5 xl:grid-cols-12">
        <DashboardSection class="xl:col-span-7" :title="t('admin.overview.paid_value_trend')" :description="t('admin.dashboard.paid_orders_only')"><div class="p-5"><RevenueChart v-if="hasRevenue" :data="revenueTrend" /><p v-else class="flex h-72 items-center justify-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_sales') }}</p></div></DashboardSection>
        <DashboardSection class="xl:col-span-5" :title="t('admin.dashboard.orders_trend')" :description="periodLabel"><div class="p-5"><OrdersTrendChart v-if="hasOrders" :data="ordersTrend" square /><p v-else class="flex h-72 items-center justify-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_orders') }}</p></div></DashboardSection>
      </section>
      <section class="grid gap-5 lg:grid-cols-2">
        <DashboardSection :title="t('admin.chart.orders_by_status')" :description="t('admin.dashboard.selected_period')"><div class="p-5"><OrderStatusChart v-if="dashboard.status_distribution.length" :data="dashboard.status_distribution" square /><p v-else class="flex h-72 items-center justify-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_orders') }}</p></div></DashboardSection>
        <DashboardSection :title="t('admin.chart.payments_by_status')" :description="t('admin.dashboard.payments_period')"><div class="p-5"><PaymentStatusChart :data="dashboard.payment_status_distribution" /></div></DashboardSection>
      </section>

      <DashboardSection :title="t('admin.dashboard.live_operations')" :description="t('admin.dashboard.live_description')" :to="{ name: 'admin-orders' }">
        <div class="grid grid-cols-2 gap-4 p-5 md:grid-cols-6">
          <div v-for="item in operationTotals" :key="item.label"><p class="text-xs text-gray-500 dark:text-muted">{{ item.label }}</p><p class="mt-2 text-2xl font-semibold tabular-nums text-ink">{{ formatCompactNumber(item.value) }}</p></div>
          <RouterLink :to="{ name: 'admin-inventory', query: { stock_status: 'low' } }" class="rounded-lg bg-amber-50 px-3 py-2 hover:ring-1 hover:ring-amber-300 dark:bg-amber-500/10"><p class="text-xs text-amber-700 dark:text-amber-300">{{ t('admin.dashboard.low_stock_alerts') }}</p><p class="mt-1 text-2xl font-semibold text-amber-700 dark:text-amber-300">{{ metrics.low_stock_products }}</p></RouterLink>
          <RouterLink :to="{ name: 'admin-inventory', query: { stock_status: 'out' } }" class="rounded-lg bg-red-50 px-3 py-2 hover:ring-1 hover:ring-red-300 dark:bg-red-500/10"><p class="text-xs text-red-600 dark:text-red-300">{{ t('admin.dashboard.out_of_stock') }}</p><p class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-300">{{ metrics.out_of_stock_products }}</p></RouterLink>
        </div>
      </DashboardSection>
      <DashboardSection :title="t('admin.dashboard.recent_orders')" :description="periodLabel" :to="{ name: 'admin-orders' }">
        <div class="divide-y divide-border-gray md:hidden">
          <p v-if="!dashboard.recent_orders.length" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_orders') }}</p>
          <RouterLink v-for="order in dashboard.recent_orders" v-else :key="order.id" :to="{ name: 'admin-order-detail', params: { id: order.id } }" class="enterprise-order-record block p-4">
            <div class="flex items-start justify-between gap-3"><span class="font-semibold text-primary">{{ order.order_number }}</span><StatusTag :status="order.status" square /></div>
            <p class="mt-2 font-medium text-ink">{{ order.customer_name ?? order.user?.name ?? t('admin.dashboard.guest_customer') }}</p>
            <p class="mt-1 truncate text-xs text-gray-500 dark:text-muted">{{ order.email ?? order.user?.email }}</p>
            <div class="mt-4 grid grid-cols-2 gap-3 border-y border-border-gray py-3 text-sm"><div><p class="text-xs text-gray-500 dark:text-muted">{{ t('order.items') }}</p><p class="mt-1 font-semibold text-ink">{{ order.items_count }}</p></div><div class="text-right"><p class="text-xs text-gray-500 dark:text-muted">{{ t('order.total') }}</p><p class="mt-1 font-semibold tabular-nums text-ink">{{ formatPrice(order.total, order.currency) }}</p></div></div>
            <div class="mt-3 flex items-center justify-between gap-3"><StatusTag :status="order.payment_status" square /><span class="text-xs text-gray-500 dark:text-muted">{{ dateLabel(order.placed_at) }}</span></div>
          </RouterLink>
        </div>
        <div class="hidden overflow-x-auto md:block">
          <table class="w-full min-w-[780px] text-sm">
            <thead class="border-b border-border-gray bg-canvas/60 text-left text-xs font-medium text-gray-500 dark:text-muted"><tr><th scope="col" class="px-5 py-3">{{ t('order.order') }}</th><th scope="col" class="px-5 py-3">{{ t('admin.orders.customer') }}</th><th scope="col" class="px-5 py-3">{{ t('order.items') }}</th><th scope="col" class="px-5 py-3 text-right">{{ t('order.total') }}</th><th scope="col" class="px-5 py-3">{{ t('admin.orders.payment_method') }}</th><th scope="col" class="px-5 py-3">{{ t('order.status') }}</th><th scope="col" class="px-5 py-3">{{ t('order.date') }}</th></tr></thead>
            <tbody class="divide-y divide-border-gray">
              <tr v-if="!dashboard.recent_orders.length"><td colspan="7" class="px-5 py-12 text-center text-gray-500 dark:text-muted">{{ t('admin.dashboard.no_orders') }}</td></tr>
              <tr v-for="order in dashboard.recent_orders" :key="order.id" class="hover:bg-canvas/50">
                <td class="px-5 py-4"><RouterLink :to="{ name: 'admin-order-detail', params: { id: order.id } }" class="font-semibold text-primary hover:underline">{{ order.order_number }}</RouterLink></td>
                <td class="px-5 py-4"><p class="font-medium text-ink">{{ order.customer_name ?? order.user?.name ?? t('admin.dashboard.guest_customer') }}</p><p class="mt-1 text-xs text-gray-500 dark:text-muted">{{ order.email ?? order.user?.email }}</p></td>
                <td class="px-5 py-4 text-gray-500 dark:text-muted">{{ order.items_count }}</td><td class="px-5 py-4 text-right font-semibold tabular-nums text-ink">{{ formatPrice(order.total, order.currency) }}</td>
                <td class="px-5 py-4"><StatusTag :status="order.payment_status" square /></td><td class="px-5 py-4"><StatusTag :status="order.status" square /></td><td class="whitespace-nowrap px-5 py-4 text-xs text-gray-500 dark:text-muted">{{ dateLabel(order.placed_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </DashboardSection>

    </template>

    <BaseModal :model-value="approveShop !== null" sharp size="sm" :title="t('admin.dashboard.approve_title')" :close-on-backdrop="shopActionId === null" @update:model-value="closeApproval">
      <p class="text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.approve_description', { name: approveShop?.name }) }}</p><p v-if="actionError" role="alert" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ actionError }}</p>
      <template #footer><div class="flex justify-end gap-2"><button type="button" class="btn-secondary btn-sm" :disabled="shopActionId !== null" @click="closeApproval">{{ t('actions.cancel') }}</button><button type="button" class="btn-primary btn-sm" :disabled="shopActionId !== null" @click="confirmApproval"><Check class="h-4 w-4" aria-hidden="true" />{{ t('admin.dashboard.approve') }}</button></div></template>
    </BaseModal>

    <BaseModal :model-value="rejectShop !== null" sharp size="sm" :title="t('admin.dashboard.reject_title')" :close-on-backdrop="shopActionId === null" @update:model-value="closeRejection">
      <p class="text-sm text-gray-500 dark:text-muted">{{ t('admin.dashboard.reject_description', { name: rejectShop?.name }) }}</p><p v-if="actionError" role="alert" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ actionError }}</p>
      <label class="mt-4 block text-sm font-medium text-ink" for="rejection-reason">{{ t('admin.dashboard.rejection_reason') }}</label><textarea id="rejection-reason" v-model="rejectionReason" class="input mt-2 min-h-24 resize-y" maxlength="500" :disabled="shopActionId !== null" />
      <template #footer><div class="flex justify-end gap-2"><button type="button" class="btn-secondary btn-sm" :disabled="shopActionId !== null" @click="closeRejection">{{ t('actions.cancel') }}</button><button type="button" class="btn-danger btn-sm" :disabled="!rejectionReason.trim() || shopActionId !== null" @click="confirmRejection"><X class="h-4 w-4" aria-hidden="true" />{{ t('admin.dashboard.reject') }}</button></div></template>
    </BaseModal>
  </div>
</template>

<style scoped>
.admin-enterprise-dashboard { min-width: 0; }

.admin-enterprise-dashboard :deep(.card),
.admin-enterprise-dashboard :deep(.dashboard-section),
.admin-enterprise-dashboard :deep(.dashboard-metric),
.admin-enterprise-dashboard :deep(.rounded),
.admin-enterprise-dashboard :deep(.rounded-sm),
.admin-enterprise-dashboard :deep(.rounded-md),
.admin-enterprise-dashboard :deep(.rounded-lg),
.admin-enterprise-dashboard :deep(.rounded-xl),
.admin-enterprise-dashboard :deep(.rounded-2xl),
.admin-enterprise-dashboard :deep(.rounded-full) { border-radius: 0 !important; }

.admin-enterprise-dashboard :deep(.card),
.admin-enterprise-dashboard :deep(.dashboard-section),
.admin-enterprise-dashboard :deep(.dashboard-metric),
.admin-enterprise-dashboard :deep(.btn),
.admin-enterprise-dashboard :deep(.btn-icon) { box-shadow: none !important; }

.admin-enterprise-dashboard :deep(.btn),
.admin-enterprise-dashboard :deep(.btn-icon),
.admin-enterprise-dashboard :deep(.input),
.admin-enterprise-dashboard :deep(.select),
.admin-enterprise-dashboard :deep(.textarea) { border-radius: 0 !important; }

.admin-enterprise-dashboard :deep(.dashboard-metric) { min-height: 172px; padding: 1.25rem; border-color: rgb(var(--color-border)); background: rgb(var(--color-surface)); }
.admin-enterprise-dashboard :deep(.dashboard-metric-icon) { width: 2.375rem; height: 2.375rem; }
.admin-enterprise-dashboard :deep(.dashboard-metric-value) { letter-spacing: -0.035em; }
.admin-enterprise-dashboard :deep(.dashboard-section-header) { min-height: 4rem; padding: .875rem 1.125rem; background: rgb(var(--color-canvas) / .65); }
.admin-enterprise-dashboard :deep(.dashboard-section-header h2) { font-size: .9375rem; letter-spacing: -.01em; }
.admin-enterprise-dashboard :deep(.dashboard-section-header p) { margin-top: .125rem; font-size: .75rem; }

.admin-enterprise-dashboard :deep(.enterprise-dashboard-header) { min-height: 0; padding-bottom: .25rem; }
.admin-enterprise-dashboard :deep(.enterprise-dashboard-header .page-title) { font-size: clamp(1.35rem, 2vw, 1.7rem); letter-spacing: -.025em; }
.admin-enterprise-dashboard :deep(.enterprise-dashboard-header .page-description) { margin-top: .2rem; font-size: .8125rem; }
.enterprise-updated-at { margin-top: .45rem; color: rgb(var(--color-muted)); font-size: .6875rem; }

.enterprise-dashboard-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: flex-end; gap: .5rem; }
.enterprise-toolbar-field { display: grid; gap: .25rem; color: rgb(var(--color-muted)); font-size: .6875rem; font-weight: 700; letter-spacing: .025em; text-transform: uppercase; }
.enterprise-toolbar-field :deep(.select), .enterprise-toolbar-field :deep(.input) { min-height: 2.5rem; min-width: 9.25rem; padding-top: .375rem; padding-bottom: .375rem; font-size: .8125rem; font-weight: 500; letter-spacing: 0; text-transform: none; }
.enterprise-auto-refresh { display: flex; min-height: 2.5rem; align-items: center; gap: .5rem; border: 1px solid rgb(var(--color-border)); padding: 0 .625rem; color: rgb(var(--color-muted)); font-size: .75rem; font-weight: 600; }
.enterprise-auto-refresh input { width: .875rem; height: .875rem; accent-color: rgb(var(--color-primary)); }
.enterprise-refresh-button, .enterprise-reports-button, .enterprise-apply-button { min-height: 2.5rem; padding-inline: .75rem; font-size: .75rem; }
.enterprise-filter-panel { border: 1px solid rgb(var(--color-border)); background: rgb(var(--color-surface)); padding: .875rem 1rem; }
.enterprise-period-label { color: rgb(var(--color-muted)); font-size: .75rem; }
.enterprise-dashboard-alert { border-radius: 0 !important; box-shadow: none !important; }

.enterprise-section-title { display: flex; align-items: end; justify-content: space-between; gap: 1rem; margin-bottom: .625rem; }
.enterprise-section-title h2 { color: rgb(var(--color-ink)); font-size: .875rem; font-weight: 700; letter-spacing: -.01em; }
.enterprise-section-title p { margin-top: .15rem; color: rgb(var(--color-muted)); font-size: .75rem; }
.enterprise-attention-card { display: flex; min-height: 5.875rem; align-items: center; gap: .75rem; background: rgb(var(--color-surface)); padding: .875rem 1rem; color: rgb(var(--color-ink)); transition: background-color .16s ease; }
.enterprise-attention-card:hover { background: rgb(var(--color-canvas)); }
.enterprise-attention-icon { display: inline-flex; width: 2.25rem; height: 2.25rem; flex: none; align-items: center; justify-content: center; border: 1px solid currentColor; }
.enterprise-attention-card strong { display: block; margin-top: .1rem; font-size: 1.375rem; font-weight: 700; line-height: 1; letter-spacing: -.035em; }
.enterprise-attention-card p { color: rgb(var(--color-muted)); font-size: .6875rem; font-weight: 700; letter-spacing: .025em; text-transform: uppercase; }
.enterprise-attention-card[data-tone="danger"] { color: #dc2626; }.enterprise-attention-card[data-tone="warning"] { color: #d97706; }.enterprise-attention-card[data-tone="info"] { color: #2563eb; }.enterprise-attention-card[data-tone="primary"] { color: rgb(var(--color-primary)); }
.enterprise-attention-card[data-tone] p { color: rgb(var(--color-muted)); }.enterprise-attention-card[data-tone] .lucide:last-child { color: rgb(var(--color-muted)); }
:global(html.dark) .enterprise-attention-card[data-tone="danger"] { color: #f87171; }:global(html.dark) .enterprise-attention-card[data-tone="warning"] { color: #fbbf24; }:global(html.dark) .enterprise-attention-card[data-tone="info"] { color: #60a5fa; }

.enterprise-data-row { transition: background-color .16s ease; }.enterprise-data-row:hover { background: rgb(var(--color-canvas) / .65); }
.enterprise-shop-row { border-bottom: 1px solid rgb(var(--color-border)); }.enterprise-shop-row:last-child { border-bottom: 0; }
.enterprise-summary-card { display: block; min-height: 7.25rem; background: rgb(var(--color-surface)); padding: 1rem; color: rgb(var(--color-ink)); transition: background-color .16s ease; }.enterprise-summary-card:hover { background: rgb(var(--color-canvas)); }
.enterprise-summary-card p { margin-top: .6rem; font-size: 1.45rem; font-weight: 700; line-height: 1; letter-spacing: -.035em; }.enterprise-summary-card span { display: block; margin-top: .35rem; color: rgb(var(--color-muted)); font-size: .75rem; }
.enterprise-order-record { transition: background-color .16s ease; }.enterprise-order-record:hover { background: rgb(var(--color-canvas) / .65); }

@media (max-width: 639px) {
  .enterprise-dashboard-toolbar { width: 100%; justify-content: stretch; }
  .enterprise-dashboard-toolbar > :first-child { flex: 1 1 100%; }
  .enterprise-toolbar-field :deep(.select), .enterprise-toolbar-field :deep(.input) { width: 100%; min-width: 0; }
  .enterprise-auto-refresh { margin-right: auto; min-height: 2.75rem; }
  .enterprise-refresh-button, .enterprise-reports-button, .enterprise-apply-button { min-height: 2.75rem; }
  .admin-enterprise-dashboard :deep(.dashboard-metric) { min-height: 10.25rem; padding: 1rem; }
  .admin-enterprise-dashboard :deep(.dashboard-metric-value) { font-size: 1.65rem; }
  .enterprise-section-title { align-items: start; }
  .enterprise-attention-card { min-height: 5.5rem; }
  .enterprise-shop-row > div:last-child { width: 100%; }
  .enterprise-shop-row > div:last-child .btn { min-height: 2.75rem; flex: 1; }
}
</style>
