<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowRight, Search } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sellerApi, type SellerShopOrder } from '@/api/seller'
import { useSellerList } from '@/composables/useSellerList'
import BasePagination from '@/components/BasePagination.vue'
import StatusTag from '@/components/StatusTag.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
import SellerListState from '@/components/seller/SellerListState.vue'
import { formatDate, formatPrice } from '@/utils/format'
const route = useRoute()
const router = useRouter()
const shopId = Number(route.params.id)
const { t } = useI18n()
const query = ref(String(route.query.q ?? ''))
const status = ref(String(route.query.status ?? ''))
const tabs = [{ value: '', label: 'all_orders' }, { value: 'confirmed', label: 'needs_preparation' }, { value: 'processing', label: 'ready_dispatch' }, { value: 'shipped', label: 'on_way' }, { value: 'delivered', label: 'completed' }]
const { items: orders, loading, error, page, pageCount, pageSize, total, load } = useSellerList<SellerShopOrder>(
  page => sellerApi.listOrders(shopId, { q: query.value.trim() || undefined, status: status.value || undefined, page }), () => t('seller.load_failed')
)
const filtered = computed(() => !!query.value.trim() || !!status.value)
function chooseStatus(value: string) { void router.replace({ query: { ...route.query, status: value || undefined } }) }
async function reset() { query.value = ''; status.value = ''; await router.replace({ query: {} }); await load(1) }
function action(order: SellerShopOrder) {
  if (order.shipment?.status === 'returned') return t('seller.open_order')
  if (order.allowed_transitions.includes('processing')) return t('seller.prepare')
  if (order.allowed_transitions.includes('shipped')) return t('seller.dispatch')
  return t('seller.open_order')
}
watch(() => route.query.status, () => { status.value = String(route.query.status ?? ''); void load(1) })
onMounted(() => void load())
</script>
<template>
  <div class="space-y-6">
    <SellerPageHeader :title="t('seller.orders')" :description="t('seller.orders_help')" />
    <section class="card overflow-hidden rounded-2xl">
      <nav class="flex gap-1 overflow-x-auto border-b border-border-gray px-4 pt-3 sm:px-5" :aria-label="t('seller.all_statuses')"><button v-for="tab in tabs" :key="tab.value" type="button" class="shrink-0 border-b-2 px-3 py-3 text-sm font-medium transition-colors" :class="status === tab.value ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'" :aria-pressed="status === tab.value" @click="chooseStatus(tab.value)">{{ t('seller.' + tab.label) }}</button></nav>
      <form class="flex flex-wrap items-center gap-3 p-4 sm:p-5" @submit.prevent="load(1)"><div class="relative min-w-0 flex-1 sm:max-w-md"><Search :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" /><input v-model="query" class="input w-full pl-10" :aria-label="t('seller.search_orders')" :placeholder="t('seller.search_orders')" /></div><button class="btn-secondary" :disabled="loading">{{ t('seller.search') }}</button><select :value="status" class="select w-full sm:ml-auto sm:w-auto" :aria-label="t('seller.all_statuses')" @change="chooseStatus(($event.target as HTMLSelectElement).value)"><option value="">{{ t('seller.all_statuses') }}</option><option v-for="value in ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']" :key="value" :value="value">{{ t('status.' + value) }}</option></select><button v-if="filtered" type="button" class="text-sm font-medium text-primary" @click="reset">{{ t('seller.reset') }}</button></form>
      <SellerListState :loading="loading" :error="error" :empty="!orders.length" :title="t(filtered ? 'seller.no_results' : 'seller.no_orders')" :description="t(filtered ? 'seller.no_results_help' : 'seller.no_orders_help')" @retry="load()"><button v-if="filtered" class="btn-secondary" @click="reset">{{ t('seller.reset') }}</button></SellerListState>
      <template v-if="!loading && !error && orders.length">
        <div class="divide-y divide-border-gray border-t border-border-gray">
          <article v-for="order in orders" :key="order.id" class="grid gap-4 p-5 sm:p-6 xl:grid-cols-[minmax(0,1fr)_130px_150px_160px] xl:items-center">
            <div class="min-w-0"><RouterLink :to="{ name: 'seller-shop-order-detail', params: { id: shopId, orderId: order.id } }" class="text-sm font-semibold hover:text-primary">{{ order.shop_order_number }}</RouterLink><p class="mt-1 text-sm text-muted">{{ order.customer_name }}</p><p class="mt-2 text-xs text-muted">{{ formatDate(order.placed_at) }} · {{ order.items_count }} {{ t('seller.products') }}</p></div>
            <div><p class="text-xs text-muted">{{ t('seller.shop_total') }}</p><p class="mt-1 text-base font-semibold tabular-nums">{{ formatPrice(order.total, order.currency) }}</p><p class="mt-2 flex items-center gap-2 text-xs text-muted">{{ t('seller.payment') }}<StatusTag :status="order.payment_status || 'unpaid'" /></p></div>
            <div class="flex flex-wrap gap-2"><StatusTag :status="order.status" /><BaseBadge v-if="order.shipment?.status === 'returned'" variant="danger">{{ t('admin.shipments.filter.returned') }}</BaseBadge></div>
            <RouterLink :to="{ name: 'seller-shop-order-detail', params: { id: shopId, orderId: order.id } }" class="btn-secondary btn-sm justify-between">{{ action(order) }}<ArrowRight :size="16" /></RouterLink>
          </article>
        </div>
        <div v-if="pageCount > 1" class="border-t border-border-gray p-5"><BasePagination :page="page" :page-count="pageCount" :page-size="pageSize" :total-items="total" @update:page="load" /></div>
      </template>
    </section>
  </div>
</template>
