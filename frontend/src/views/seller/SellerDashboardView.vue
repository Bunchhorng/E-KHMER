<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ArrowRight, ChartNoAxesColumnIncreasing, CheckCircle2, ChevronRight, Circle, ClipboardCheck, ClipboardList, EyeOff, Package, Plus, RefreshCw, ShoppingBag, Truck, Warehouse } from 'lucide-vue-next'
import { sellerApi } from '@/api/seller'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice, formatDate } from '@/utils/format'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
import StatusTag from '@/components/StatusTag.vue'
import AssetImage from '@/components/AssetImage.vue'
const workspace = useSellerWorkspace()
const { t } = useI18n()
const error = ref('')
const showAllTasks = ref(false)
const stockImages = ref<Record<number, string | null>>({})
const metrics = computed(() => workspace.dashboard?.metrics)
const cards = computed(() => [
  { key: 'to_pack', count: metrics.value?.to_pack ?? 0, help: 'to_pack_help', icon: ClipboardList, name: 'seller-shop-orders', query: { status: 'confirmed' }, tone: 'blue' },
  { key: 'to_ship', count: metrics.value?.to_ship ?? 0, help: 'to_ship_help', icon: Truck, name: 'seller-shop-orders', query: { status: 'processing' }, tone: 'violet' },
  { key: 'low_stock', count: metrics.value?.low_stock ?? 0, help: 'low_stock_help', icon: Warehouse, name: 'seller-shop-inventory', query: { stock_status: 'low' }, tone: 'amber' },
  { key: 'live_products', count: metrics.value?.active_products ?? 0, help: 'live_products_help', icon: Package, name: 'seller-shop-products', query: {}, tone: 'green' }
])
const tasks = computed(() => [
  { count: metrics.value?.to_pack ?? 0, label: 'prepare_orders', help: 'prepare_help', name: 'seller-shop-orders', query: { status: 'confirmed' }, icon: ClipboardList },
  { count: metrics.value?.to_ship ?? 0, label: 'dispatch_orders', help: 'dispatch_help', name: 'seller-shop-orders', query: { status: 'processing' }, icon: Truck },
  { count: metrics.value?.low_stock ?? 0, label: 'check_stock', help: 'stock_help', name: 'seller-shop-inventory', query: { stock_status: 'low' }, icon: Warehouse }
].filter(task => showAllTasks.value || task.count > 0))
const shopStats = computed(() => [
  { key: 'on_way', count: metrics.value?.on_way ?? 0, icon: Truck, tone: 'blue', name: 'seller-shop-orders', query: { status: 'shipped' } },
  { key: 'hidden_products', count: metrics.value?.inactive_products ?? 0, icon: EyeOff, tone: 'violet', name: 'seller-shop-products', query: {} },
  { key: 'returns', count: metrics.value?.returns ?? 0, icon: Package, tone: 'rose', name: 'seller-shop-returns', query: {} }
])
async function loadStockImages(shopId: number) {
  const items = workspace.dashboard?.low_stock ?? []
  // Reuse catalog photos without holding up the overview if a photo is unavailable.
  const results = await Promise.allSettled(items.map(async item => {
    const { data } = await sellerApi.listProducts(shopId, { q: item.sku || item.name })
    return [item.id, data.data.find(product => product.name === item.name)?.cover_image ?? null] as const
  }))
  if (workspace.selectedId !== shopId || workspace.dashboard?.low_stock !== items) return
  stockImages.value = Object.fromEntries(results.flatMap(result => result.status === 'fulfilled' ? [result.value] : []))
}
async function load() {
  error.value = ''
  stockImages.value = {}
  try {
    await workspace.loadOverview()
    if (workspace.selectedId) void loadStockImages(workspace.selectedId)
  }
  catch (cause) { error.value = extractErrorMessage(cause, t('seller.load_failed')) }
}
watch(() => workspace.selectedId, () => void load(), { immediate: true })
</script>
<template>
  <div class="seller-overview">
    <SellerPageHeader class="overview-heading" :title="t('seller.overview')" :description="t('seller.overview_help')">
      <button v-if="workspace.currentShop" type="button" class="btn-secondary overview-refresh" :disabled="workspace.overviewLoading" :aria-label="t('actions.refresh')" @click="load"><RefreshCw :size="20" :class="{ 'animate-spin': workspace.overviewLoading }" /></button>
      <RouterLink v-if="workspace.selectedId" :to="{ name: 'seller-shop-product-create', params: { id: workspace.selectedId } }" class="btn-primary overview-add"><Plus :size="22" :stroke-width="1.7" />{{ t('seller.add_product') }}</RouterLink>
    </SellerPageHeader>
    <section v-if="!workspace.currentShop" class="card flex flex-col items-center px-6 py-16 text-center"><ShoppingBag :size="32" class="mb-5 text-primary" /><h2 class="text-xl font-bold">{{ t('seller.no_shop') }}</h2><p class="mt-3 max-w-md text-sm leading-relaxed text-muted">{{ t('seller.no_shop_help') }}</p><RouterLink to="/seller/application" class="btn-primary mt-6"><Plus :size="18" />{{ t('seller.add_shop') }}</RouterLink></section>
    <p v-else-if="error" role="alert" class="card p-6 text-sm text-red-600 dark:text-red-400">{{ error }} <button class="ml-2 underline" @click="load">{{ t('actions.retry') }}</button></p>
    <div v-else-if="workspace.overviewLoading && !metrics" class="grid animate-pulse gap-5 sm:grid-cols-2 xl:grid-cols-4" aria-busy="true"><div v-for="i in 4" :key="i" class="h-40 rounded-2xl bg-border-gray/40"></div><span class="sr-only">{{ t('common.loading') }}</span></div>
    <template v-else-if="workspace.dashboard && metrics">
      <div class="overview-metrics grid grid-cols-2 xl:grid-cols-4" :aria-label="t('seller.shop_overview')">
        <RouterLink v-for="card in cards" :key="card.key" :to="{ name: card.name, params: { id: workspace.selectedId }, query: card.query }" class="card metric-card group relative overflow-hidden transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md" :data-tone="card.tone">
          <div class="flex items-center justify-between gap-3"><span class="overview-icon metric-icon"><component :is="card.icon" :size="27" :stroke-width="1.8" /></span><svg class="metric-wave" viewBox="0 0 112 48" fill="none" aria-hidden="true"><path d="M2 39C14 27 21 10 35 16C48 22 56 36 68 27C81 17 85 3 98 10C103 12 107 15 110 18" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" /></svg></div>
          <p class="metric-value font-bold tabular-nums tracking-tight">{{ card.count }}</p><h2 class="metric-label font-semibold">{{ t('seller.' + card.key) }}</h2><p class="metric-help text-muted">{{ t('seller.' + card.help) }}</p>
          <span class="overview-chevron metric-chevron" aria-hidden="true"><ChevronRight :size="21" :stroke-width="1.8" /></span>
        </RouterLink>
      </div>
      <section v-if="!metrics.products || !metrics.in_stock" class="card rounded-2xl p-5 sm:p-6">
        <h2 class="font-semibold">{{ t('seller.setup') }}</h2><p class="mt-1 text-sm text-muted">{{ t('seller.setup_help') }}</p>
        <div class="mt-5 grid gap-3 md:grid-cols-3"><div class="flex items-center gap-3 rounded-xl bg-emerald-500/5 p-4"><CheckCircle2 :size="20" class="shrink-0 text-emerald-600 dark:text-emerald-400" /><span class="text-sm">{{ t('seller.approved') }}</span></div>
        <RouterLink :to="{ name: 'seller-shop-product-create', params: { id: workspace.selectedId } }" class="flex items-center gap-3 rounded-xl bg-canvas p-4 text-sm"><component :is="metrics.products ? CheckCircle2 : Circle" :size="20" class="shrink-0 text-primary" />{{ t('seller.first_product') }}</RouterLink>
        <RouterLink :to="{ name: 'seller-shop-inventory', params: { id: workspace.selectedId } }" class="flex items-center gap-3 rounded-xl bg-canvas p-4 text-sm"><component :is="metrics.in_stock ? CheckCircle2 : Circle" :size="20" class="shrink-0 text-primary" />{{ t('seller.stock_ready') }}</RouterLink></div>
      </section>
      <div class="overview-panels grid items-start">
        <section class="card next-steps overflow-hidden" aria-labelledby="seller-tasks-heading">
          <div class="overview-panel-heading flex items-center justify-between gap-3"><div class="flex min-w-0 items-center gap-4"><span class="overview-icon" data-tone="blue"><ClipboardCheck :size="25" :stroke-width="1.8" /></span><div class="min-w-0"><h2 id="seller-tasks-heading">{{ t('seller.attention') }}</h2><p>{{ t('seller.attention_help') }}</p></div></div><button type="button" class="overview-link" :aria-expanded="showAllTasks" aria-controls="seller-task-list" @click="showAllTasks = !showAllTasks">{{ t(showAllTasks ? 'seller.show_fewer_tasks' : 'seller.view_all_tasks') }}<ArrowRight :size="17" aria-hidden="true" /></button></div>
          <div id="seller-task-list" class="overview-task-list">
            <div v-if="!tasks.length" class="flex items-start gap-4 p-4"><CheckCircle2 :size="25" class="shrink-0 text-emerald-600 dark:text-emerald-400" /><div><h3 class="text-sm font-semibold">{{ t('seller.all_clear') }}</h3><p class="mt-1 text-xs leading-relaxed text-muted">{{ t('seller.all_clear_help') }}</p></div></div>
            <RouterLink v-for="task in tasks" :key="task.label" :to="{ name: task.name, params: { id: workspace.selectedId }, query: task.query }" class="overview-task flex items-center gap-4 rounded-xl border border-border-gray transition hover:bg-canvas"><span class="overview-icon" data-tone="blue"><component :is="task.icon" :size="25" :stroke-width="1.8" /></span><div class="min-w-0 flex-1"><h3 class="text-sm font-semibold">{{ t('seller.' + task.label) }}</h3><p class="mt-0.5 text-xs leading-relaxed text-muted">{{ t('seller.' + task.help) }}</p></div><span class="task-count flex shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">{{ task.count }}</span><span class="overview-chevron" aria-hidden="true"><ChevronRight :size="19" /></span></RouterLink>
          </div>
        </section>
        <section class="card stock-alerts overflow-hidden" aria-labelledby="seller-stock-heading">
          <div class="overview-panel-heading compact-heading flex items-center justify-between gap-3 border-b border-border-gray"><div class="flex min-w-0 items-center gap-4"><span class="overview-icon" data-tone="amber"><Warehouse :size="24" :stroke-width="1.8" /></span><h2 id="seller-stock-heading">{{ t('seller.stock_alerts') }}</h2></div><RouterLink :to="{ name: 'seller-shop-inventory', params: { id: workspace.selectedId }, query: { stock_status: 'low' } }" class="overview-link">{{ t('actions.view_all') }}<ArrowRight :size="17" aria-hidden="true" /></RouterLink></div>
          <p v-if="!workspace.dashboard.low_stock.length" class="p-5 text-sm leading-relaxed text-muted">{{ t('seller.no_stock_alerts') }}</p>
          <div class="divide-y divide-border-gray"><RouterLink v-for="item in workspace.dashboard.low_stock" :key="item.id" :to="{ name: 'seller-shop-inventory', params: { id: workspace.selectedId }, query: { stock_status: 'low', q: item.sku ?? item.name } }" class="stock-alert flex items-center gap-4 hover:bg-canvas"><AssetImage :src="stockImages[item.id]" :alt="item.name" class="stock-thumbnail rounded-xl border border-border-gray bg-surface" /><div class="min-w-0 flex-1"><p class="truncate text-[14px] font-medium" :title="item.name">{{ item.name }}</p><p class="mt-1 truncate text-xs text-muted">{{ item.sku }}</p></div><span class="stock-count shrink-0 rounded-lg bg-amber-500/10 text-xs font-medium text-amber-700 dark:text-amber-400">{{ t('seller.available_count', { count: item.available }) }}</span></RouterLink></div>
        </section>
        <section class="card recent-orders min-w-0 overflow-hidden" aria-labelledby="seller-orders-heading">
          <div class="overview-panel-heading flex items-center justify-between gap-3"><div class="flex min-w-0 items-center gap-4"><span class="overview-icon" data-tone="blue"><ShoppingBag :size="27" :stroke-width="1.7" /></span><div><h2 id="seller-orders-heading">{{ t('seller.recent_orders') }}</h2><p>{{ t('seller.recent_orders_help') }}</p></div></div><RouterLink :to="{ name: 'seller-shop-orders', params: { id: workspace.selectedId } }" class="overview-link">{{ t('actions.view_all') }}<ArrowRight :size="17" aria-hidden="true" /></RouterLink></div>
          <div v-if="!workspace.dashboard.recent_orders.length" class="p-10 text-center"><ShoppingBag :size="28" class="mx-auto mb-3 text-muted" /><h3 class="text-sm font-semibold">{{ t('seller.no_orders') }}</h3><p class="mt-2 text-xs text-muted">{{ t('seller.no_orders_help') }}</p></div>
          <div v-else class="overview-orders-table">
            <table class="w-full text-left"><caption class="sr-only">{{ t('seller.recent_orders') }}</caption><thead><tr><th scope="col">{{ t('seller.customer') }}</th><th scope="col">{{ t('seller.order_number_short') }}</th><th scope="col">{{ t('seller.date') }}</th><th scope="col">{{ t('order.status') }}</th><th scope="col">{{ t('seller.amount') }}</th><th scope="col"><span class="sr-only">{{ t('actions.view') }}</span></th></tr></thead><tbody><tr v-for="(order, index) in workspace.dashboard.recent_orders" :key="order.id"><td><div class="flex min-w-0 items-center gap-4"><span class="customer-avatar flex shrink-0 items-center justify-center rounded-full font-semibold" :class="index % 3 === 0 ? 'bg-primary/10 text-primary' : index % 3 === 1 ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'">{{ (order.customer_name || order.shop_order_number).charAt(0).toUpperCase() }}</span><span class="customer-name truncate font-medium">{{ order.customer_name || order.shop_order_number }}</span></div></td><td class="text-muted"><RouterLink :to="{ name: 'seller-shop-order-detail', params: { id: workspace.selectedId, orderId: order.id } }" class="hover:text-primary hover:underline">{{ order.shop_order_number }}</RouterLink></td><td class="whitespace-nowrap text-muted">{{ formatDate(order.placed_at) }}</td><td><StatusTag :status="order.status" /></td><td class="whitespace-nowrap font-semibold tabular-nums">{{ formatPrice(order.total, order.currency) }}</td><td><RouterLink :to="{ name: 'seller-shop-order-detail', params: { id: workspace.selectedId, orderId: order.id } }" class="overview-chevron" :aria-label="t('seller.view_order', { number: order.shop_order_number })"><ChevronRight :size="19" /></RouterLink></td></tr></tbody></table>
          </div>
        </section>
        <section class="card shop-summary overflow-hidden" aria-labelledby="seller-summary-heading">
          <div class="overview-panel-heading compact-heading flex items-center gap-4"><span class="overview-icon" data-tone="blue"><ChartNoAxesColumnIncreasing :size="23" :stroke-width="1.8" /></span><h2 id="seller-summary-heading">{{ t('seller.shop_overview') }}</h2></div>
          <ul class="overview-summary-list rounded-xl border border-border-gray"><li v-for="stat in shopStats" :key="stat.key"><RouterLink :to="{ name: stat.name, params: { id: workspace.selectedId }, query: stat.query }" class="summary-row flex items-center gap-4 transition hover:bg-canvas"><span class="overview-icon" :data-tone="stat.tone"><component :is="stat.icon" :size="23" :stroke-width="1.8" /></span><span class="min-w-0 flex-1 text-sm text-muted">{{ t('seller.' + stat.key) }}</span><span class="text-base font-semibold tabular-nums">{{ stat.count }}</span><ChevronRight :size="18" class="shrink-0 text-muted" aria-hidden="true" /></RouterLink></li></ul>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.seller-overview { display: flex; flex-direction: column; gap: 18px; }
