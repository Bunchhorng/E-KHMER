<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { sellerApi, type SellerDashboard } from '@/api/seller'
import { extractErrorMessage } from '@/api/errors'

const dashboard = ref<SellerDashboard | null>(null); const loading = ref(true); const error = ref('')
async function load() { loading.value = true; error.value = ''; try { const { data } = await sellerApi.dashboard(); dashboard.value = data.data } catch (e) { error.value = extractErrorMessage(e, 'Your shop is not active or could not be loaded.') } finally { loading.value = false } }
onMounted(load)
</script>
<template>
  <div class="container-app mx-auto py-10"><div class="mb-7 flex items-end justify-between"><div><p class="text-sm font-semibold text-primary">SELLER CENTER</p><h1 class="mt-1 text-3xl font-bold text-ink">{{ dashboard?.shop.name ?? 'My shop' }}</h1></div><RouterLink to="/seller/application" class="btn-secondary btn-sm">Shop application</RouterLink></div>
    <div v-if="loading" class="card p-10 text-center text-sm text-gray-500">Loading shop dashboard…</div><div v-else-if="error" class="card p-8 text-center"><p class="text-red-600">{{ error }}</p><button class="btn-secondary btn-sm mt-4" @click="load">Retry</button></div>
    <template v-else-if="dashboard"><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div v-for="item in [{label:'Products',value:dashboard.metrics.products},{label:'Active products',value:dashboard.metrics.active_products},{label:'Inactive products',value:dashboard.metrics.inactive_products},{label:'Low stock',value:dashboard.metrics.low_stock}]" :key="item.label" class="card p-5"><p class="text-sm text-gray-500">{{ item.label }}</p><p class="mt-2 text-3xl font-bold text-ink">{{ item.value }}</p></div></div><div class="card mt-6 p-6"><h2 class="font-bold text-ink">Next steps</h2><p class="mt-2 text-sm text-gray-500">Manage your catalog, staff, and shop profile from Seller Center as those marketplace modules are enabled.</p></div></template>
  </div>
</template>
