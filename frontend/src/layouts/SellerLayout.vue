<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowUpRight, Boxes, ChevronDown, LayoutDashboard, LogOut, Menu, Plus, ShoppingBag, Store, Warehouse, X } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage } from '@/api/errors'
import AssetImage from '@/components/AssetImage.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const workspace = useSellerWorkspace()
// Clear previous workspace data before rendering, including after account changes.
workspace.reset()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const mobileOpen = ref(false)
const sidebar = ref<HTMLElement | null>(null)
const menuButton = ref<HTMLButtonElement | null>(null)
const error = ref('')
const desktop = window.matchMedia('(min-width: 1024px)')
const isDesktop = ref(desktop.matches)
const nav = [
  { key: 'overview', name: 'seller-shop-dashboard', icon: LayoutDashboard },
  { key: 'products', name: 'seller-shop-products', icon: Boxes },
  { key: 'stock', name: 'seller-shop-inventory', icon: Warehouse },
  { key: 'orders', name: 'seller-shop-orders', icon: ShoppingBag }
]
const activeKey = computed(() => String(route.name).includes('product') ? 'products' : String(route.name).includes('inventory') ? 'stock' : String(route.name).includes('order') ? 'orders' : 'overview')
const isApplication = computed(() => route.name === 'seller-application')

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
function escape(event: KeyboardEvent) {
  if (!mobileOpen.value) return
  if (event.key === 'Escape') mobileOpen.value = false
  if (event.key === 'Tab' && sidebar.value) {
    const controls = [...sidebar.value.querySelectorAll<HTMLElement>('a[href], button, select')].filter(element => element.getClientRects().length > 0)
    const first = controls[0]
    const last = controls[controls.length - 1]
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
  }
}
watch(mobileOpen, async open => {
  await nextTick()
  if (open && mobileOpen.value) sidebar.value?.querySelector<HTMLElement>('a')?.focus()
  else if (!isDesktop.value) menuButton.value?.focus()
})
function viewport(event: MediaQueryListEvent) { isDesktop.value = event.matches; mobileOpen.value = false }
watch(() => route.fullPath, () => {
  mobileOpen.value = false
  const id = Number(route.params.id)
  if (id && workspace.shops.some(shop => shop.id === id)) workspace.selectShop(id)
})
watch(() => auth.user?.id, () => { workspace.reset(); void load() })
onMounted(() => { void load(); window.addEventListener('keydown', escape); desktop.addEventListener('change', viewport) })
onBeforeUnmount(() => { window.removeEventListener('keydown', escape); desktop.removeEventListener('change', viewport) })
async function signOut() { await auth.logout(); workspace.reset(); await router.push('/') }
</script>

