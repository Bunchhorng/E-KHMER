<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Box, BriefcaseBusiness, ChartNoAxesCombined, ChevronDown, ChevronRight, ClipboardCheck, LayoutDashboard, PackageCheck, Plus, Settings, Store, Warehouse } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage } from '@/api/errors'
import AssetImage from '@/components/AssetImage.vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import type { DashboardNavGroup } from '@/components/dashboard/types'

const workspace = useSellerWorkspace()
// Clear previous workspace data before rendering, including after account changes.
workspace.reset()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const error = ref('')
const nav = [
  { key: 'overview', name: 'seller-shop-dashboard', icon: LayoutDashboard },
  { key: 'analytics', name: 'seller-shop-analytics', icon: ChartNoAxesCombined },
  { key: 'products', name: 'seller-shop-products', icon: Box },
  { key: 'stock', name: 'seller-shop-inventory', icon: Warehouse },
  { key: 'orders', name: 'seller-shop-orders', icon: ClipboardCheck },
  { key: 'settings', name: 'seller-shop-settings', icon: Settings },
  { key: 'returns', name: 'seller-shop-returns', icon: PackageCheck },
  { key: 'payments', name: 'seller-shop-payments', icon: BriefcaseBusiness }
]
const activeKey = computed(() => nav.find(item => item.key !== 'overview' && String(route.name).includes(item.key === 'stock' ? 'inventory' : item.key === 'products' ? 'product' : item.key === 'orders' ? 'order' : item.key))?.key ?? 'overview')
const isApplication = computed(() => route.name === 'seller-application')
const isEditor = computed(() => route.name === 'seller-shop-product-create' || route.name === 'seller-shop-product-edit')
const groups = computed<DashboardNavGroup[]>(() => [{
  items: [
    ...(workspace.selectedId ? nav : nav.slice(0, 1)).map(item => ({
      id: item.key, label: t(item.key === 'analytics' ? 'analytics.nav' : `seller.${item.key}`), icon: item.icon,
      to: workspace.selectedId ? { name: item.name, params: { id: workspace.selectedId } } : { name: 'seller-dashboard' },
      active: activeKey.value === item.key && !isApplication.value
    })),
    ...(!workspace.selectedId || isApplication.value ? [{
      id: 'application', label: t('seller.add_shop'), icon: Plus,
      to: { name: 'seller-application' }, active: isApplication.value
    }] : [])
  ]
}])

async function load() {
  error.value = ''
  try {
    await workspace.loadShops()
    if (!auth.isAuthenticated) return
    const requested = Number(route.params.id)
    if (requested && !workspace.shops.some(shop => shop.id === requested)) {
      await router.replace({ name: 'seller-dashboard' })
    } else if (requested) workspace.selectShop(requested)
  } catch (cause) {
    error.value = extractErrorMessage(cause, t('seller.load_failed'))
  }
}

async function switchShop(event: Event) {
  const id = Number((event.target as HTMLSelectElement).value)
  // Navigate first: form leave guards can cancel a switch without changing context.
  await router.push({ name: nav.find(item => item.key === activeKey.value)?.name ?? 'seller-shop-dashboard', params: { id } })
  ;(event.target as HTMLSelectElement).value = String(workspace.selectedId ?? '')
}
watch(() => route.fullPath, () => {
  const id = Number(route.params.id)
  if (id && workspace.shops.some(shop => shop.id === id)) workspace.selectShop(id)
})
watch(() => auth.user?.id, () => { workspace.reset(); void load() })
onMounted(() => { void load() })
async function signOut() { await auth.logout(); workspace.reset(); await router.push('/') }
</script>

<template>
  <DashboardLayout
    class="seller-workspace"
    :class="{ 'seller-editor': isEditor }"
    navigation-id="seller-navigation"
    content-id="seller-content"
    :label="t('seller.workspace')"
    :title="workspace.currentShop?.name ?? t('seller.manage_shop')"
    home-to="/seller"
    :groups="groups"
    :compact="isEditor"
    @sign-out="signOut"
  >
    <template #context>
      <div class="seller-shop shrink-0 rounded-xl border border-border-gray">
        <div class="mb-3 flex items-center justify-between gap-2"><p class="text-[12px] font-semibold uppercase text-muted">{{ t('seller.active_shop') }}</p><RouterLink to="/seller/application" class="seller-add-shop text-muted hover:text-primary" :title="t('seller.add_shop')" :aria-label="t('seller.add_shop')"><Plus :size="15" /></RouterLink></div>
        <div v-if="workspace.currentShop" class="relative flex min-w-0 items-center gap-3">
          <AssetImage v-if="workspace.currentShop.logo" :src="workspace.currentShop.logo" :alt="workspace.currentShop.name" class="h-9 w-9 shrink-0 rounded-lg border border-border-gray object-cover" /><span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><Store :size="20" /></span>
          <template v-if="workspace.shops.length > 1"><label for="seller-shop-select" class="sr-only">{{ t('seller.switch_shop') }}</label><select id="seller-shop-select" :value="workspace.selectedId" class="seller-shop-select min-w-0 flex-1 appearance-none bg-transparent pr-5 text-[14px] font-semibold" @change="switchShop"><option v-for="shop in workspace.shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option></select><ChevronDown :size="17" class="pointer-events-none absolute right-0 text-muted" /></template>
          <RouterLink v-else :to="{ name: 'seller-shop-settings', params: { id: workspace.currentShop.id } }" class="flex min-w-0 flex-1 items-center justify-between gap-2"><span class="truncate text-[13px] font-semibold tracking-tight" :title="workspace.currentShop.name">{{ workspace.currentShop.name }}</span><ChevronRight :size="17" class="shrink-0 text-muted" /></RouterLink>
        </div>
        <p v-else class="text-sm text-muted">{{ t('seller.no_shop') }}</p>
      </div>
    </template>
        <div v-if="error" role="alert" class="card p-6"><p class="text-sm text-red-600 dark:text-red-400">{{ error }}</p><button class="btn-secondary mt-4" @click="load">{{ t('actions.retry') }}</button></div>
        <div v-else-if="workspace.loading" class="grid animate-pulse gap-4" aria-busy="true"><div class="h-16 rounded-xl bg-border-gray/40"></div><div class="h-64 rounded-xl bg-border-gray/40"></div><span class="sr-only">{{ t('common.loading') }}</span></div>
        <RouterView v-else v-slot="{ Component }"><component :is="Component" :key="String(route.name) + ':' + String(route.params.id ?? '') + ':' + String(route.params.productId ?? '') + ':' + String(route.params.orderId ?? '')" /></RouterView>
  </DashboardLayout>
</template>

<style scoped>
.seller-shop { padding: 12px 14px; background: rgb(var(--color-canvas) / 70%); }
.seller-add-shop { display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; }
.seller-add-shop:hover { background: rgb(var(--color-primary) / 8%); }
.seller-shop-select { height: 36px; text-overflow: ellipsis; cursor: pointer; }
.seller-shop-select option { color: rgb(var(--color-ink)); background: rgb(var(--color-surface)); }
</style>
