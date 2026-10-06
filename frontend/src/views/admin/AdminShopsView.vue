<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Ban, Check, CircleCheck, CircleX, Clock3, MoreHorizontal, Plus, Search, Store, Trash2, X } from 'lucide-vue-next'
import { adminApi, type AdminShop } from '@/api/admin'
import { extractErrorMessage } from '@/api/errors'
import BaseModal from '@/components/BaseModal.vue'
import BasePagination from '@/components/BasePagination.vue'
import DataTableSkeleton from '@/components/DataTableSkeleton.vue'
import EmptyState from '@/components/EmptyState.vue'
import StatusTag from '@/components/StatusTag.vue'
import { formatCompactNumber, formatDate } from '@/utils/format'

type ShopAction = 'active' | 'suspended' | 'rejected' | 'delete'
interface Summary { total: number; pending: number; active: number; suspended: number; rejected: number }

const shops = ref<AdminShop[]>([])
const loading = ref(true)
const error = ref('')
const q = ref('')
const status = ref('')
const page = ref(1)
const pages = ref(1)
const total = ref(0)
const summary = ref<Summary>({ total: 0, pending: 0, active: 0, suspended: 0, rejected: 0 })
const activeMenuId = ref<number | null>(null)
const actionId = ref<number | null>(null)
const pendingAction = ref<{ shop: AdminShop; action: ShopAction } | null>(null)
const rejectionReason = ref('')

