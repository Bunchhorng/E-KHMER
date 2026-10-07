<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { History, Info, Search } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sellerApi } from '@/api/seller'
import type { AdminInventoryItem, InventoryTransaction } from '@/api/admin'
import { useSellerList } from '@/composables/useSellerList'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import { extractErrorMessage } from '@/api/errors'
import { formatDateTime } from '@/utils/format'
import BaseBadge from '@/components/BaseBadge.vue'
import BaseModal from '@/components/BaseModal.vue'
import BasePagination from '@/components/BasePagination.vue'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
import SellerListState from '@/components/seller/SellerListState.vue'
const route = useRoute()
const shopId = Number(route.params.id)
const { t } = useI18n()
const workspace = useSellerWorkspace()
const query = ref(String(route.query.q ?? ''))
const status = ref(String(route.query.stock_status ?? ''))
const selected = ref<AdminInventoryItem | null>(null)
const quantity = ref<number | null>(null)
const adjusting = ref(false)
const stockError = ref('')
const feedback = ref('')
const ledger = ref<InventoryTransaction[]>([])
const ledgerLoading = ref(false)
const ledgerError = ref('')
const ledgerPage = ref(1)
const ledgerPages = ref(1)
const ledgerTotal = ref(0)
const ledgerSize = ref(20)
let historyRequest = 0
let alive = true
const { items: inventory, loading, error, page, pageCount, pageSize, total, load } = useSellerList<AdminInventoryItem>(
  page => sellerApi.listInventory(shopId, { q: query.value.trim() || undefined, stock_status: status.value || undefined, page }), () => t('seller.load_failed')
)
const filtered = computed(() => !!query.value.trim() || !!status.value)
const valid = computed(() => selected.value && quantity.value !== null && Number.isInteger(quantity.value) && quantity.value >= selected.value.reserved_quantity && quantity.value <= 1000000)
const available = computed(() => Math.max(0, Number(quantity.value ?? 0) - (selected.value?.reserved_quantity ?? 0)))
async function reset() { query.value = ''; status.value = ''; await load(1) }
async function loadLedger(target = 1) {
  const item = selected.value
  if (!item) return
  const request = ++historyRequest
  ledgerLoading.value = true; ledgerError.value = ''
  try {
    const { data } = await sellerApi.listInventoryTransactions(shopId, item.id, { page: target })
    if (request !== historyRequest || !alive) return
    ledger.value = data.data
    ledgerPage.value = data.meta.current_page; ledgerPages.value = data.meta.last_page
    ledgerTotal.value = data.meta.total; ledgerSize.value = data.meta.per_page
  } catch (cause) { if (request === historyRequest && alive) ledgerError.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { if (request === historyRequest && alive) ledgerLoading.value = false }
}
function selectItem(item: AdminInventoryItem) {
  selected.value = item; quantity.value = item.quantity; stockError.value = ''; feedback.value = ''; ledger.value = []
  void loadLedger()
}
function close() { if (!adjusting.value) { selected.value = null; historyRequest++ } }
async function adjustStock() {
  const item = selected.value
  if (!item || !valid.value || adjusting.value || quantity.value === null) return
  adjusting.value = true; stockError.value = ''; feedback.value = ''
  try {
    const { data } = await sellerApi.adjustInventory(shopId, item.id, quantity.value)
    if (!alive) return
    selected.value = data.data; quantity.value = data.data.quantity
    feedback.value = t('seller.stock_saved')
    if (workspace.selectedId === shopId) workspace.dashboard = null
    await Promise.all([load(), loadLedger()])
  } catch (cause) { if (alive) stockError.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { if (alive) adjusting.value = false }
}
function quantityText(transaction: InventoryTransaction) {
  if (transaction.type === 'adjust') return transaction.quantity >= 0 ? '+' + transaction.quantity : String(transaction.quantity)
  return (['release', 'restock', 'in'].includes(transaction.type) ? '+' : '−') + Math.abs(transaction.quantity)
}
watch(() => [route.query.q, route.query.stock_status], () => {
  query.value = String(route.query.q ?? ''); status.value = String(route.query.stock_status ?? ''); void load(1)
})
onMounted(() => void load())
onBeforeUnmount(() => { alive = false; historyRequest++ })
</script>
<template>
  <div class="space-y-6">
    <SellerPageHeader :title="t('seller.stock')" :description="t('seller.stock_intro')" />
    <div class="flex items-start gap-3 rounded-xl border border-primary/10 bg-primary/5 p-4 text-sm leading-relaxed text-muted"><Info :size="18" class="mt-0.5 shrink-0 text-primary" /><p>{{ t('seller.stock_explain') }}</p></div>
    <section class="card overflow-hidden rounded-2xl">
      <form class="flex flex-wrap items-center gap-3 border-b border-border-gray p-4 sm:p-5" @submit.prevent="load(1)">
        <div class="relative min-w-0 flex-1 sm:max-w-sm"><Search :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" /><input v-model="query" class="input w-full pl-10" :aria-label="t('seller.search_stock')" :placeholder="t('seller.search_stock')" /></div><button class="btn-secondary" :disabled="loading">{{ t('seller.search') }}</button>
        <select v-model="status" class="select w-full sm:w-auto" :aria-label="t('seller.all_stock')" @change="load(1)"><option value="">{{ t('seller.all_stock') }}</option><option value="in">{{ t('seller.healthy') }}</option><option value="low">{{ t('seller.low_stock') }}</option><option value="out">{{ t('seller.out') }}</option></select><button v-if="filtered" type="button" class="text-sm font-medium text-primary" @click="reset">{{ t('seller.reset') }}</button>
      </form>
      <SellerListState :loading="loading" :error="error" :empty="!inventory.length" :title="t(filtered ? 'seller.no_results' : 'seller.no_inventory')" :description="t(filtered ? 'seller.no_results_help' : 'seller.no_inventory_help')" @retry="load()"><button v-if="filtered" class="btn-secondary" @click="reset">{{ t('seller.reset') }}</button><RouterLink v-else :to="{ name: 'seller-shop-product-create', params: { id: shopId } }" class="btn-primary">{{ t('seller.add_product') }}</RouterLink></SellerListState>
      <template v-if="!loading && !error && inventory.length">
        <div class="divide-y divide-border-gray">
          <article v-for="item in inventory" :key="item.id" class="flex flex-wrap items-center gap-5 p-5 sm:p-6">
            <div class="min-w-0 flex-1 basis-48"><h2 class="text-sm font-semibold">{{ item.product_name }}</h2><p class="mt-1 text-xs text-muted">{{ item.sku }}<span v-if="item.variant_label"> · {{ item.variant_label }}</span></p><div class="mt-2"><BaseBadge :variant="item.is_out_of_stock ? 'danger' : item.is_low_stock ? 'warning' : 'success'" dot>{{ t(item.is_out_of_stock ? 'seller.out' : item.is_low_stock ? 'seller.low_stock' : 'seller.healthy') }}</BaseBadge></div></div>
            <dl class="grid w-full grid-cols-3 gap-3 rounded-xl bg-canvas p-3 sm:w-auto sm:min-w-72 sm:gap-6 sm:px-5"><div><dt class="text-xs text-muted">{{ t('seller.available') }}</dt><dd class="mt-1 text-xl font-bold tabular-nums" :class="item.is_low_stock ? 'text-amber-700 dark:text-amber-400' : 'text-ink'">{{ item.available_quantity }}</dd></div><div><dt class="text-xs text-muted">{{ t('seller.reserved') }}</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ item.reserved_quantity }}</dd></div><div><dt class="text-xs text-muted">{{ t('seller.on_hand') }}</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ item.quantity }}</dd></div></dl>
            <button type="button" class="btn-secondary w-full sm:w-auto" @click="selectItem(item)">{{ t('seller.update_stock') }}</button>
          </article>
        </div>
        <div v-if="pageCount > 1" class="border-t border-border-gray p-5"><BasePagination :page="page" :page-count="pageCount" :page-size="pageSize" :total-items="total" @update:page="load" /></div>
      </template>
    </section>
    <BaseModal :model-value="!!selected" :title="t('seller.update_stock')" size="lg" :close-on-backdrop="!adjusting" @update:model-value="close">
      <template v-if="selected">
        <p class="font-semibold">{{ selected.product_name }}</p><p class="mt-1 text-xs text-muted">{{ selected.sku }}</p>
        <form class="mt-5 space-y-4" @submit.prevent="adjustStock">
          <label class="block"><span class="label">{{ t('seller.new_total') }}</span><input v-model.number="quantity" class="input w-full" type="number" :min="selected.reserved_quantity" max="1000000" step="1" required :disabled="adjusting" :aria-invalid="!valid" aria-describedby="stock-count-help" /><span id="stock-count-help" class="mt-2 block text-xs leading-relaxed text-muted">{{ t('seller.new_total_help') }}</span></label>
          <p v-if="quantity !== null && quantity < selected.reserved_quantity" class="text-sm text-red-600 dark:text-red-400">{{ t('seller.min_reserved', { count: selected.reserved_quantity }) }}</p>
          <div class="rounded-xl bg-primary/5 p-4 text-sm"><p class="font-medium text-primary">{{ t('seller.will_available', { count: available }) }}</p><p class="mt-1 text-xs text-muted">{{ t('seller.reserved') }}: {{ selected.reserved_quantity }}</p></div>
          <p v-if="stockError" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ stockError }}</p><p v-if="feedback" role="status" class="text-sm text-emerald-700 dark:text-emerald-400">{{ feedback }}</p>
          <button class="btn-primary w-full" :disabled="adjusting || !valid || quantity === selected.quantity">{{ t(adjusting ? 'common.saving' : 'seller.update_stock') }}</button>
        </form>
        <section class="mt-7 border-t border-border-gray pt-5"><h3 class="mb-4 flex items-center gap-2 text-sm font-semibold"><History :size="17" />{{ t('seller.stock_history') }}</h3>
          <p v-if="ledgerLoading" role="status" class="py-4 text-sm text-muted">{{ t('common.loading') }}</p><p v-else-if="ledgerError" role="alert" class="text-sm text-red-600 dark:text-red-400">{{ ledgerError }} <button class="underline" @click="loadLedger(ledgerPage)">{{ t('actions.retry') }}</button></p><p v-else-if="!ledger.length" class="text-sm text-muted">{{ t('seller.no_history') }}</p>
          <div v-else class="divide-y divide-border-gray"><div v-for="transaction in ledger" :key="transaction.id" class="flex items-start justify-between gap-3 py-3"><div class="min-w-0"><p class="text-sm font-medium">{{ t('seller.' + transaction.type) }}</p><p class="mt-1 text-xs text-muted">{{ formatDateTime(transaction.created_at) }}</p><p class="mt-1 break-words text-xs text-muted">{{ transaction.note || transaction.reference }} · {{ transaction.created_by?.name ?? t('seller.system') }}</p></div><div class="text-right"><p class="text-sm font-semibold tabular-nums">{{ quantityText(transaction) }}</p><p class="mt-1 whitespace-nowrap text-xs text-muted">{{ t('seller.balance') }}: {{ transaction.balance_after }}</p></div></div></div>
          <div v-if="ledgerPages > 1 && !ledgerLoading" class="mt-4"><BasePagination :page="ledgerPage" :page-count="ledgerPages" :page-size="ledgerSize" :total-items="ledgerTotal" @update:page="loadLedger" /></div>
        </section>
      </template>
      <template #footer><button class="btn-secondary w-full" :disabled="adjusting" @click="close">{{ t('actions.close') }}</button></template>
    </BaseModal>
  </div>
</template>
