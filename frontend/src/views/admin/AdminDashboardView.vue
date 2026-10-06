<script setup lang="ts">
import type { Component } from 'vue'
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowUpRight, Check, CircleDollarSign, Clock3, RefreshCw, ShoppingBag, Store, TrendingDown, TrendingUp, Users, X } from 'lucide-vue-next'
import RevenueChart from '@/components/admin/charts/RevenueChart.vue'
import OrdersTrendChart from '@/components/admin/charts/OrdersTrendChart.vue'
import OrderStatusChart from '@/components/admin/charts/OrderStatusChart.vue'
import BaseModal from '@/components/BaseModal.vue'
import StatusTag from '@/components/StatusTag.vue'
import { adminApi, type AdminDashboard, type AdminOrderItem, type AdminShop } from '@/api/admin'
import { formatCompactNumber, formatDate, formatPrice } from '@/utils/format'

interface MetricCard { key: string; label: string; value: string; delta: number | null; icon: Component; tone: string }
const loading = ref(true)
const error = ref(false)
const range = ref('30')
const dashboard = ref<AdminDashboard | null>(null)
const recentOrders = ref<AdminOrderItem[]>([])
const pendingShops = ref<AdminShop[]>([])
const shopCounts = ref({ total: 0, active: 0, pending: 0 })
const shopActionId = ref<number | null>(null)
const rejectShop = ref<AdminShop | null>(null)
const rejectionReason = ref('')
const ranges = [{ key: '7', label: 'Last 7 days' }, { key: '30', label: 'Last 30 days' }, { key: 'this_month', label: 'This month' }, { key: 'last_month', label: 'Last month' }]
const metrics = computed(() => dashboard.value?.metrics ?? null)
const cards = computed<MetricCard[]>(() => {
  const m = metrics.value
  if (!m) return []
  return [
    { key: 'shops', label: 'Total Shops', value: formatCompactNumber(shopCounts.value.total), delta: null, icon: Store, tone: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300' },
    { key: 'active', label: 'Active Shops', value: formatCompactNumber(shopCounts.value.active), delta: null, icon: Store, tone: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300' },
    { key: 'pending', label: 'Pending Shops', value: formatCompactNumber(shopCounts.value.pending), delta: null, icon: Clock3, tone: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300' },
    { key: 'customers', label: 'Total Customers', value: formatCompactNumber(m.customers_count), delta: m.customers_delta, icon: Users, tone: 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300' },
    { key: 'orders', label: 'Total Orders', value: formatCompactNumber(m.orders_count), delta: m.orders_delta, icon: ShoppingBag, tone: 'bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300' },
    { key: 'revenue', label: 'Revenue', value: formatPrice(m.total_revenue), delta: m.revenue_delta, icon: CircleDollarSign, tone: 'bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300' }
  ]
})
const labelFor = (date: string) => { const d = new Date(`${date}T00:00:00`); return Number.isNaN(d.getTime()) ? date : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) }
const revenueTrend = computed(() => (dashboard.value?.revenue_trend ?? []).map((item) => ({ label: labelFor(item.date), revenue: item.revenue })))
const ordersTrend = computed(() => (dashboard.value?.orders_trend ?? []).map((item) => ({ label: labelFor(item.date), orders: item.orders })))
const statuses = computed(() => dashboard.value?.status_distribution ?? [])
const topProducts = computed(() => dashboard.value?.top_selling_products.slice(0, 5) ?? [])
const activities = computed(() => [
  ...pendingShops.value.slice(0, 2).map((shop) => ({ id: `shop-${shop.id}`, icon: Store, tone: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300', title: 'New shop registration', detail: `${shop.name} is waiting for approval`, time: formatDate(shop.created_at) })),
  ...recentOrders.value.slice(0, 3).map((order) => ({ id: `order-${order.id}`, icon: ShoppingBag, tone: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300', title: `Order ${order.order_number}`, detail: `${order.user?.name ?? 'Customer'} placed an order`, time: formatDate(order.placed_at) }))
])
async function loadDashboard() { loading.value = true; error.value = false; try { dashboard.value = (await adminApi.getDashboard(range.value)).data.data } catch { error.value = true } finally { loading.value = false } }
async function loadSupportingData() {
  const [orders, all, active, pending] = await Promise.all([adminApi.listOrders(), adminApi.listShops(), adminApi.listShops({ status: 'active' }), adminApi.listShops({ status: 'pending' })])
  recentOrders.value = orders.data.data.slice(0, 5); pendingShops.value = pending.data.data.slice(0, 5)
  shopCounts.value = { total: all.data.meta.total, active: active.data.meta.total, pending: pending.data.meta.total }
}
async function refresh() { await Promise.all([loadDashboard(), loadSupportingData().catch(() => undefined)]) }
async function setShopStatus(shop: AdminShop, status: 'active' | 'rejected') {
  if (status === 'rejected') { openRejectModal(shop); return }
  shopActionId.value = shop.id
  try { await adminApi.updateShopStatus(shop.id, status); await loadSupportingData() } finally { shopActionId.value = null }
}
function openRejectModal(shop: AdminShop) { rejectShop.value = shop; rejectionReason.value = '' }
function closeRejectModal() { rejectShop.value = null; rejectionReason.value = '' }
async function confirmRejection() {
  const shop = rejectShop.value
  if (!shop || !rejectionReason.value.trim()) return
  shopActionId.value = shop.id
  try { await adminApi.updateShopStatus(shop.id, 'rejected', rejectionReason.value.trim()); await loadSupportingData(); closeRejectModal() } finally { shopActionId.value = null }
}
onMounted(() => { void refresh() })
</script>

<template>
  <div class="mx-auto max-w-[1600px] space-y-4 sm:space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div><h1 class="text-2xl font-bold tracking-tight text-ink sm:text-[27px]">Super Admin Dashboard</h1><p class="mt-1 text-sm text-gray-500 dark:text-muted">Welcome back, Admin! Here’s what’s happening with your marketplace today.</p></div>
      <div class="flex items-center gap-2"><select v-model="range" class="select h-9 min-w-36 !py-1.5 text-xs" @change="loadDashboard"><option v-for="option in ranges" :key="option.key" :value="option.key">{{ option.label }}</option></select><button class="btn-icon h-9 w-9" title="Refresh dashboard" @click="refresh"><RefreshCw class="h-4 w-4" /></button></div>
    </section>

    <div v-if="loading" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6"><div v-for="i in 6" :key="i" class="card h-28 animate-pulse bg-gray-100 dark:bg-surface-hover"></div></div>
    <div v-else-if="error" class="card p-10 text-center"><p class="text-sm text-gray-500 dark:text-muted">Unable to load the dashboard data.</p><button class="btn-primary mt-4 !px-4 !py-2" @click="refresh"><RefreshCw class="h-4 w-4" /> Retry</button></div>

    <template v-else-if="metrics">
      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <article v-for="card in cards" :key="card.key" class="card min-w-0 p-3.5 sm:p-4"><div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="card.tone"><component :is="card.icon" class="h-5 w-5" /></div><div class="min-w-0"><p class="truncate text-xs font-medium text-gray-500 dark:text-muted">{{ card.label }}</p><p class="mt-1 truncate text-xl font-bold tracking-tight text-ink">{{ card.value }}</p><p v-if="card.delta !== null" class="mt-1 flex items-center gap-1 text-[11px] font-semibold" :class="card.delta >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'"><component :is="card.delta >= 0 ? TrendingUp : TrendingDown" class="h-3.5 w-3.5" /> {{ Math.abs(card.delta) }}% <span class="font-normal text-gray-400 dark:text-gray-500">vs last month</span></p><p v-else class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Marketplace total</p></div></div></article>
      </section>

      <section class="grid gap-4 xl:grid-cols-12">
        <article class="card p-4 xl:col-span-5"><div class="mb-2 flex items-start justify-between gap-3"><div><h2 class="font-semibold text-ink">Sales Overview</h2><p class="mt-0.5 text-2xl font-bold text-ink">{{ formatPrice(metrics.total_revenue) }} <span v-if="metrics.revenue_delta !== null" class="text-sm text-emerald-500">↑ {{ metrics.revenue_delta }}%</span></p><p class="text-xs text-gray-500 dark:text-muted">Total revenue from all orders</p></div><span class="chip text-[11px]">Last 30 days</span></div><RevenueChart :data="revenueTrend" /></article>
        <article class="card p-4 xl:col-span-4"><div class="mb-4 flex items-center justify-between"><div><h2 class="font-semibold text-ink">Marketplace Growth</h2><p class="mt-1 text-xs text-gray-500 dark:text-muted">Orders placed over time</p></div><span class="chip text-[11px]">Selected range</span></div><OrdersTrendChart :data="ordersTrend" /></article>
        <article class="card p-4 xl:col-span-3"><div class="mb-4 flex items-center justify-between"><h2 class="font-semibold text-ink">Order Status</h2><span class="text-xs text-gray-500 dark:text-muted">{{ metrics.orders_count }} total</span></div><OrderStatusChart :data="statuses" /></article>
      </section>

      <section class="grid gap-4 xl:grid-cols-12">
        <article class="card overflow-hidden xl:col-span-5"><div class="flex items-center justify-between px-4 pb-3 pt-4"><h2 class="font-semibold text-ink">Pending Shop Approvals <span class="ml-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-xs text-primary">{{ shopCounts.pending }}</span></h2><RouterLink :to="{ name: 'admin-shops' }" class="text-xs font-semibold text-primary hover:text-primary-dark">View all</RouterLink></div><div v-if="pendingShops.length" class="divide-y divide-border-gray"><div v-for="shop in pendingShops" :key="shop.id" class="flex items-center gap-3 px-4 py-3"><img v-if="shop.logo" :src="shop.logo" :alt="shop.name" class="h-9 w-9 rounded-lg border border-border-gray object-cover" /><div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-bold text-primary">{{ shop.name.charAt(0) }}</div><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-ink">{{ shop.name }}</p><p class="truncate text-xs text-gray-500 dark:text-muted">{{ shop.email ?? shop.city ?? shop.code }}</p></div><button class="btn-primary !px-3 !py-1.5 text-xs" :disabled="shopActionId === shop.id" @click="setShopStatus(shop, 'active')"><Check class="h-3.5 w-3.5" />Approve</button><button class="btn-outline !border-red-300 !px-2.5 !py-1.5 text-xs !text-red-500 hover:!bg-red-50" :disabled="shopActionId === shop.id" @click="setShopStatus(shop, 'rejected')"><X class="h-3.5 w-3.5" /></button></div></div><p v-else class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">No pending shop approvals.</p></article>
        <article class="card overflow-hidden xl:col-span-4"><div class="flex items-center justify-between px-4 pb-3 pt-4"><h2 class="font-semibold text-ink">Top Products by Sales</h2><RouterLink :to="{ name: 'admin-products' }" class="text-xs font-semibold text-primary hover:text-primary-dark">View all</RouterLink></div><div v-if="topProducts.length" class="divide-y divide-border-gray"><div v-for="(product, index) in topProducts" :key="product.product_id" class="flex items-center gap-3 px-4 py-2.5"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="index === 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600 dark:bg-surface-hover dark:text-muted'">{{ index + 1 }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-ink">{{ product.product_name }}</p><p class="text-xs text-gray-500 dark:text-muted">{{ product.total_qty }} units sold</p></div><p class="text-sm font-bold text-ink">{{ formatPrice(product.revenue) }}</p></div></div><p v-else class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">No sales data yet.</p></article>
        <article class="card overflow-hidden xl:col-span-3"><div class="flex items-center justify-between px-4 pb-3 pt-4"><h2 class="font-semibold text-ink">Recent Activities</h2><RouterLink :to="{ name: 'admin-notifications' }" class="text-xs font-semibold text-primary hover:text-primary-dark">View all</RouterLink></div><div v-if="activities.length" class="divide-y divide-border-gray"><div v-for="activity in activities" :key="activity.id" class="flex gap-3 px-4 py-2.5"><div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="activity.tone"><component :is="activity.icon" class="h-4 w-4" /></div><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-ink">{{ activity.title }}</p><p class="truncate text-[11px] text-gray-500 dark:text-muted">{{ activity.detail }}</p></div><span class="shrink-0 text-[10px] text-gray-400">{{ activity.time }}</span></div></div><p v-else class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">No recent activity.</p></article>
      </section>

      <section class="card overflow-hidden"><div class="flex items-center justify-between px-4 pb-3 pt-4"><div><h2 class="font-semibold text-ink">Recent Orders</h2><p class="mt-0.5 text-xs text-gray-500 dark:text-muted">Latest marketplace transactions</p></div><RouterLink :to="{ name: 'admin-orders' }" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-dark">View all <ArrowUpRight class="h-3.5 w-3.5" /></RouterLink></div><div class="overflow-x-auto"><table class="w-full min-w-[760px] text-sm"><thead class="border-y border-border-gray bg-canvas/60 text-left text-[11px] uppercase tracking-wide text-gray-500 dark:text-muted"><tr><th class="px-4 py-2.5">Order ID</th><th class="px-4 py-2.5">Customer</th><th class="px-4 py-2.5">Items</th><th class="px-4 py-2.5 text-right">Total</th><th class="px-4 py-2.5">Payment</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5">Date</th></tr></thead><tbody class="divide-y divide-border-gray"><tr v-if="!recentOrders.length"><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">No orders found.</td></tr><tr v-for="order in recentOrders" :key="order.id" class="hover:bg-canvas/50"><td class="px-4 py-3 font-semibold text-primary"><RouterLink :to="{ name: 'admin-order-detail', params: { id: order.id } }">{{ order.order_number }}</RouterLink></td><td class="px-4 py-3"><p class="font-medium text-ink">{{ order.user?.name ?? 'Guest customer' }}</p><p class="text-xs text-gray-500 dark:text-muted">{{ order.user?.email }}</p></td><td class="px-4 py-3 text-gray-600 dark:text-muted">{{ order.items_count }} items</td><td class="px-4 py-3 text-right font-semibold text-ink">{{ formatPrice(order.total) }}</td><td class="px-4 py-3"><StatusTag :status="order.payment_status" /></td><td class="px-4 py-3"><StatusTag :status="order.status" /></td><td class="px-4 py-3 text-xs text-gray-500 dark:text-muted">{{ formatDate(order.placed_at) }}</td></tr></tbody></table></div></section>
      <BaseModal :model-value="rejectShop !== null" size="sm" title="Reject shop application" @update:model-value="closeRejectModal">
        <p class="text-sm text-gray-600 dark:text-muted">
          Reject <strong class="text-ink">{{ rejectShop?.name }}</strong>? The applicant will receive the reason below.
        </p>
        <label class="mt-4 block text-sm font-medium text-ink" for="rejection-reason">Reason for rejection</label>
        <textarea
          id="rejection-reason"
          v-model="rejectionReason"
          class="input mt-2 min-h-24 resize-y"
          placeholder="Explain what the seller needs to update..."
          maxlength="500"
        />
        <template #footer>
          <div class="flex justify-end gap-2">
            <button type="button" class="btn-secondary btn-sm" :disabled="shopActionId !== null" @click="closeRejectModal">Cancel</button>
            <button type="button" class="btn-danger btn-sm" :disabled="!rejectionReason.trim() || shopActionId === rejectShop?.id" @click="confirmRejection"><X class="h-4 w-4" />Reject shop</button>
          </div>
        </template>
      </BaseModal>
    </template>
  </div>
</template>