const tabs = computed(() => [
  { key: '', label: 'All Shops', count: summary.value.total }, { key: 'pending', label: 'Pending', count: summary.value.pending },
  { key: 'active', label: 'Active', count: summary.value.active }, { key: 'suspended', label: 'Suspended', count: summary.value.suspended },
  { key: 'rejected', label: 'Rejected', count: summary.value.rejected }
])
const cards = computed(() => [
  { label: 'Total Shops', value: summary.value.total, icon: Store, tone: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300', note: 'All registered shops' },
  { label: 'Pending Shops', value: summary.value.pending, icon: Clock3, tone: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300', note: 'Waiting for approval' },
  { label: 'Active Shops', value: summary.value.active, icon: CircleCheck, tone: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300', note: 'Currently selling' },
  { label: 'Suspended Shops', value: summary.value.suspended, icon: Ban, tone: 'bg-red-50 text-red-500 dark:bg-red-500/15 dark:text-red-300', note: 'Temporarily blocked' },
  { label: 'Rejected Shops', value: summary.value.rejected, icon: CircleX, tone: 'bg-rose-50 text-rose-500 dark:bg-rose-500/15 dark:text-rose-300', note: 'Applications rejected' }
])
const actionTitle = computed(() => ({ active: 'Approve shop', suspended: 'Suspend shop', rejected: 'Reject shop application', delete: 'Delete shop' }[pendingAction.value?.action ?? 'active']))
const actionCopy = computed(() => {
  const item = pendingAction.value
  if (!item) return ''
  if (item.action === 'active') return `Approve ${item.shop.name} and allow it to sell on the marketplace?`
  if (item.action === 'suspended') return `Suspend ${item.shop.name}? Customers will no longer be able to shop from it.`
  if (item.action === 'delete') return `Delete ${item.shop.name}? This action cannot be undone.`
  return `Reject ${item.shop.name}? The applicant will receive the reason you provide.`
})

async function load(target = page.value) {
  loading.value = true; error.value = ''
  try {
    const { data } = await adminApi.listShops({ q: q.value || undefined, status: status.value || undefined, page: target })
    shops.value = data.data; page.value = data.meta.current_page; pages.value = data.meta.last_page; total.value = data.meta.total
  } catch (e) { error.value = extractErrorMessage(e, 'Could not load shops.') } finally { loading.value = false }
}
async function loadSummary() {
  try {
    const [all, pending, active, suspended, rejected] = await Promise.all([adminApi.listShops(), adminApi.listShops({ status: 'pending' }), adminApi.listShops({ status: 'active' }), adminApi.listShops({ status: 'suspended' }), adminApi.listShops({ status: 'rejected' })])
    summary.value = { total: all.data.meta.total, pending: pending.data.meta.total, active: active.data.meta.total, suspended: suspended.data.meta.total, rejected: rejected.data.meta.total }
  } catch { /* table error state remains the primary feedback */ }
}
function selectTab(next: string) { status.value = next; page.value = 1; void load(1) }
function search() { page.value = 1; void load(1) }
function openAction(shop: AdminShop, action: ShopAction) { activeMenuId.value = null; rejectionReason.value = ''; pendingAction.value = { shop, action } }
function closeAction() { pendingAction.value = null; rejectionReason.value = '' }
async function confirmAction() {
  const item = pendingAction.value
  if (!item || (item.action === 'rejected' && !rejectionReason.value.trim())) return
  actionId.value = item.shop.id
  try {
    if (item.action === 'delete') await adminApi.deleteShop(item.shop.id)
    else await adminApi.updateShopStatus(item.shop.id, item.action, item.action === 'rejected' ? rejectionReason.value.trim() : undefined)
    closeAction(); await Promise.all([load(), loadSummary()])
  } catch (e) { error.value = extractErrorMessage(e, 'Could not update this shop.') } finally { actionId.value = null }
}
onMounted(() => { void Promise.all([load(), loadSummary()]) })
</script>

<template>
  <div class="mx-auto max-w-[1600px] space-y-4 sm:space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-2xl font-bold tracking-tight text-ink sm:text-[27px]">Shops</h1><p class="mt-1 text-sm text-gray-500 dark:text-muted">Manage all shops in the marketplace. Approve, suspend or monitor shop activities.</p></div><RouterLink :to="{ name: 'admin-shop-create' }" class="btn-primary !px-4 !py-2"><Plus class="h-4 w-4" />Add New Shop</RouterLink></section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5"><article v-for="card in cards" :key="card.label" class="card p-4"><div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="card.tone"><component :is="card.icon" class="h-5 w-5" /></div><div><p class="text-xs font-medium text-gray-500 dark:text-muted">{{ card.label }}</p><p class="mt-1 text-xl font-bold text-ink">{{ formatCompactNumber(card.value) }}</p><p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ card.note }}</p></div></div></article></section>

    <section class="flex flex-wrap items-center justify-between gap-3"><div class="flex max-w-full items-center gap-1 overflow-x-auto pb-1"><button v-for="tab in tabs" :key="tab.key" type="button" class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold transition-colors" :class="status === tab.key ? 'bg-primary text-white shadow-sm' : 'text-gray-500 hover:bg-surface-hover dark:text-muted'" @click="selectTab(tab.key)">{{ tab.label }} <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px]" :class="status === tab.key ? 'bg-white/20' : 'bg-gray-100 dark:bg-surface-hover'">{{ tab.count }}</span></button></div><div class="flex w-full gap-2 sm:w-auto"><div class="relative flex-1 sm:w-60"><Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" /><input v-model="q" class="input h-9 w-full pl-9 text-xs" placeholder="Search shops..." @keyup.enter="search" /></div><select v-model="status" class="select h-9 w-32 !py-1.5 text-xs" @change="search"><option value="">All Statuses</option><option value="pending">Pending</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="rejected">Rejected</option></select></div></section>

    <DataTableSkeleton v-if="loading" :rows="7" :columns="7" />
    <div v-else-if="error" class="card p-8 text-center"><p class="text-red-600">{{ error }}</p><button class="btn-secondary btn-sm mt-4" @click="load()">Retry</button></div>
    <div v-else-if="shops.length" class="card overflow-visible"><div class="overflow-x-auto"><table class="w-full min-w-[950px] text-sm"><thead class="border-b border-border-gray bg-canvas/60 text-left text-[11px] uppercase tracking-wide text-gray-500 dark:text-muted"><tr><th class="px-3 py-3">#</th><th class="px-3 py-3">Shop</th><th class="px-3 py-3">Contact</th><th class="px-3 py-3 text-center">Products</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Created At</th><th class="px-3 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-border-gray"><tr v-for="(shop, index) in shops" :key="shop.id" class="transition-colors hover:bg-canvas/50"><td class="px-3 py-3 text-gray-500">{{ (page - 1) * 20 + index + 1 }}</td><td class="px-3 py-3"><div class="flex items-center gap-2.5"><img v-if="shop.logo" :src="shop.logo" :alt="shop.name" class="h-8 w-8 rounded-lg border border-border-gray object-cover" /><div v-else class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-xs font-bold text-primary">{{ shop.name.charAt(0) }}</div><div><p class="font-semibold text-ink">{{ shop.name }}</p><p class="text-xs text-gray-500 dark:text-muted">{{ shop.code || shop.slug }}</p></div></div></td><td class="px-3 py-3"><p class="text-xs font-medium text-ink">{{ shop.phone ?? '—' }}</p><p class="text-xs text-gray-500 dark:text-muted">{{ shop.email ?? shop.city ?? '—' }}</p></td><td class="px-3 py-3 text-center font-medium text-ink">{{ shop.products_count ?? 0 }}</td><td class="px-3 py-3"><StatusTag :status="shop.status" /></td><td class="px-3 py-3 text-xs text-gray-500 dark:text-muted">{{ formatDate(shop.created_at) }}</td><td class="relative px-3 py-3 text-right"><button class="btn-icon h-8 w-8" title="Shop actions" @click="activeMenuId = activeMenuId === shop.id ? null : shop.id"><MoreHorizontal class="h-4 w-4" /></button><div v-if="activeMenuId === shop.id" class="absolute right-5 top-11 z-20 w-40 overflow-hidden rounded-lg border border-border-gray bg-surface py-1 text-left shadow-popover"><RouterLink :to="{ name: 'admin-shop-edit', params: { id: shop.id } }" class="block px-3 py-2 text-xs font-medium text-gray-700 hover:bg-canvas dark:text-muted">Edit shop</RouterLink><button v-if="shop.status !== 'active'" class="flex w-full items-center gap-2 px-3 py-2 text-xs font-medium text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10" @click="openAction(shop, 'active')"><Check class="h-3.5 w-3.5" />Approve</button><button v-if="shop.status === 'active'" class="flex w-full items-center gap-2 px-3 py-2 text-xs font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-500/10" @click="openAction(shop, 'suspended')"><Ban class="h-3.5 w-3.5" />Suspend</button><button v-if="shop.status === 'pending'" class="flex w-full items-center gap-2 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" @click="openAction(shop, 'rejected')"><X class="h-3.5 w-3.5" />Reject</button><button v-if="!shop.is_default" class="flex w-full items-center gap-2 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" @click="openAction(shop, 'delete')"><Trash2 class="h-3.5 w-3.5" />Delete</button></div></td></tr></tbody></table></div><div class="border-t border-border-gray p-4"><BasePagination :page="page" :page-count="pages" :total-items="total" :page-size="20" @update:page="load" /></div></div>
    <EmptyState v-else title="No shops found" description="Try changing the search or status filter."><template #icon><Store class="h-10 w-10 text-gray-300" /></template></EmptyState>

    <BaseModal :model-value="pendingAction !== null" size="sm" :title="actionTitle" @update:model-value="closeAction"><p class="text-sm text-gray-600 dark:text-muted">{{ actionCopy }}</p><template v-if="pendingAction?.action === 'rejected'"><label class="mt-4 block text-sm font-medium text-ink" for="shop-rejection-reason">Reason for rejection</label><textarea id="shop-rejection-reason" v-model="rejectionReason" class="input mt-2 min-h-24 resize-y" placeholder="Explain what needs to be updated..." maxlength="500" /></template><template #footer><div class="flex justify-end gap-2"><button type="button" class="btn-secondary btn-sm" :disabled="actionId !== null" @click="closeAction">Cancel</button><button type="button" :class="pendingAction?.action === 'active' ? 'btn-primary' : 'btn-danger'" class="btn-sm" :disabled="actionId !== null || (pendingAction?.action === 'rejected' && !rejectionReason.trim())" @click="confirmAction">{{ pendingAction?.action === 'active' ? 'Approve shop' : pendingAction?.action === 'delete' ? 'Delete shop' : pendingAction?.action === 'suspended' ? 'Suspend shop' : 'Reject shop' }}</button></div></template></BaseModal>
  </div>
</template>
