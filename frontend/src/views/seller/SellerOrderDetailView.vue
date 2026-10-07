<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ArrowLeft, RefreshCw } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { sellerApi } from '@/api/seller'
import type { ApiShopOrder } from '@/api/checkout'
import { extractErrorMessage } from '@/api/errors'
import ShopOrderManagement from '@/components/ShopOrderManagement.vue'

const { t } = useI18n()
const route = useRoute()
const shopId = computed(() => Number(route.params.id))
const orderId = computed(() => Number(route.params.orderId))
const allocation = ref<ApiShopOrder | null>(null)
const loading = ref(true)
const error = ref('')
let requestId = 0

async function load() {
  const currentId = ++requestId
  loading.value = true
  error.value = ''
  try {
    const response = await sellerApi.getOrder(shopId.value, orderId.value)
    if (currentId === requestId) allocation.value = response.data.data
  } catch (failure) {
    if (currentId === requestId) { allocation.value = null; error.value = extractErrorMessage(failure, t('marketplace.load_failed')) }
  } finally {
    if (currentId === requestId) loading.value = false
  }
}
watch(() => [shopId.value, orderId.value], () => { void load() }, { immediate: true })
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3"><div class="flex min-w-0 flex-1 items-center gap-3"><RouterLink :to="{ name: 'seller-shop-orders', params: { id: shopId } }" class="btn-icon shrink-0" :aria-label="t('actions.back')"><ArrowLeft class="h-5 w-5" /></RouterLink><div class="min-w-0"><h1 class="break-all text-xl sm:text-2xl font-semibold text-ink">{{ allocation?.shop_order_number ?? t('marketplace.shop_order') }}</h1><p class="mt-1 text-sm text-gray-500 dark:text-muted">{{ t('marketplace.owner_order_description') }}</p></div></div><button type="button" class="btn-secondary btn-sm" :disabled="loading" @click="load"><RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />{{ t('actions.refresh') }}</button></header>
    <p v-if="loading" class="card p-10 text-center text-sm text-gray-500 dark:text-muted" role="status">{{ t('common.loading') }}</p>
    <div v-else-if="error" role="alert" class="card p-8 text-center"><p class="text-sm text-red-600 dark:text-red-400">{{ error }}</p><button type="button" class="btn-primary btn-sm mt-4" @click="load">{{ t('actions.retry') }}</button></div>
    <ShopOrderManagement v-else-if="allocation" :allocation="allocation" :shop-id="shopId" @updated="allocation = $event" />
  </div>
</template>
