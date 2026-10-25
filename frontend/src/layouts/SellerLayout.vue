<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowUpRight, Box, BriefcaseBusiness, ChevronDown, ChevronRight, ClipboardCheck, Info, LayoutDashboard, LogOut, Menu, PackageCheck, Plus, Settings, Store, Warehouse, X } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { useSellerWorkspace } from '@/stores/sellerWorkspace'
import { useAuthStore } from '@/stores/auth'
import { extractErrorMessage } from '@/api/errors'
import AssetImage from '@/components/AssetImage.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import ContentLayout from '@/layouts/ContentLayout.vue'

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
  { key: 'products', name: 'seller-shop-products', icon: Box },
  { key: 'stock', name: 'seller-shop-inventory', icon: Warehouse },
  { key: 'orders', name: 'seller-shop-orders', icon: ClipboardCheck },
  { key: 'settings', name: 'seller-shop-settings', icon: Settings },
  { key: 'returns', name: 'seller-shop-returns', icon: PackageCheck },
  { key: 'payments', name: 'seller-shop-payments', icon: BriefcaseBusiness }
]
const activeKey = computed(() => nav.find(item => item.key !== 'overview' && String(route.name).includes(item.key === 'stock' ? 'inventory' : item.key === 'products' ? 'product' : item.key === 'orders' ? 'order' : item.key))?.key ?? 'overview')
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
    <aside ref="sidebar" :role="!isDesktop && mobileOpen ? 'dialog' : undefined" :aria-modal="!isDesktop && mobileOpen ? true : undefined" :aria-label="t('seller.manage_shop')" id="seller-navigation" class="seller-sidebar fixed z-50 flex flex-col bg-surface transition-transform lg:translate-x-0" :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'" :inert="!isDesktop && !mobileOpen">
      <div class="seller-brand flex shrink-0 items-center justify-between">
        <RouterLink to="/seller" class="flex items-center gap-4"><span class="seller-brand-icon flex shrink-0 items-center justify-center text-white"><Store :size="27" :stroke-width="1.7" /></span><span><span class="block text-[23px] font-extrabold leading-tight tracking-tight">E-KHMER</span><span class="mt-1 block text-sm text-muted">{{ t('seller.workspace') }}</span></span></RouterLink>
        <button type="button" class="btn-icon lg:hidden" :aria-label="t('actions.close')" @click="mobileOpen = false"><X :size="18" /></button>
      </div>
      <div class="seller-shop shrink-0 rounded-xl border border-border-gray">
        <div class="mb-3 flex items-center justify-between gap-2"><p class="text-[12px] font-semibold uppercase text-muted">{{ t('seller.active_shop') }}</p><RouterLink to="/seller/application" class="seller-add-shop text-muted hover:text-primary" :title="t('seller.add_shop')" :aria-label="t('seller.add_shop')"><Plus :size="15" /></RouterLink></div>
        <div v-if="workspace.currentShop" class="relative flex min-w-0 items-center gap-3">
          <AssetImage v-if="workspace.currentShop.logo" :src="workspace.currentShop.logo" :alt="workspace.currentShop.name" class="h-9 w-9 shrink-0 rounded-lg border border-border-gray object-cover" /><span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><Store :size="20" /></span>
          <template v-if="workspace.shops.length > 1"><label for="seller-shop-select" class="sr-only">{{ t('seller.switch_shop') }}</label><select id="seller-shop-select" :value="workspace.selectedId" class="seller-shop-select min-w-0 flex-1 appearance-none bg-transparent pr-5 text-[14px] font-semibold" @change="switchShop"><option v-for="shop in workspace.shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option></select><ChevronDown :size="17" class="pointer-events-none absolute right-0 text-muted" /></template>
          <RouterLink v-else :to="{ name: 'seller-shop-settings', params: { id: workspace.currentShop.id } }" class="flex min-w-0 flex-1 items-center justify-between gap-2"><span class="truncate text-[14px] font-semibold tracking-tight">{{ workspace.currentShop.name }}</span><ChevronRight :size="17" class="shrink-0 text-muted" /></RouterLink>
        </div>
        <p v-else class="text-sm text-muted">{{ t('seller.no_shop') }}</p>
      </div>
      <nav class="seller-nav flex-1 space-y-1 overflow-y-auto" :aria-label="t('seller.manage_shop')">
        <RouterLink v-for="item in workspace.selectedId ? nav : nav.slice(0, 1)" :key="item.key" :to="workspace.selectedId ? { name: item.name, params: { id: workspace.selectedId } } : { name: 'seller-dashboard' }" class="seller-nav-link relative flex items-center gap-4 rounded-xl font-medium transition-colors" :class="activeKey === item.key && !isApplication ? 'seller-nav-active text-primary' : 'text-muted hover:bg-canvas hover:text-ink'" :aria-current="activeKey === item.key && !isApplication ? 'page' : undefined"><component :is="item.icon" :size="22" :stroke-width="1.8" class="shrink-0" /><span>{{ t(`seller.${item.key}`) }}</span></RouterLink>
        <RouterLink v-if="!workspace.selectedId || isApplication" to="/seller/application" class="seller-nav-link flex items-center gap-4 rounded-xl font-medium" :class="isApplication ? 'seller-nav-active text-primary' : 'text-muted hover:bg-canvas'"><Plus :size="22" />{{ t('seller.add_shop') }}</RouterLink>
      </nav>
      <div class="seller-shop-note flex shrink-0 items-start gap-3 rounded-xl border border-primary/5 bg-primary/5"><Info :size="21" class="mt-0.5 shrink-0 fill-primary text-surface" /><div><p class="text-[14px] leading-6 text-muted">{{ t('seller.shop_only') }}</p><RouterLink to="/shop" class="mt-3 inline-flex items-center gap-2 text-[15px] font-medium text-primary">{{ t('seller.visit_store') }}<ArrowUpRight :size="15" /></RouterLink></div></div>
      <div class="seller-profile flex shrink-0 items-center gap-3 border-t border-border-gray"><RouterLink to="/account/profile" class="flex min-w-0 flex-1 items-center gap-4"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xl font-bold text-primary">{{ auth.user?.name.charAt(0) }}</span><span class="truncate text-sm font-semibold">{{ auth.user?.name }}</span></RouterLink><button type="button" class="btn-icon" :title="t('seller.sign_out')" :aria-label="t('seller.sign_out')" @click="signOut"><LogOut :size="20" /></button></div>
    </aside>
    <div class="seller-main min-w-0" :inert="!isDesktop && mobileOpen">
      <header class="seller-topbar sticky z-30 border border-border-gray bg-surface/95 backdrop-blur">
        <ContentLayout class="seller-topbar-content flex items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3"><button ref="menuButton" type="button" class="btn-icon lg:hidden" :aria-label="t('seller.menu')" aria-controls="seller-navigation" :aria-expanded="mobileOpen" @click="mobileOpen = true"><Menu :size="22" /></button><div class="min-w-0"><p class="text-xs text-muted">{{ t('seller.workspace') }}</p><p class="truncate text-sm font-semibold">{{ workspace.currentShop?.name ?? t('seller.manage_shop') }}</p></div></div>
        <div class="seller-topbar-actions flex shrink-0 items-center"><span class="seller-language flex items-center gap-1"><LanguageSwitcher /><ChevronDown :size="17" class="hidden sm:block" aria-hidden="true" /></span><ThemeToggle /><RouterLink to="/shop" class="seller-marketplace hidden items-center justify-center gap-3 rounded-xl bg-primary/[0.08] font-semibold text-primary transition-colors hover:bg-primary/15 sm:inline-flex">{{ t('seller.visit_store') }}<ArrowUpRight :size="20" /></RouterLink></div>
        </ContentLayout>
      </header>
      <ContentLayout as="main" id="seller-content" class="seller-content">
        <div v-if="error" role="alert" class="card p-6"><p class="text-sm text-red-600 dark:text-red-400">{{ error }}</p><button class="btn-secondary mt-4" @click="load">{{ t('actions.retry') }}</button></div>
        <div v-else-if="workspace.loading" class="grid animate-pulse gap-4" aria-busy="true"><div class="h-16 rounded-xl bg-border-gray/40"></div><div class="h-64 rounded-xl bg-border-gray/40"></div><span class="sr-only">{{ t('common.loading') }}</span></div>
        <RouterView v-else v-slot="{ Component }"><component :is="Component" :key="String(route.name) + ':' + String(route.params.id ?? '') + ':' + String(route.params.productId ?? '') + ':' + String(route.params.orderId ?? '')" /></RouterView>
      </ContentLayout>
    </div>
  </div>
