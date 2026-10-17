<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Search, Store } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { shopsApi, type PublicShop } from '@/api/shops'
import { extractErrorMessage } from '@/api/errors'
import AssetImage from '@/components/AssetImage.vue'
import BasePagination from '@/components/BasePagination.vue'
import EmptyState from '@/components/EmptyState.vue'
const { t } = useI18n()
const route = useRoute(); const router = useRouter()
const shops = ref<PublicShop[]>([]); const search = ref(''); const error = ref(''); const loading = ref(true)
const meta = ref({ current_page: 1, last_page: 1, per_page: 12, total: 0 })
let request = 0
async function load() {
  const current = ++request; loading.value = true; error.value = ''
  search.value = String(route.query.q ?? '')
  try {
    const result = (await shopsApi.list({ q: search.value || undefined, page: Number(route.query.page) || 1 })).data
    if (current === request) { shops.value = result.data; meta.value = result.meta }
  } catch (cause) { if (current === request) error.value = extractErrorMessage(cause, t('seller.load_failed')) }
  finally { if (current === request) loading.value = false }
}
function find() { router.push({ name: 'shops', query: search.value.trim() ? { q: search.value.trim() } : {} }) }
watch(() => route.fullPath, load, { immediate: true })
onBeforeUnmount(() => request++)
</script>
<template>
  <main class="container-app space-y-7 py-8">
    <div><h1 class="text-3xl font-bold">{{ t('storefront.title') }}</h1><p class="mt-2 text-muted">{{ t('storefront.help') }}</p></div>
    <form class="flex max-w-lg gap-3" @submit.prevent="find"><input v-model="search" class="input min-w-0 flex-1" maxlength="100" :aria-label="t('storefront.search_shops')" :placeholder="t('storefront.search_shops')" /><button class="btn-primary"><Search :size="18" /><span class="sr-only">{{ t('actions.search') }}</span></button></form>
    <p v-if="error" role="alert" class="card p-5 text-red-600">{{ error }} <button class="ml-2 underline" @click="load">{{ t('actions.retry') }}</button></p>
    <div v-else-if="loading" class="grid animate-pulse gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-busy="true"><div v-for="i in 6" :key="i" class="card h-64 bg-border-gray/20"></div></div>
    <EmptyState v-else-if="!shops.length" :title="t('storefront.no_shops')" />
    <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink v-for="shop in shops" :key="shop.id" :to="{ name: 'shop-storefront', params: { slug: shop.slug } }" class="card group overflow-hidden rounded-2xl transition hover:shadow-lg">
        <AssetImage :src="shop.banner" :alt="shop.name" class="h-32 w-full bg-primary/5" />
        <div class="p-5"><div class="flex items-center gap-3"><AssetImage :src="shop.logo" :alt="shop.name" class="h-12 w-12 rounded-xl border border-border-gray bg-surface" /><div class="min-w-0"><h2 class="truncate text-lg font-semibold group-hover:text-primary">{{ shop.name }}</h2><p class="text-xs text-muted">{{ [shop.city, shop.province].filter(Boolean).join(', ') }}</p></div><Store :size="18" class="ml-auto shrink-0 text-primary" /></div><p class="mt-4 line-clamp-2 min-h-10 text-sm text-muted">{{ shop.description }}</p><p class="mt-4 text-xs text-muted">{{ t('storefront.product_count', { count: shop.products_count }) }}<span v-if="shop.rating_count"> · ★ {{ shop.rating_avg }} ({{ shop.rating_count }})</span></p></div>
      </RouterLink>
    </div>
    <BasePagination v-if="!error && !loading" :page="meta.current_page" :page-count="meta.last_page" :total-items="meta.total" :page-size="meta.per_page" @update:page="router.push({ query: { ...route.query, page: $event } })" />
  </main>
</template>