<template>
  <div class="seller-workspace min-h-screen bg-canvas">
    <a href="#seller-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-surface focus:p-3">{{ t('seller.manage_shop') }}</a>
    <button v-if="mobileOpen" type="button" class="fixed inset-0 z-40 bg-black/40 lg:hidden" :aria-label="t('actions.close')" @click="mobileOpen = false"></button>
    <aside ref="sidebar" :role="!isDesktop && mobileOpen ? 'dialog' : undefined" :aria-modal="!isDesktop && mobileOpen ? true : undefined" :aria-label="t('seller.manage_shop')" id="seller-navigation" class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-border-gray bg-surface transition-transform lg:translate-x-0" :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'" :inert="!isDesktop && !mobileOpen">
      <div class="flex h-20 items-center justify-between px-6">
        <RouterLink to="/seller" class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-white"><Store :size="21" /></span><span><span class="block text-lg font-extrabold tracking-tight">E-KHMER</span><span class="text-xs font-medium text-muted">{{ t('seller.workspace') }}</span></span></RouterLink>
        <button type="button" class="btn-icon lg:hidden" :aria-label="t('actions.close')" @click="mobileOpen = false"><X :size="18" /></button>
      </div>
      <div class="mx-4 rounded-xl border border-border-gray bg-canvas p-3">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted">{{ t('seller.active_shop') }}</p>
        <div v-if="workspace.currentShop" class="flex min-w-0 items-center gap-2.5"><AssetImage v-if="workspace.currentShop.logo" :src="workspace.currentShop.logo" :alt="workspace.currentShop.name" class="h-9 w-9 shrink-0 rounded-lg border border-border-gray object-cover" /><span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><Store :size="19" /></span><p class="min-w-0 truncate text-sm font-semibold">{{ workspace.currentShop.name }}</p></div>
        <p v-else class="text-sm text-muted">{{ t('seller.no_shop') }}</p>
        <label v-if="workspace.shops.length > 1" class="relative mt-3 block"><span class="sr-only">{{ t('seller.switch_shop') }}</span><select :value="workspace.selectedId" class="select w-full pr-7 text-sm" @change="switchShop"><option v-for="shop in workspace.shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option></select><ChevronDown :size="15" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted" /></label>
      </div>
      <nav class="mt-7 flex-1 space-y-1 overflow-y-auto px-4" :aria-label="t('seller.manage_shop')">
        <RouterLink v-for="item in workspace.selectedId ? nav : nav.slice(0, 1)" :key="item.key" :to="workspace.selectedId ? { name: item.name, params: { id: workspace.selectedId } } : { name: 'seller-dashboard' }" class="flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-medium transition-colors" :class="activeKey === item.key && !isApplication ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-canvas hover:text-ink'" :aria-current="activeKey === item.key && !isApplication ? 'page' : undefined"><component :is="item.icon" :size="20" /><span>{{ t(`seller.${item.key}`) }}</span></RouterLink>
        <div class="pt-5"><RouterLink to="/seller/application" class="flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-medium" :class="isApplication ? 'bg-primary/10 text-primary' : 'text-muted hover:bg-canvas'"><Plus :size="20" />{{ t('seller.add_shop') }}</RouterLink></div>
      </nav>
      <div class="m-4 rounded-xl bg-primary/5 p-4"><p class="text-xs leading-relaxed text-muted">{{ t('seller.shop_only') }}</p><RouterLink to="/shop" class="mt-3 flex items-center gap-2 text-xs font-semibold text-primary">{{ t('seller.visit_store') }}<ArrowUpRight :size="15" /></RouterLink></div>
      <div class="flex items-center gap-3 border-t border-border-gray px-5 py-4"><RouterLink to="/account/profile" class="flex min-w-0 flex-1 items-center gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary">{{ auth.user?.name.charAt(0) }}</span><span class="truncate text-sm font-medium">{{ auth.user?.name }}</span></RouterLink><button type="button" class="btn-icon" :title="t('seller.sign_out')" :aria-label="t('seller.sign_out')" @click="signOut"><LogOut :size="18" /></button></div>
    </aside>
    <div class="min-w-0 lg:pl-64" :inert="!isDesktop && mobileOpen">
      <header class="sticky top-0 z-30 flex h-20 items-center justify-between gap-3 border-b border-border-gray bg-surface/95 px-4 backdrop-blur sm:px-8">
        <div class="flex min-w-0 items-center gap-3"><button ref="menuButton" type="button" class="btn-icon lg:hidden" :aria-label="t('seller.menu')" aria-controls="seller-navigation" :aria-expanded="mobileOpen" @click="mobileOpen = true"><Menu :size="22" /></button><div class="min-w-0"><p class="text-xs text-muted">{{ t('seller.workspace') }}</p><p class="truncate text-sm font-semibold">{{ workspace.currentShop?.name ?? t('seller.manage_shop') }}</p></div></div>
        <div class="flex shrink-0 items-center gap-1"><LanguageSwitcher /><ThemeToggle /><RouterLink to="/shop" class="btn-secondary btn-sm ml-2 hidden sm:inline-flex">{{ t('seller.visit_store') }}<ArrowUpRight :size="16" /></RouterLink></div>
      </header>
      <main id="seller-content" class="mx-auto min-w-0 max-w-[1440px] p-4 sm:p-8 lg:p-9">
        <div v-if="error" role="alert" class="card p-6"><p class="text-sm text-red-600 dark:text-red-400">{{ error }}</p><button class="btn-secondary mt-4" @click="load">{{ t('actions.retry') }}</button></div>
        <div v-else-if="workspace.loading" class="grid animate-pulse gap-4" aria-busy="true"><div class="h-16 rounded-xl bg-border-gray/40"></div><div class="h-64 rounded-xl bg-border-gray/40"></div><span class="sr-only">{{ t('common.loading') }}</span></div>
        <RouterView v-else v-slot="{ Component }"><component :is="Component" :key="String(route.name) + ':' + String(route.params.id ?? '') + ':' + String(route.params.productId ?? '') + ':' + String(route.params.orderId ?? '')" /></RouterView>
      </main>
    </div>
  </div>
</template>

<style scoped>
.seller-workspace :deep(button), .seller-workspace :deep(a), .seller-workspace :deep(input), .seller-workspace :deep(select), .seller-workspace :deep(textarea) { outline-offset: 3px; }
.seller-workspace :deep(:focus-visible) { outline: 2px solid rgb(var(--color-primary)); }
</style>
