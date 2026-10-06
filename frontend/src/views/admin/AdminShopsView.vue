<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Search, Store } from 'lucide-vue-next'
import { adminApi, type AdminShop } from '@/api/admin'
import { extractErrorMessage } from '@/api/errors'
import BasePagination from '@/components/BasePagination.vue'
import DataTableSkeleton from '@/components/DataTableSkeleton.vue'
import EmptyState from '@/components/EmptyState.vue'
import StatusTag from '@/components/StatusTag.vue'
import { formatDate } from '@/utils/format'

const shops = ref<AdminShop[]>([])
const loading = ref(true)
const error = ref('')
const q = ref('')
const status = ref('')
const page = ref(1)
const pages = ref(1)
const total = ref(0)
const actionId = ref<number | null>(null)

async function load(target = page.value) {
  loading.value = true
  error.value = ''
  try {
    const { data } = await adminApi.listShops({ q: q.value || undefined, status: status.value || undefined, page: target })
    shops.value = data.data
    page.value = data.meta.current_page
    pages.value = data.meta.last_page
    total.value = data.meta.total
  } catch (e) {
    error.value = extractErrorMessage(e, 'Could not load shops.')
  } finally {
    loading.value = false
  }
}

async function setStatus(shop: AdminShop, next: AdminShop['status']) {
  let reason: string | undefined
  if (next === 'rejected') {
    reason = window.prompt('Reason for rejection:')?.trim()
    if (!reason) return
  }
  if (!window.confirm(`Change ${shop.name} to ${next}?`)) return
  actionId.value = shop.id
  try {
    const { data } = await adminApi.updateShopStatus(shop.id, next, reason)
    const index = shops.value.findIndex((item) => item.id === shop.id)
    if (index >= 0) shops.value[index] = data.data
  } catch (e) {
    error.value = extractErrorMessage(e, 'Could not update shop status.')
  } finally {
    actionId.value = null
  }
}

async function deleteShop(shop: AdminShop) {
  if (!window.confirm(`Delete ${shop.name}? This cannot be undone.`)) return
  actionId.value = shop.id
  error.value = ''
  try {
    await adminApi.deleteShop(shop.id)
    shops.value = shops.value.filter((item) => item.id !== shop.id)
    total.value -= 1
  } catch (e) {
    error.value = extractErrorMessage(e, 'Could not delete the shop.')
  } finally {
    actionId.value = null
  }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-sm font-semibold text-primary">MARKETPLACE</p>
        <h1 class="mt-1 text-2xl font-bold text-ink">Shops</h1>
        <p class="mt-1 text-sm text-gray-500">Review seller applications and manage marketplace availability.</p>
      </div>
      <div class="flex items-center gap-3"><span class="chip">{{ total }} shops</span><RouterLink :to="{ name: 'admin-shop-create' }" class="btn-primary">Create shop</RouterLink></div>
    </div>

    <div class="card flex flex-wrap gap-3 p-4">
      <div class="relative min-w-56 flex-1"><Search class="absolute left-3 top-3 h-4 w-4 text-gray-400" /><input v-model="q" class="input pl-9" placeholder="Search shop name or code" @keyup.enter="page = 1; load(1)" /></div>
      <select v-model="status" class="select w-44" @change="page = 1; load(1)"><option value="">All statuses</option><option value="pending">Pending</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="rejected">Rejected</option><option value="closed">Closed</option></select>
      <button class="btn-secondary" @click="page = 1; load(1)">Search</button>
    </div>

    <DataTableSkeleton v-if="loading" :rows="7" :columns="6" />
    <div v-else-if="error" class="card p-8 text-center"><p class="text-red-600">{{ error }}</p><button class="btn-secondary btn-sm mt-4" @click="load()">Retry</button></div>
    <div v-else-if="shops.length" class="card overflow-hidden p-0">
      <div class="overflow-x-auto"><table class="w-full min-w-[860px] text-sm"><thead class="bg-canvas/60 text-left text-xs uppercase text-gray-500"><tr><th class="p-4">Shop</th><th class="p-4">Code</th><th class="p-4">Products</th><th class="p-4">Joined</th><th class="p-4">Status</th><th class="p-4 text-right">Actions</th></tr></thead><tbody class="divide-y divide-border-gray"><tr v-for="shop in shops" :key="shop.id"><td class="p-4"><p class="font-semibold text-ink">{{ shop.name }}</p><p class="text-xs text-gray-500">{{ shop.email ?? shop.city ?? shop.slug }}</p></td><td class="p-4 font-mono text-xs">{{ shop.code }}</td><td class="p-4">{{ shop.products_count ?? 0 }}</td><td class="p-4">{{ formatDate(shop.created_at) }}</td><td class="p-4"><StatusTag :status="shop.status" /></td><td class="p-4 text-right"><div class="flex justify-end gap-2"><RouterLink :to="{ name: 'admin-shop-edit', params: { id: shop.id } }" class="btn-secondary btn-sm">Edit</RouterLink><button v-if="shop.status === 'pending' || shop.status === 'suspended' || shop.status === 'rejected'" class="btn-primary btn-sm" :disabled="actionId === shop.id" @click="setStatus(shop, 'active')">Approve</button><button v-if="shop.status === 'pending'" class="btn-secondary btn-sm text-red-600" :disabled="actionId === shop.id" @click="setStatus(shop, 'rejected')">Reject</button><button v-if="shop.status === 'active'" class="btn-secondary btn-sm" :disabled="actionId === shop.id" @click="setStatus(shop, 'suspended')">Suspend</button><button v-if="!shop.is_default" class="btn-secondary btn-sm text-red-600" :disabled="actionId === shop.id" @click="deleteShop(shop)">Delete</button></div></td></tr></tbody></table></div>
      <div class="p-4"><BasePagination :page="page" :page-count="pages" :total-items="total" :page-size="20" @update:page="load" /></div>
    </div>
    <EmptyState v-else title="No shops found" description="Try changing the search or status filter."><template #icon><Store class="h-10 w-10 text-gray-300" /></template></EmptyState>
  </div>
</template>
