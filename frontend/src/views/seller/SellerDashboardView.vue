<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ArrowRight, CheckCircle2, Circle, ClipboardList, Package, Plus, RefreshCw, ShoppingBag, Truck, Warehouse } from 'lucide-vue-next'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice, formatDate } from '@/utils/format'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
import StatusTag from '@/components/StatusTag.vue'
const workspace = useSellerWorkspace()
const { t } = useI18n()
const error = ref('')
const metrics = computed(() => workspace.dashboard?.metrics)
const cards = computed(() => [
  { key: 'to_pack', count: metrics.value?.to_pack ?? 0, help: 'to_pack_help', icon: ClipboardList, name: 'seller-shop-orders', query: { status: 'confirmed' }, color: 'bg-blue-500/10 text-blue-600 dark:text-blue-400' },
  { key: 'to_ship', count: metrics.value?.to_ship ?? 0, help: 'to_ship_help', icon: Truck, name: 'seller-shop-orders', query: { status: 'processing' }, color: 'bg-violet-500/10 text-violet-600 dark:text-violet-400' },
  { key: 'low_stock', count: metrics.value?.low_stock ?? 0, help: 'low_stock_help', icon: Warehouse, name: 'seller-shop-inventory', query: { stock_status: 'low' }, color: 'bg-amber-500/10 text-amber-700 dark:text-amber-400' },
  { key: 'live_products', count: metrics.value?.active_products ?? 0, help: 'live_products_help', icon: Package, name: 'seller-shop-products', query: {}, color: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' }
])
const tasks = computed(() => [
  { count: metrics.value?.to_pack ?? 0, label: 'prepare_orders', help: 'prepare_help', name: 'seller-shop-orders', query: { status: 'confirmed' }, icon: ClipboardList },
  { count: metrics.value?.to_ship ?? 0, label: 'dispatch_orders', help: 'dispatch_help', name: 'seller-shop-orders', query: { status: 'processing' }, icon: Truck },
  { count: metrics.value?.low_stock ?? 0, label: 'check_stock', help: 'stock_help', name: 'seller-shop-inventory', query: { stock_status: 'low' }, icon: Warehouse }
].filter(task => task.count > 0))
async function load() {
  error.value = ''
  try { await workspace.loadOverview() }
  catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
}
watch(() => workspace.selectedId, () => void load(), { immediate: true })
</script>
<template>
  <div class="space-y-7">
    <SellerPageHeader :title="t('seller.overview')" :description="t('seller.overview_help')">
      <button v-if="workspace.currentShop" type="button" class="btn-secondary" :disabled="workspace.overviewLoading" :aria-label="t('actions.refresh')" @click="load"><RefreshCw :size="17" :class="{ 'animate-spin': workspace.overviewLoading }" /></button>
      <RouterLink v-if="workspace.selectedId" :to="{ name: 'seller-shop-product-create', params: { id: workspace.selectedId } }" class="btn-primary"><Plus :size="18" />{{ t('seller.add_product') }}</RouterLink>
    </SellerPageHeader>
    <section v-if="!workspace.currentShop" class="card flex flex-col items-center px-6 py-16 text-center"><ShoppingBag :size="32" class="mb-5 text-primary" /><h2 class="text-xl font-bold">{{ t('seller.no_shop') }}</h2><p class="mt-3 max-w-md text-sm leading-relaxed text-muted">{{ t('seller.no_shop_help') }}</p><RouterLink to="/seller/application" class="btn-primary mt-6"><Plus :size="18" />{{ t('seller.add_shop') }}</RouterLink></section>
    <p v-else-if="error" role="alert" class="card p-6 text-sm text-red-600 dark:text-red-400">{{ error }} <button class="ml-2 underline" @click="load">{{ t('actions.retry') }}</button></p>
    <div v-else-if="workspace.overviewLoading && !metrics" class="grid animate-pulse gap-5 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true"><div v-for="i in 4" :key="i" class="h-40 rounded-2xl bg-border-gray/40"></div><span class="sr-only">{{ t('common.loading') }}</span></div>
    <template v-else-if="workspace.dashboard && metrics">
      <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <RouterLink v-for="card in cards" :key="card.key" :to="{ name: card.name, params: { id: workspace.selectedId }, query: card.query }" class="card group rounded-2xl p-5 transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
          <div class="flex items-center justify-between"><span class="flex h-10 w-10 items-center justify-center rounded-xl" :class="card.color"><component :is="card.icon" :size="20" /></span><ArrowRight :size="16" class="text-muted group-hover:text-primary" /></div>
          <p class="mt-5 text-3xl font-bold tabular-nums tracking-tight">{{ card.count }}</p><p class="mt-1 text-sm font-semibold">{{ t('seller.' + card.key) }}</p><p class="mt-2 text-xs leading-relaxed text-muted">{{ t('seller.' + card.help) }}</p>
        </RouterLink>
      </div>
      <section v-if="!metrics.products || !metrics.in_stock" class="card rounded-2xl p-5 sm:p-6">
        <h2 class="font-semibold">{{ t('seller.setup') }}</h2><p class="mt-1 text-sm text-muted">{{ t('seller.setup_help') }}</p>
        <div class="mt-5 grid gap-3 md:grid-cols-3"><div class="flex items-center gap-3 rounded-xl bg-emerald-500/5 p-4"><CheckCircle2 :size="20" class="shrink-0 text-emerald-600 dark:text-emerald-400" /><span class="text-sm">{{ t('seller.approved') }}</span></div>
        <RouterLink :to="{ name: 'seller-shop-product-create', params: { id: workspace.selectedId } }" class="flex items-center gap-3 rounded-xl bg-canvas p-4 text-sm"><component :is="metrics.products ? CheckCircle2 : Circle" :size="20" class="shrink-0 text-primary" />{{ t('seller.first_product') }}</RouterLink>
        <RouterLink :to="{ name: 'seller-shop-inventory', params: { id: workspace.selectedId } }" class="flex items-center gap-3 rounded-xl bg-canvas p-4 text-sm"><component :is="metrics.in_stock ? CheckCircle2 : Circle" :size="20" class="shrink-0 text-primary" />{{ t('seller.stock_ready') }}</RouterLink></div>
      </section>
      <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-6">
          <section class="card overflow-hidden rounded-2xl">
            <div class="border-b border-border-gray p-5 sm:px-6"><h2 class="font-semibold">{{ t('seller.attention') }}</h2><p class="mt-1 text-xs text-muted">{{ t('seller.attention_help') }}</p></div>
            <div v-if="!tasks.length" class="flex items-start gap-4 p-6"><CheckCircle2 :size="24" class="shrink-0 text-emerald-600 dark:text-emerald-400" /><div><h3 class="text-sm font-semibold">{{ t('seller.all_clear') }}</h3><p class="mt-1 text-sm leading-relaxed text-muted">{{ t('seller.all_clear_help') }}</p></div></div>
            <div v-else class="divide-y divide-border-gray"><RouterLink v-for="task in tasks" :key="task.label" :to="{ name: task.name, params: { id: workspace.selectedId }, query: task.query }" class="flex items-center gap-4 p-5 transition hover:bg-canvas sm:px-6"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/5 text-primary"><component :is="task.icon" :size="19" /></span><div class="min-w-0 flex-1"><h3 class="text-sm font-semibold">{{ t('seller.' + task.label) }}</h3><p class="mt-1 text-xs leading-relaxed text-muted">{{ t('seller.' + task.help) }}</p></div><span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">{{ task.count }}</span><ArrowRight :size="16" class="shrink-0 text-muted" /></RouterLink></div>
          </section>
          <section class="card overflow-hidden rounded-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-border-gray p-5 sm:px-6"><h2 class="font-semibold">{{ t('seller.recent_orders') }}</h2><RouterLink :to="{ name: 'seller-shop-orders', params: { id: workspace.selectedId } }" class="text-xs font-semibold text-primary">{{ t('actions.view_all') }}</RouterLink></div>
            <div v-if="!workspace.dashboard.recent_orders.length" class="p-10 text-center"><ShoppingBag :size="28" class="mx-auto mb-3 text-muted" /><h3 class="text-sm font-semibold">{{ t('seller.no_orders') }}</h3><p class="mt-2 text-xs text-muted">{{ t('seller.no_orders_help') }}</p></div>
            <div class="divide-y divide-border-gray"><RouterLink v-for="order in workspace.dashboard.recent_orders" :key="order.id" :to="{ name: 'seller-shop-order-detail', params: { id: workspace.selectedId, orderId: order.id } }" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-canvas sm:px-6"><div class="min-w-0"><p class="text-sm font-semibold">{{ order.customer_name || order.shop_order_number }}</p><p class="mt-1 text-xs text-muted">{{ order.shop_order_number }} · {{ formatDate(order.placed_at) }}</p></div><div class="flex items-center gap-3"><StatusTag :status="order.status" /><span class="text-sm font-semibold tabular-nums">{{ formatPrice(order.total, order.currency) }}</span><ArrowRight :size="15" class="text-muted" /></div></RouterLink></div>
          </section>
        </div>
        <div class="space-y-6">
          <section class="card overflow-hidden rounded-2xl"><div class="flex items-center gap-2 border-b border-border-gray p-5"><Warehouse :size="18" class="text-amber-600 dark:text-amber-400" /><h2 class="font-semibold">{{ t('seller.stock_alerts') }}</h2></div><p v-if="!workspace.dashboard.low_stock.length" class="p-5 text-sm leading-relaxed text-muted">{{ t('seller.no_stock_alerts') }}</p><div class="divide-y divide-border-gray"><RouterLink v-for="item in workspace.dashboard.low_stock" :key="item.id" :to="{ name: 'seller-shop-inventory', params: { id: workspace.selectedId }, query: { stock_status: 'low', q: item.sku ?? item.name } }" class="flex items-center justify-between gap-3 p-5 hover:bg-canvas"><div class="min-w-0"><p class="truncate text-sm font-medium">{{ item.name }}</p><p class="mt-1 text-xs text-muted">{{ item.sku }}</p></div><span class="shrink-0 rounded-lg bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-700 dark:text-amber-400">{{ t('seller.available_count', { count: item.available }) }}</span></RouterLink></div></section>
          <section class="rounded-2xl border border-border-gray p-5"><dl class="space-y-4 text-sm"><div v-for="stat in [{ key: 'on_way', count: metrics.on_way }, { key: 'hidden_products', count: metrics.inactive_products }, { key: 'returns', count: metrics.returns }]" :key="stat.key" class="flex justify-between gap-3"><dt class="text-muted">{{ t('seller.' + stat.key) }}</dt><dd class="font-semibold tabular-nums">{{ stat.count }}</dd></div></dl></section>
        </div>
      </div>
    </template>
  </div>
</template>
