<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Eye, EyeOff, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sellerApi } from '@/api/seller'
import type { AdminProduct } from '@/api/admin'
import { useSellerList } from '@/composables/useSellerList'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import { extractErrorMessage } from '@/api/errors'
import { formatPrice } from '@/utils/format'
import AssetImage from '@/components/AssetImage.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import BaseModal from '@/components/BaseModal.vue'
import BasePagination from '@/components/BasePagination.vue'
import SellerPageHeader from '@/components/seller/SellerPageHeader.vue'
import SellerListState from '@/components/seller/SellerListState.vue'
const route = useRoute()
const shopId = Number(route.params.id)
const { t } = useI18n()
const workspace = useSellerWorkspace()
const query = ref('')
const busyId = ref<number | null>(null)
const selected = ref<AdminProduct | null>(null)
const feedback = ref('')
const mutationError = ref('')
const { items: products, loading, error, page, pageCount, pageSize, total, load } = useSellerList<AdminProduct>(
  page => sellerApi.listProducts(shopId, { q: query.value.trim() || undefined, page }), () => t('seller.load_failed')
)
const filtered = computed(() => !!query.value.trim())
async function reset() { query.value = ''; await load(1) }
async function toggle(product: AdminProduct) {
  if (busyId.value) return
  busyId.value = product.id; mutationError.value = ''; feedback.value = ''
  try {
    await sellerApi.updateProduct(shopId, product.id, { is_active: !product.is_active })
    feedback.value = t('seller.saved')
    await load(); if (workspace.selectedId === shopId) workspace.dashboard = null
  } catch (cause) { mutationError.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { busyId.value = null }
}
async function remove() {
  const product = selected.value
  if (!product || busyId.value) return
  busyId.value = product.id; mutationError.value = ''; feedback.value = ''
  try {
    await sellerApi.deleteProduct(shopId, product.id)
    selected.value = null
    feedback.value = t('seller.saved')
    await load(products.value.length === 1 && page.value > 1 ? page.value - 1 : page.value)
    if (workspace.selectedId === shopId) workspace.dashboard = null
  } catch (cause) { mutationError.value = extractErrorMessage(cause, t('seller.save_failed')) }
  finally { busyId.value = null }
}
onMounted(() => void load())
</script>
<template>
  <div class="space-y-6">
    <SellerPageHeader :title="t('seller.products')" :description="t('seller.products_help')"><RouterLink :to="{ name: 'seller-shop-product-create', params: { id: shopId } }" class="btn-primary"><Plus :size="18" />{{ t('seller.add_product') }}</RouterLink></SellerPageHeader>
    <p v-if="feedback" role="status" class="rounded-xl bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-400">{{ feedback }}</p>
    <p v-if="mutationError && !selected" role="alert" class="rounded-xl bg-red-500/10 p-4 text-sm text-red-600 dark:text-red-400">{{ mutationError }}</p>
    <section class="card overflow-hidden rounded-2xl">
      <form class="flex flex-wrap items-center gap-3 border-b border-border-gray p-4 sm:p-5" @submit.prevent="load(1)">
        <div class="relative min-w-0 flex-1 sm:max-w-md"><Search :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" /><input v-model="query" class="input w-full pl-10" :aria-label="t('seller.search_products')" :placeholder="t('seller.search_products')" /></div>
        <button class="btn-secondary" :disabled="loading">{{ t('seller.search') }}</button><button v-if="filtered" type="button" class="text-sm font-medium text-primary" @click="reset">{{ t('seller.reset') }}</button><span class="ml-auto text-sm text-muted">{{ total }} {{ t('seller.products') }}</span>
      </form>
      <SellerListState :loading="loading" :error="error" :empty="!products.length" :title="t(filtered ? 'seller.no_results' : 'seller.no_products')" :description="t(filtered ? 'seller.no_results_help' : 'seller.no_products_help')" @retry="load()"><button v-if="filtered" class="btn-secondary" @click="reset">{{ t('seller.reset') }}</button><RouterLink v-else :to="{ name: 'seller-shop-product-create', params: { id: shopId } }" class="btn-primary">{{ t('seller.add_product') }}</RouterLink></SellerListState>
      <template v-if="!loading && !error && products.length">
        <div class="hidden grid-cols-[minmax(0,1fr)_120px_100px_240px] gap-4 bg-canvas/70 px-6 py-3 text-xs font-semibold text-muted xl:grid"><span>{{ t('seller.product') }}</span><span>{{ t('seller.price') }}</span><span>{{ t('seller.visibility') }}</span><span class="text-right">{{ t('seller.next_step') }}</span></div>
        <div class="divide-y divide-border-gray">
          <article v-for="product in products" :key="product.id" class="grid items-center gap-4 p-4 sm:p-6 xl:grid-cols-[minmax(0,1fr)_120px_100px_240px]">
            <RouterLink :to="{ name: 'seller-shop-product-edit', params: { id: shopId, productId: product.id } }" class="flex min-w-0 items-center gap-4"><AssetImage :src="product.cover_image" :alt="product.name" class="h-16 w-16 shrink-0 rounded-xl border border-border-gray bg-canvas object-cover" /><div class="min-w-0"><h2 class="text-sm font-semibold hover:text-primary">{{ product.name }}</h2><p class="mt-1 truncate text-xs text-muted">{{ product.sku }}<span v-if="product.category"> · {{ product.category.name }}</span></p><p class="mt-1 text-xs" :class="product.in_stock ? 'text-muted' : 'text-amber-700 dark:text-amber-400'">{{ t(product.in_stock ? 'seller.healthy' : 'seller.out') }}</p></div></RouterLink>
            <p class="text-sm font-semibold tabular-nums">{{ formatPrice(product.price) }}</p>
            <div><BaseBadge :variant="product.is_active ? 'success' : 'neutral'" dot>{{ t(product.is_active ? 'seller.live' : 'seller.hidden') }}</BaseBadge></div>
            <div class="flex flex-wrap items-center gap-2 xl:justify-end">
              <RouterLink :to="{ name: 'seller-shop-product-edit', params: { id: shopId, productId: product.id } }" class="btn-secondary btn-sm"><Pencil :size="15" />{{ t('actions.edit') }}</RouterLink>
              <button type="button" class="btn-secondary btn-sm" :disabled="busyId !== null" :title="t(product.is_active ? 'seller.hide' : 'seller.show')" :aria-label="t(product.is_active ? 'seller.hide' : 'seller.show') + ': ' + product.name" @click="toggle(product)"><component :is="product.is_active ? EyeOff : Eye" :size="18" /><span>{{ t(product.is_active ? 'seller.hide_short' : 'seller.show_short') }}</span></button>
              <button type="button" class="btn-icon text-red-600 dark:text-red-400" :disabled="busyId !== null" :title="t('actions.delete')" :aria-label="t('actions.delete') + ': ' + product.name" @click="selected = product; mutationError = ''"><Trash2 :size="17" /></button>
            </div>
          </article>
        </div>
        <div v-if="pageCount > 1" class="border-t border-border-gray p-5"><BasePagination :page="page" :page-count="pageCount" :page-size="pageSize" :total-items="total" @update:page="load" /></div>
      </template>
    </section>
    <BaseModal :model-value="!!selected" :title="t('seller.delete_product')" size="md" :close-on-backdrop="!busyId" @update:model-value="!busyId && (selected = null)">
      <p class="text-sm leading-relaxed text-muted">{{ t('seller.delete_help', { name: selected?.name }) }}</p><p v-if="mutationError" role="alert" class="mt-4 text-sm text-red-600 dark:text-red-400">{{ mutationError }}</p>
      <template #footer><div class="flex justify-end gap-2"><button class="btn-secondary" :disabled="busyId !== null" @click="selected = null">{{ t('actions.cancel') }}</button><button class="btn-primary !bg-red-600 hover:!bg-red-700" :disabled="busyId !== null" @click="remove">{{ t(busyId ? 'common.saving' : 'actions.delete') }}</button></div></template>
    </BaseModal>
  </div>
</template>