</template>

<style scoped>
.seller-workspace {
  --color-ink: 10 18 44;
  --color-muted: 92 108 140;
  --color-canvas: 245 248 253;
  --color-surface: 255 255 255;
  --color-border: 229 236 246;
  --color-primary: 30 99 255;
  --seller-sidebar-width: 294px;
  color: rgb(var(--color-ink));
  background: radial-gradient(ellipse at 70% 0%, rgb(235 243 255 / 45%), transparent 65%), rgb(var(--color-canvas));
}
:global(html.dark .seller-workspace) {
  --color-ink: 240 245 255;
  --color-muted: 157 173 199;
  --color-canvas: 11 18 32;
  --color-surface: 23 34 53;
  --color-border: 43 57 80;
  --color-primary: 96 153 255;
  background: rgb(var(--color-canvas));
}
.seller-sidebar { inset: 10px auto 10px 12px; width: var(--seller-sidebar-width); border: 1px solid rgb(var(--color-border) / 65%); border-radius: 18px; box-shadow: 0 4px 24px rgb(41 83 145 / 2%); }
.seller-sidebar.-translate-x-full { transform: translateX(calc(-100% - 12px)); }
.seller-brand { min-height: 96px; padding: 18px 20px 22px; }
.seller-brand-icon { width: 56px; height: 56px; border: 1px solid rgb(255 255 255 / 20%); border-radius: 17px; background: linear-gradient(145deg, #1762ff, #2e69ff); box-shadow: inset 0 0 0 4px rgb(255 255 255 / 5%), 0 5px 12px rgb(30 99 255 / 10%); }
.seller-shop { margin: 6px 20px 0; padding: 12px 14px; background: rgb(var(--color-canvas) / 70%); }
.seller-add-shop { display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 6px; }
.seller-add-shop:hover { background: rgb(var(--color-primary) / 8%); }
.seller-shop-select { height: 36px; text-overflow: ellipsis; cursor: pointer; }
.seller-shop-select option { color: rgb(var(--color-ink)); background: rgb(var(--color-surface)); }
.seller-nav { min-height: 100px; margin: 24px 0 12px; padding: 0 20px; }
.seller-nav-link { min-height: 52px; padding: 12px 16px; font-size: 17px; }
.seller-nav-active { background: rgb(var(--color-primary) / 10%); }
.seller-nav-active::before { content: ''; position: absolute; left: -5px; top: 11px; bottom: 11px; width: 4px; border-radius: 4px; background: rgb(var(--color-primary)); }
.seller-shop-note { margin: 12px 20px 30px; padding: 16px 14px 18px; }
.seller-profile { min-height: 104px; padding: 20px 24px; }
.seller-main { padding: 10px 12px 0; }
.seller-topbar { top: 10px; border-radius: 16px; box-shadow: 0 2px 5px rgb(41 83 145 / 2%); }
.seller-topbar-content { min-height: 84px; padding: 14px 30px; }
.seller-topbar-content > div:first-child p:first-child { font-size: 15px; }
.seller-topbar-content > div:first-child p:last-child { margin-top: 2px; font-size: 19px; }
.seller-topbar-actions { gap: 18px; }
.seller-language { padding-right: 18px; border-right: 1px solid rgb(var(--color-border)); }
.seller-marketplace { min-height: 52px; padding: 12px 24px; font-size: 18px; }
.seller-content { padding: 26px 22px 24px; }
.seller-workspace :deep(.card) { box-shadow: 0 3px 9px rgb(41 83 145 / 3%); }
.seller-workspace :deep(button), .seller-workspace :deep(a), .seller-workspace :deep(input), .seller-workspace :deep(select), .seller-workspace :deep(textarea) { outline-offset: 3px; }
.seller-workspace :deep(:focus-visible) { outline: 2px solid rgb(var(--color-primary)); }
@media (min-width: 1024px) { .seller-main { padding-left: calc(var(--seller-sidebar-width) + 20px); } .seller-sidebar { transform: translateX(0) !important; } }
@media (max-width: 1279px) and (min-width: 1024px) { .seller-workspace { --seller-sidebar-width: 254px; } .seller-brand { padding-inline: 16px; } .seller-brand-icon { width: 48px; height: 48px; } .seller-brand :deep(.text-\[23px\]) { font-size: 20px; } .seller-shop, .seller-shop-note { margin-inline: 14px; } .seller-nav { padding-inline: 14px; } .seller-nav-link { font-size: 16px; gap: 12px; } .seller-profile { padding-inline: 18px; } .seller-marketplace { padding-inline: 16px; font-size: 16px; } }
@media (max-width: 639px) { .seller-sidebar { --seller-sidebar-width: min(294px, calc(100vw - 28px)); } .seller-topbar { top: 8px; } .seller-topbar-content { min-height: 70px; padding: 10px 12px; gap: 8px; } .seller-topbar-content > div:first-child { gap: 8px; } .seller-topbar-content > div:first-child p:first-child { font-size: 11px; } .seller-topbar-content > div:first-child p:last-child { max-width: 42vw; font-size: 13px; } .seller-topbar-actions { gap: 4px; } .seller-language { padding-right: 4px; } .seller-content { padding: 24px 4px; } }
@media (max-height: 780px) { .seller-brand { min-height: 80px; padding-block: 12px; } .seller-nav { margin-top: 18px; } .seller-nav-link { min-height: 46px; } .seller-shop-note { margin-bottom: 18px; padding-block: 12px; } .seller-profile { min-height: 80px; padding-block: 16px; } }
@media (prefers-reduced-motion: reduce) { .seller-sidebar { transition: none; } }
</style>