.overview-heading { margin: 2px 2px 4px; min-height: 84px; }
.overview-heading :deep(h1) { font-size: 44px; line-height: 1.12; font-weight: 750; letter-spacing: -1.6px; }
.overview-heading :deep(p) { margin-top: 7px; font-size: 18px; line-height: 1.6; }
.overview-heading :deep(> div:last-child) { gap: 12px; }
.overview-refresh { min-width: 60px; height: 50px; border-radius: 12px; padding: 0; box-shadow: 0 2px 4px rgb(41 83 145 / 4%); }
.overview-add { min-height: 50px; gap: 12px; border: 1px solid rgb(255 255 255 / 10%); border-radius: 11px; padding-inline: 24px; font-size: 18px; background: linear-gradient(125deg, #1762ff, #2c69f5); box-shadow: 0 4px 8px rgb(30 99 255 / 9%); }
.overview-metrics { gap: 14px; }
.metric-card { min-height: 224px; border-radius: 15px; padding: 16px 24px 20px; }
[data-tone='blue'] { --tone: 30 99 255; --tone-wave: 133 173 255; }
[data-tone='violet'] { --tone: 131 41 244; --tone-wave: 171 132 255; }
[data-tone='amber'] { --tone: 220 112 0; --tone-wave: 255 182 109; }
[data-tone='green'] { --tone: 0 155 116; --tone-wave: 126 223 194; }
[data-tone='rose'] { --tone: 221 47 86; }
.overview-icon { display: flex; width: 52px; height: 52px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 15px; background: rgb(var(--tone) / 9%); color: rgb(var(--tone)); }
.metric-icon { width: 58px; height: 58px; border-radius: 16px; }
.metric-wave { width: 108px; height: 47px; margin-top: 16px; color: rgb(var(--tone-wave)); mask-image: linear-gradient(90deg, #000 65%, transparent); }
.metric-value { margin-top: 10px; font-size: 36px; line-height: 1.1; }
.metric-label { margin-top: 3px; padding-right: 32px; font-size: 19px; line-height: 1.3; letter-spacing: -.4px; }
.metric-help { max-width: 210px; margin-top: 4px; padding-right: 20px; font-size: 16px; line-height: 1.5; }
.overview-chevron { display: inline-flex; width: 35px; height: 35px; flex-shrink: 0; align-items: center; justify-content: center; border: 1px solid rgb(var(--color-border)); border-radius: 50%; background: rgb(var(--color-canvas) / 70%); color: rgb(var(--color-muted)); transition: color .15s, background .15s; }
a:hover .overview-chevron, a.overview-chevron:hover { background: rgb(var(--color-primary) / 8%); color: rgb(var(--color-primary)); }
.metric-chevron { position: absolute; right: 22px; top: 110px; width: 40px; height: 40px; }
.overview-panels { grid-template-columns: minmax(0, 2.2fr) minmax(340px, 1fr); gap: 16px 14px; }
.overview-panels > .card { border-radius: 16px; }
.overview-panel-heading { min-height: 82px; padding: 14px 18px 10px; }
.overview-panel-heading h2 { font-size: 20px; font-weight: 650; line-height: 1.35; letter-spacing: -.5px; }
.overview-panel-heading p { margin-top: 2px; color: rgb(var(--color-muted)); font-size: 15px; line-height: 1.5; }
.overview-link { display: inline-flex; flex-shrink: 0; align-items: center; gap: 10px; font-size: 14px; font-weight: 500; color: rgb(var(--color-primary)); }
.overview-link:hover { text-decoration: underline; text-underline-offset: 4px; }
.overview-task-list { display: flex; flex-direction: column; gap: 10px; padding: 0 18px 10px; }
.overview-task { min-height: 78px; padding: 12px 14px; }
.task-count { min-width: 34px; height: 34px; margin-right: 0; padding-inline: 8px; }
.compact-heading { min-height: 70px; padding: 12px 18px; }
.compact-heading .overview-icon { width: 42px; height: 42px; border-radius: 12px; }
.stock-alert { min-height: 98px; padding: 14px 18px; }
.stock-thumbnail { width: 68px; height: 68px; }
.stock-count { padding: 7px 10px; }
.overview-orders-table { position: relative; padding: 0 18px 8px; overflow-x: auto; }
.overview-orders-table table { font-size: 14px; line-height: 1.45; }
.overview-orders-table th { padding: 12px 12px; background: rgb(var(--color-canvas) / 80%); font-size: 11px; font-weight: 500; text-transform: uppercase; white-space: nowrap; color: rgb(var(--color-muted)); }
.overview-orders-table th:first-child { border-radius: 9px 0 0 9px; padding-left: 16px; }
.overview-orders-table th:last-child { border-radius: 0 9px 9px 0; }
.overview-orders-table td { height: 47px; padding: 5px 12px; border-bottom: 1px solid rgb(var(--color-border)); }
.overview-orders-table tr:last-child td { border-bottom: 0; }
.overview-orders-table tbody tr:hover { background: rgb(var(--color-canvas) / 60%); }
.overview-orders-table td:first-child { padding-left: 12px; }
.overview-orders-table td:last-child { padding-right: 6px; }
.overview-orders-table :deep(.text-xs) { font-size: 13px; }
.customer-avatar { width: 36px; height: 36px; font-size: 18px; }
.customer-name { max-width: 145px; }
.overview-summary-list { margin: -6px 12px 12px; padding: 0 12px; }
.overview-summary-list > li + li { border-top: 1px solid rgb(var(--color-border)); }
.summary-row { min-height: 57px; }
.summary-row .overview-icon { width: 42px; height: 42px; border-radius: 12px; }
:global(html.dark .seller-overview .overview-icon) { color: rgb(var(--tone-wave, var(--tone))); background: rgb(var(--tone) / 15%); }
@media (min-width: 1500px) { .overview-panels { grid-template-columns: minmax(0, 2.2fr) minmax(390px, 1fr); } }
@media (max-width: 1399px) { .overview-panels { grid-template-columns: minmax(0, 1fr); } .next-steps { order: 0; } .stock-alerts { order: 2; } .recent-orders { order: 1; } .shop-summary { order: 3; } }
@media (min-width: 768px) and (max-width: 1399px) { .overview-panels { grid-template-columns: repeat(2, minmax(0, 1fr)); } .next-steps, .recent-orders { grid-column: 1 / -1; } .metric-help { max-width: 260px; } }
@media (max-width: 767px) { .overview-heading { gap: 14px; } .overview-heading :deep(h1) { font-size: 34px; letter-spacing: -1px; } .overview-heading :deep(p) { font-size: 15px; } .overview-heading :deep(> div:last-child) { margin-bottom: 4px; } .overview-add { min-height: 44px; padding-inline: 16px; font-size: 15px; } .overview-refresh { min-width: 48px; height: 44px; } .metric-card { min-height: 210px; padding: 16px; } .metric-icon { width: 48px; height: 48px; } .metric-wave { width: 74px; } .metric-chevron { top: 98px; right: 12px; width: 32px; height: 32px; } .metric-value { font-size: 31px; } .metric-label { font-size: 17px; } .metric-help { padding-right: 0; font-size: 14px; } .overview-panel-heading h2 { font-size: 18px; } .overview-panel-heading p { font-size: 13px; } .overview-panel-heading, .compact-heading { padding-inline: 14px; } .overview-panel-heading > div { gap: 12px; } .overview-link { gap: 5px; font-size: 12px; } .overview-task-list { padding-inline: 12px; } .overview-task { gap: 10px; padding: 12px; } .overview-task .overview-icon { width: 42px; height: 46px; } .overview-task .overview-chevron { display: none; } .overview-task h3 { font-size: 14px; } .overview-task p { font-size: 12px; } .overview-panel-heading .overview-icon { width: 44px; height: 48px; } .overview-orders-table { padding-inline: 12px; } .overview-orders-table table { min-width: 715px; } }
@media (max-width: 399px) { .overview-metrics { gap: 10px; } .metric-card { padding: 14px 12px; } .metric-label { font-size: 15px; } .metric-help { font-size: 13px; } .metric-wave { width: 60px; } .overview-panel-heading { flex-wrap: wrap; row-gap: 8px; } .next-steps .overview-link { margin: 0 0 8px 56px; } .stock-count { padding-inline: 7px; font-size: 12px; } .stock-alert { gap: 10px; padding-inline: 12px; } .stock-thumbnail { width: 54px; height: 54px; } }
@media (prefers-reduced-motion: reduce) { .metric-card { transition: none; } .metric-card:hover { transform: none; } }
</style>
