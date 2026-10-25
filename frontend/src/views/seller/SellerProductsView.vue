<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { AlertTriangle, Box, ChevronDown, ChevronLeft, ChevronRight, Copy, Eye, EyeOff, FileText, Package, Plus, Search, X } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sellerApi, type SellerProductFilters, type SellerProductListItem, type SellerProductListResponse } from '@/api/seller'
import { useSellerList } from '@/composables/useSellerList'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice } from '@/utils/format'
import AssetImage from '@/components/AssetImage.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import BaseModal from '@/components/BaseModal.vue'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
import SellerListState from '@/components/seller/SellerListState.vue'
import SellerProductActions from '@/components/seller/SellerProductActions.vue'

const route = useRoute()
const shopId = Number(route.params.id)
const { t } = useI18n()
const workspace = useSellerWorkspace()
const query = ref('')
const category = ref('')
const status = ref<NonNullable<SellerProductFilters['status']> | ''>('')
const stock = ref<NonNullable<SellerProductFilters['stock_status']> | ''>('')
const sort = ref<NonNullable<SellerProductFilters['sort']>>('newest')
const perPage = ref(10)
const summary = ref<SellerProductListResponse['summary'] | null>(null)
const categories = ref<SellerProductListResponse['categories']>([])
const busyId = ref<number | null>(null)
const bulkBusy = ref(false)
const selected = ref<SellerProductListItem | null>(null)
const preview = ref<SellerProductListItem | null>(null)
const selectedIds = ref<number[]>([])
const feedback = ref('')
const mutationError = ref('')
const busy = computed(() => busyId.value !== null || bulkBusy.value)
let searchTimer: ReturnType<typeof setTimeout> | undefined
let listingRequest = 0
const { items: products, loading, error, page, pageCount, pageSize, total, load } = useSellerList<SellerProductListItem>(
  async target => {
    const request = ++listingRequest
    const response = await sellerApi.listProducts(shopId, {
      q: query.value.trim() || undefined, page: target, category_id: category.value ? Number(category.value) : undefined,
      status: status.value || undefined, stock_status: stock.value || undefined, sort: sort.value, per_page: perPage.value
    })
    if (request === listingRequest) {
      summary.value = response.data.summary ?? null
      categories.value = response.data.categories ?? []
    }
    return response
  }, () => t('seller.load_failed')
)
const filtered = computed(() => !!(query.value.trim() || category.value || status.value || stock.value))
const allSelected = computed(() => products.value.length > 0 && products.value.every(product => selectedIds.value.includes(product.id)))
const partlySelected = computed(() => selectedIds.value.length > 0 && !allSelected.value)
const rangeStart = computed(() => total.value ? (page.value - 1) * pageSize.value + 1 : 0)
const rangeEnd = computed(() => Math.min(page.value * pageSize.value, total.value))
const pages = computed(() => [...new Set([1, page.value - 1, page.value, page.value + 1, pageCount.value])].filter(value => value >= 1 && value <= pageCount.value).sort((a, b) => a - b))
const metricCards = computed(() => [
  { key: 'total_products', value: summary.value?.total_products ?? (loading.value ? null : total.value), label: 'total_products', help: 'total_products_help', icon: Box, tone: 'blue' },
  { key: 'live_products', value: summary.value?.live_products, label: 'live_products', help: 'visible_to_customers', icon: Package, tone: 'green' },
  { key: 'hidden_products', value: summary.value?.hidden_products, label: 'draft_hidden', help: 'not_visible_to_customers', icon: FileText, tone: 'violet' },
  { key: 'low_stock_products', value: summary.value?.low_stock_products, label: 'low_stock', help: 'needs_attention', icon: AlertTriangle, tone: 'amber' }
])
async function reload(target = 1) {
  clearTimeout(searchTimer); selectedIds.value = []
  await load(target)
  if (!error.value && page.value > pageCount.value) await load(pageCount.value)
}
function search() { clearTimeout(searchTimer); searchTimer = setTimeout(() => void reload(), 300) }
function reset() {
  const filtersChanged = !!(category.value || status.value || stock.value || sort.value !== 'newest')
  query.value = ''; category.value = ''; status.value = ''; stock.value = ''; sort.value = 'newest'
  if (!filtersChanged) void reload()
}
function selectPage(event: Event) { selectedIds.value = (event.target as HTMLInputElement).checked ? products.value.map(product => product.id) : [] }
function stockLabel(product: SellerProductListItem) { return !product.in_stock ? 'seller.out' : product.is_low_stock ? 'seller.low_stock' : 'seller.healthy' }
function stockVariant(product: SellerProductListItem): 'success' | 'warning' | 'danger' { return !product.in_stock ? 'danger' : product.is_low_stock ? 'warning' : 'success' }
function invalidateOverview() { if (workspace.selectedId === shopId) workspace.dashboard = null }
async function toggle(product: SellerProductListItem) {
  if (busy.value) return
  busyId.value = product.id; mutationError.value = ''; feedback.value = ''
  try {
    await sellerApi.updateProduct(shopId, product.id, { is_active: !product.is_active })
    feedback.value = t('seller.saved'); invalidateOverview(); await reload(page.value)
  } catch (cause) { mutationError.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { busyId.value = null }
}
async function bulkVisibility(active: boolean) {
  if (busy.value) return
  bulkBusy.value = true; mutationError.value = ''; feedback.value = ''
  const targets = products.value.filter(product => selectedIds.value.includes(product.id) && product.is_active !== active)
  try {
    const results = await Promise.allSettled(targets.map(product => sellerApi.updateProduct(shopId, product.id, { is_active: active })))
    const failures = results.filter(result => result.status === 'rejected').length
    if (failures) mutationError.value = t('seller.bulk_update_failed', { count: failures })
    if (results.length > failures) feedback.value = t('seller.updated_products', { count: results.length - failures })
    invalidateOverview(); await reload(page.value)
  } finally { bulkBusy.value = false }
}
async function remove() {
  const product = selected.value
  if (!product || busy.value) return
  busyId.value = product.id; mutationError.value = ''; feedback.value = ''
  try {
    await sellerApi.deleteProduct(shopId, product.id)
    selected.value = null; feedback.value = t('seller.saved'); invalidateOverview()
    await reload(products.value.length === 1 && page.value > 1 ? page.value - 1 : page.value)
  } catch (cause) { mutationError.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { busyId.value = null }
}
async function copyCode(product: SellerProductListItem) {
  try { await navigator.clipboard.writeText(product.barcode || product.sku || ''); mutationError.value = ''; feedback.value = t('seller.code_copied') }
  catch { mutationError.value = t('seller.copy_failed') }
}
watch([category, status, stock, sort, perPage], () => void reload())
onMounted(() => void reload())
onBeforeUnmount(() => { clearTimeout(searchTimer); listingRequest++ })
</script>

<template>
  <div class="seller-products">
    <SellerPageHeader class="products-heading" :title="t('seller.products')" :description="t('seller.products_help')">
      <RouterLink :to="{ name: 'seller-shop-product-create', params: { id: shopId } }" class="btn-primary products-add"><Plus :size="22" :stroke-width="1.7" />{{ t('seller.add_product') }}</RouterLink>
    </SellerPageHeader>
    <div class="products-metrics grid grid-cols-2 xl:grid-cols-4" :aria-label="t('seller.product_summary')">
      <div v-for="card in metricCards" :key="card.key" class="card product-metric relative overflow-hidden" :data-tone="card.tone">
        <span class="metric-icon"><component :is="card.icon" :size="28" :stroke-width="1.8" /></span>
        <div class="min-w-0"><p class="metric-value font-bold tabular-nums">{{ card.value ?? '—' }}</p><h2>{{ t('seller.' + card.label) }}</h2><p class="metric-help text-muted">{{ t('seller.' + card.help) }}</p></div>
        <svg viewBox="0 0 108 50" fill="none" class="metric-wave" aria-hidden="true"><path d="M1 44C13 29 20 17 32 22C45 27 53 40 65 28C77 16 82 0 94 8C100 11 103 15 107 19" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" /></svg>
      </div>
    </div>
    <p v-if="feedback" role="status" class="rounded-xl bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-400">{{ feedback }}</p>
    <p v-if="mutationError && !selected" role="alert" class="rounded-xl bg-red-500/10 p-4 text-sm text-red-600 dark:text-red-400">{{ mutationError }}</p>
    <section class="card products-list min-w-0 overflow-hidden" :aria-label="t('seller.products')">
      <form class="products-toolbar flex flex-wrap items-center border-b border-border-gray" role="search" @submit.prevent="reload()">
        <div class="product-search relative min-w-0 flex-1"><Search :size="21" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted" /><input v-model="query" type="search" class="input pl-[52px]" :aria-label="t('seller.search_products')" :placeholder="t('seller.search_products_placeholder')" @input="search" /><button type="submit" class="sr-only">{{ t('seller.search') }}</button></div>
        <label class="product-filter relative"><span class="sr-only">{{ t('seller.category') }}</span><select v-model="category" class="select"><option value="">{{ t('seller.category') }}</option><option v-for="item in categories" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select><ChevronDown :size="16" aria-hidden="true" /></label>
        <label class="product-filter relative"><span class="sr-only">{{ t('order.status') }}</span><select v-model="status" class="select"><option value="">{{ t('order.status') }}</option><option value="live">{{ t('seller.live') }}</option><option value="hidden">{{ t('seller.draft_hidden') }}</option></select><ChevronDown :size="16" aria-hidden="true" /></label>
        <label class="product-filter relative"><span class="sr-only">{{ t('seller.stock') }}</span><select v-model="stock" class="select"><option value="">{{ t('seller.stock') }}</option><option value="in">{{ t('seller.in_stock') }}</option><option value="low">{{ t('seller.low_stock') }}</option><option value="out">{{ t('seller.out') }}</option></select><ChevronDown :size="16" aria-hidden="true" /></label>
        <label class="product-filter sort-filter relative"><span class="sr-only">{{ t('seller.sort_by') }}</span><select v-model="sort" class="select"><option value="newest">{{ t('seller.sort_by') }}</option><option value="name_asc">{{ t('seller.sort_name_asc') }}</option><option value="name_desc">{{ t('seller.sort_name_desc') }}</option><option value="price_asc">{{ t('seller.sort_price_asc') }}</option><option value="price_desc">{{ t('seller.sort_price_desc') }}</option></select><ChevronDown :size="16" aria-hidden="true" /></label>
        <span class="product-result-count text-sm text-muted">{{ total }} {{ t('seller.products') }}</span>
        <button v-if="filtered" type="button" class="clear-filters inline-flex items-center gap-1 text-xs font-medium text-primary" @click="reset"><X :size="15" />{{ t('seller.reset') }}</button>
      </form>
      <div v-if="selectedIds.length" class="product-selection flex flex-wrap items-center gap-3 border-b border-border-gray bg-primary/5">
        <span class="mr-auto text-sm font-medium text-primary">{{ t('seller.selected_products', { count: selectedIds.length }) }}</span>
        <button type="button" class="btn-secondary btn-sm" :disabled="busy || loading" @click="bulkVisibility(true)"><Eye :size="16" />{{ t('seller.show_short') }}</button>
        <button type="button" class="btn-secondary btn-sm" :disabled="busy || loading" @click="bulkVisibility(false)"><EyeOff :size="16" />{{ t('seller.hide_short') }}</button>
        <button type="button" class="btn-icon" :aria-label="t('seller.clear_selection')" :disabled="bulkBusy" @click="selectedIds = []"><X :size="18" /></button>
      </div>
      <SellerListState :loading="loading" :error="error" :empty="!products.length" :title="t(filtered ? 'seller.no_results' : 'seller.no_products')" :description="t(filtered ? 'seller.no_results_help' : 'seller.no_products_help')" @retry="reload(page)"><button v-if="filtered" class="btn-secondary" @click="reset">{{ t('seller.reset') }}</button><RouterLink v-else :to="{ name: 'seller-shop-product-create', params: { id: shopId } }" class="btn-primary">{{ t('seller.add_product') }}</RouterLink></SellerListState>
      <template v-if="!loading && !error && products.length">
        <div class="products-table-scroll" tabindex="0" role="region" :aria-label="t('seller.product_table')">
          <table class="products-table w-full text-left">
            <caption class="sr-only">{{ t('seller.product_table') }}</caption>
            <thead><tr><th scope="col" class="selection-cell"><input type="checkbox" :checked="allSelected" :indeterminate="partlySelected" :disabled="busy" :aria-label="t('seller.select_page')" @change="selectPage" /></th><th scope="col">{{ t('seller.product') }}</th><th scope="col">{{ t('seller.category') }}</th><th scope="col">{{ t('seller.sku_barcode') }}</th><th scope="col">{{ t('seller.stock') }}</th><th scope="col">{{ t('seller.price') }}</th><th scope="col">{{ t('seller.visibility') }}</th><th scope="col">{{ t('seller.actions') }}</th></tr></thead>
            <tbody><tr v-for="product in products" :key="product.id" :class="{ 'product-row-selected': selectedIds.includes(product.id) }">
              <td class="selection-cell"><input v-model="selectedIds" type="checkbox" :value="product.id" :disabled="busy" :aria-label="t('seller.select_product', { name: product.name })" /></td>
              <td class="product-name-cell"><RouterLink :to="{ name: 'seller-shop-product-edit', params: { id: shopId, productId: product.id } }" class="flex min-w-0 items-center gap-4"><AssetImage :src="product.cover_image" :alt="product.name" class="product-thumbnail rounded-lg border border-border-gray bg-canvas" /><div class="min-w-0"><h2 class="font-semibold hover:text-primary">{{ product.name }}</h2><p class="mt-1 text-xs" :class="stockVariant(product) === 'warning' ? 'text-amber-600 dark:text-amber-400' : stockVariant(product) === 'danger' ? 'text-red-600 dark:text-red-400' : 'text-muted'">{{ t(stockLabel(product)) }}</p></div></RouterLink></td>
              <td class="product-category text-muted">{{ product.category?.name ?? '—' }}</td>
              <td class="product-code"><p class="text-xs text-muted">{{ t(product.barcode ? 'seller.barcode_label' : 'seller.sku') }}:</p><div class="mt-0.5 flex items-center gap-2"><span class="break-all text-xs text-muted">{{ product.barcode || product.sku || '—' }}</span><button v-if="product.barcode || product.sku" type="button" class="copy-code text-muted hover:text-primary" :aria-label="t('seller.copy_code', { name: product.name })" @click="copyCode(product)"><Copy :size="15" /></button></div></td>
              <td class="product-stock"><BaseBadge :variant="stockVariant(product)" dot>{{ t(stockLabel(product)) }}</BaseBadge><p class="mt-1 text-xs text-muted">{{ product.available_quantity === undefined ? '—' : t('seller.stock_count', { count: product.available_quantity }) }}</p></td>
              <td class="product-price whitespace-nowrap font-semibold tabular-nums">{{ formatPrice(product.price) }}</td>
              <td><BaseBadge :variant="product.is_active ? 'success' : 'neutral'" dot>{{ t(product.is_active ? 'seller.live' : 'seller.hidden') }}</BaseBadge></td>
              <td class="product-actions-cell"><SellerProductActions :name="product.name" :active="product.is_active" :disabled="busy" :edit-to="{ name: 'seller-shop-product-edit', params: { id: shopId, productId: product.id } }" @preview="preview = product" @toggle="toggle(product)" @delete="selected = product; mutationError = ''" /></td>
            </tr></tbody>
          </table>
        </div>
        <footer class="products-footer flex flex-wrap items-center justify-between gap-4 border-t border-border-gray">
          <p class="text-xs text-muted">{{ t('seller.showing_products', { from: rangeStart, to: rangeEnd, total }) }}</p>
          <div class="flex flex-wrap items-center gap-5"><nav class="flex items-center gap-2" :aria-label="t('seller.product_pagination')">
            <button type="button" class="pagination-button" :disabled="page <= 1 || loading || busy" :aria-label="t('pagination.previous')" @click="reload(page - 1)"><ChevronLeft :size="19" /></button>
            <template v-for="(item, index) in pages" :key="item"><span v-if="index > 0 && item - pages[index - 1]! > 1" class="text-muted">…</span><button type="button" class="pagination-button" :class="{ 'pagination-active': page === item }" :aria-current="page === item ? 'page' : undefined" :aria-label="t('seller.page_number', { page: item })" :disabled="busy" @click="reload(item)">{{ item }}</button></template>
            <button type="button" class="pagination-button" :disabled="page >= pageCount || loading || busy" :aria-label="t('pagination.next')" @click="reload(page + 1)"><ChevronRight :size="19" /></button>
          </nav><label class="product-filter per-page relative"><span class="sr-only">{{ t('seller.products_per_page') }}</span><select v-model="perPage" class="select" :disabled="busy"><option v-for="size in [10, 15, 25, 50]" :key="size" :value="size">{{ t('seller.per_page', { count: size }) }}</option></select><ChevronDown :size="15" aria-hidden="true" /></label></div>
        </footer>
      </template>
    </section>
    <BaseModal :model-value="!!selected" :title="t('seller.delete_product')" size="md" :close-on-backdrop="!busy" @update:model-value="!busy && (selected = null)">
      <p class="text-sm leading-relaxed text-muted">{{ t('seller.delete_help', { name: selected?.name }) }}</p><p v-if="mutationError" role="alert" class="mt-4 text-sm text-red-600 dark:text-red-400">{{ mutationError }}</p>
      <template #footer><div class="flex justify-end gap-2"><button class="btn-secondary" :disabled="busy" @click="selected = null">{{ t('actions.cancel') }}</button><button class="btn-primary !bg-red-600 hover:!bg-red-700" :disabled="busy" @click="remove">{{ t(busy ? 'common.saving' : 'actions.delete') }}</button></div></template>
    </BaseModal>
    <BaseModal :model-value="!!preview" :title="t('seller.preview')" size="md" @update:model-value="preview = null">
      <article v-if="preview" class="space-y-4"><AssetImage :src="preview.cover_image" :alt="preview.name" class="h-64 w-full rounded-xl border border-border-gray bg-canvas" /><div class="flex flex-wrap items-center gap-2"><BaseBadge :variant="preview.is_active ? 'success' : 'neutral'" dot>{{ t(preview.is_active ? 'seller.live' : 'seller.hidden') }}</BaseBadge><span class="text-xs text-muted">{{ preview.category?.name }}</span></div><h2 class="text-2xl font-bold">{{ preview.name }}</h2><p class="text-xl font-semibold">{{ formatPrice(preview.price) }}</p><p v-if="preview.short_description" class="text-sm leading-relaxed text-muted">{{ preview.short_description }}</p><p class="text-xs text-muted">{{ t(stockLabel(preview)) }}</p></article>
      <template #footer><div class="flex justify-end gap-2"><button type="button" class="btn-secondary" @click="preview = null">{{ t('actions.close') }}</button><RouterLink v-if="preview?.is_active" :to="{ name: 'product-detail', params: { slug: preview.slug } }" class="btn-primary">{{ t('seller.view_in_marketplace') }}</RouterLink></div></template>
    </BaseModal>
  </div>
</template>

<style scoped>
.seller-products { display: flex; min-width: 0; flex-direction: column; gap: 16px; }
.products-heading { min-height: 78px; margin: -4px 4px 0; }
.products-heading :deep(h1) { font-size: 44px; font-weight: 750; line-height: 1.12; letter-spacing: -1.6px; }
.products-heading :deep(p) { margin-top: 5px; font-size: 18px; line-height: 1.6; }
.products-add { min-height: 52px; padding-inline: 24px; border-radius: 11px; gap: 12px; font-size: 18px; background: linear-gradient(125deg, #1762ff, #2c69f5); box-shadow: 0 4px 8px rgb(30 99 255 / 8%); }
.products-metrics { gap: 14px; margin-inline: 2px; }
.product-metric { display: flex; align-items: flex-start; gap: 24px; min-height: 129px; padding: 19px 20px; border-radius: 16px; }
[data-tone='blue'] { --tone: 30 99 255; --wave: 122 166 255; }
[data-tone='green'] { --tone: 0 159 120; --wave: 96 207 173; }
[data-tone='violet'] { --tone: 136 45 247; --wave: 182 137 255; }
[data-tone='amber'] { --tone: 239 127 0; --wave: 255 180 95; }
.metric-icon { display: flex; width: 62px; height: 60px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 16px; background: rgb(var(--tone) / 8%); color: rgb(var(--tone)); }
.metric-value { position: relative; z-index: 1; font-size: 32px; line-height: 1.15; letter-spacing: -.6px; }
.product-metric h2 { position: relative; z-index: 1; margin-top: 3px; font-size: 15px; font-weight: 550; line-height: 1.4; }
.metric-help { position: relative; z-index: 1; margin-top: 4px; font-size: 13px; line-height: 1.5; }
.metric-wave { position: absolute; right: 17px; top: 29px; width: 100px; height: 49px; color: rgb(var(--wave)); mask-image: linear-gradient(90deg, transparent, #000 20%); }
.products-list { border-radius: 16px; }
.products-toolbar { gap: 12px; padding: 18px 20px; }
.product-search { flex-basis: 340px; }
.product-search .input { min-height: 50px; border-radius: 10px; font-size: 16px; }
.product-search .input::placeholder { color: rgb(var(--color-muted) / 75%); }
.product-filter { display: block; min-width: 112px; max-width: 180px; }
.product-filter .select { height: 50px; padding: 10px 36px 10px 18px; border-radius: 10px; font-size: 14px; font-weight: 500; text-overflow: ellipsis; cursor: pointer; }
.product-filter > svg { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); pointer-events: none; }
.sort-filter { max-width: 145px; }
.product-result-count { min-width: 137px; margin-left: auto; border-left: 1px solid rgb(var(--color-border)); padding: 6px 4px 6px 26px; text-align: right; white-space: nowrap; }
.clear-filters { flex-basis: 100%; justify-content: flex-start; }
.product-selection { padding: 12px 20px; }
.products-table-scroll { position: relative; overflow-x: auto; }
.products-table { min-width: 1220px; font-size: 14px; line-height: 1.45; }
.products-table th { height: 44px; padding: 10px 14px; background: rgb(var(--color-canvas) / 85%); color: rgb(var(--color-muted)); font-size: 13px; font-weight: 600; white-space: nowrap; }
.products-table td { padding: 12px 14px; border-top: 1px solid rgb(var(--color-border)); }
.products-table tbody tr:first-child td { border-top: 0; }
.products-table tbody tr:hover, .products-table .product-row-selected { background: rgb(var(--color-primary) / 2%); }
.products-table .selection-cell { width: 58px; padding-left: 20px; padding-right: 16px; }
.selection-cell input { position: relative; display: block; width: 19px; height: 19px; appearance: none; border: 1px solid rgb(var(--color-muted) / 55%); border-radius: 5px; background: rgb(var(--color-surface)); cursor: pointer; }
.selection-cell input:checked, .selection-cell input:indeterminate { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary)); }
.selection-cell input:checked::after { content: ''; position: absolute; top: 2px; left: 6px; width: 5px; height: 10px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); }
.selection-cell input:indeterminate::after { content: ''; position: absolute; top: 8px; left: 4px; width: 9px; height: 2px; background: white; }
.product-name-cell { width: 280px; min-width: 245px; }
.product-name-cell h2 { max-width: 185px; overflow-wrap: anywhere; font-size: 15px; line-height: 1.45; letter-spacing: -.35px; }
.product-thumbnail { width: 66px; height: 66px; flex-shrink: 0; }
.product-category { width: 120px; min-width: 110px; }
.product-code { width: 166px; min-width: 160px; }
.copy-code { display: flex; min-width: 24px; min-height: 28px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 5px; }
.product-stock { min-width: 153px; }
.product-stock :deep(span), .products-table td > :deep(span) { white-space: nowrap; }
.product-stock :deep(.text-xs), .products-table td > :deep(.text-xs) { font-size: 14px; }
.product-price { min-width: 116px; font-size: 16px; }
.product-actions-cell { width: 275px; }
.products-table .product-actions-cell { padding-right: 20px; }
.products-table .text-xs { font-size: 14px; }
.products-footer { min-height: 62px; padding: 10px 22px; }
.pagination-button { display: inline-flex; width: 42px; height: 42px; align-items: center; justify-content: center; border-radius: 10px; background: rgb(var(--color-canvas)); color: rgb(var(--color-muted)); font-size: 15px; }
.pagination-button:hover:not(:disabled) { color: rgb(var(--color-primary)); background: rgb(var(--color-primary) / 8%); }
.pagination-button:disabled { opacity: .5; cursor: not-allowed; }
.pagination-button.pagination-active { background: #1e63ff; color: white; box-shadow: inset 0 0 0 3px rgb(255 255 255 / 8%); }
.per-page { min-width: 132px; }
.per-page .select { height: 42px; font-size: 12px; }
:global(html.dark .seller-products .metric-icon) { color: rgb(var(--wave)); background: rgb(var(--tone) / 13%); }
@media (max-width: 1535px) and (min-width: 1280px) { .product-metric { gap: 16px; padding-inline: 16px; } .metric-icon { width: 52px; height: 56px; } .metric-wave { width: 78px; right: 10px; } .product-metric h2 { font-size: 14px; } .metric-help { font-size: 12px; } .product-search { flex-basis: 100%; } .product-result-count { margin-left: auto; } }
@media (max-width: 1279px) { .products-toolbar { gap: 10px; } .product-search { flex-basis: 100%; } .product-filter { flex: 1; max-width: none; } .product-result-count { min-width: 110px; padding-left: 16px; } }
@media (max-width: 767px) { .products-heading { gap: 16px; margin-inline: 2px; } .products-heading :deep(h1) { font-size: 34px; letter-spacing: -1px; } .products-heading :deep(p) { font-size: 15px; } .products-add { min-height: 45px; padding-inline: 17px; font-size: 15px; } .products-metrics { margin: 0; gap: 12px; } .product-metric { min-height: 160px; flex-direction: column; gap: 10px; padding: 16px; } .metric-icon { width: 46px; height: 46px; border-radius: 12px; } .metric-value { font-size: 28px; } .product-metric h2 { font-size: 14px; } .metric-help { font-size: 12px; } .metric-wave { width: 72px; top: 20px; right: 10px; } .products-toolbar { padding: 14px; } .product-search .input { font-size: 14px; } .product-filter { min-width: calc(50% - 6px); } .product-filter .select { height: 44px; padding-left: 12px; font-size: 13px; } .product-result-count { border-left: 0; padding: 2px; font-size: 13px; } .products-footer { padding: 16px; gap: 12px; } .products-footer > div { width: 100%; justify-content: space-between; gap: 10px; } .per-page { min-width: 115px; max-width: 140px; } .per-page .select { padding-left: 10px; font-size: 12px; } }
@media (max-width: 399px) { .product-metric { padding: 14px 12px; } .metric-wave { width: 60px; } .products-footer { padding-inline: 12px; } .pagination-button { width: 36px; height: 38px; } .products-footer nav { gap: 5px; } }
</style>
