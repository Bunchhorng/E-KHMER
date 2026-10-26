<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, type RouteLocationRaw } from 'vue-router'
import { ArrowUpRight, ChevronRight, LogOut, Menu, PanelLeftClose, PanelLeftOpen, ShoppingBag, Store, User, X } from 'lucide-vue-next'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import ContentLayout from '@/layouts/ContentLayout.vue'
import type { DashboardNavGroup } from '@/components/dashboard/types'
import { useAuthStore } from '@/stores/auth'
import logo from '@/assets/E-KHMER-LOGO.jpg'

const props = withDefaults(defineProps<{
  navigationId: string
  contentId: string
  label: string
  title: string
  homeTo: RouteLocationRaw
  groups: DashboardNavGroup[]
  collapsed?: boolean
  collapsible?: boolean
  compact?: boolean
}>(), { collapsed: false, collapsible: false, compact: false })
const emit = defineEmits<{ 'update:collapsed': [value: boolean]; signOut: [] }>()
const auth = useAuthStore()
const route = useRoute()
const desktopMedia = window.matchMedia('(min-width: 1024px)')
const isDesktop = ref(desktopMedia.matches)
const mobileOpen = ref(false)
const sidebar = ref<HTMLElement | null>(null)
const menuButton = ref<HTMLButtonElement | null>(null)
const expanded = computed(() => !isDesktop.value || !props.collapsed)

function toggleSidebar() {
  if (isDesktop.value) emit('update:collapsed', !props.collapsed)
  else mobileOpen.value = !mobileOpen.value
}

function onKeydown(event: KeyboardEvent) {
  if (!mobileOpen.value) return
  if (event.key === 'Escape') mobileOpen.value = false
  if (event.key !== 'Tab' || !sidebar.value) return
  const controls = [...sidebar.value.querySelectorAll<HTMLElement>('a[href], button, select, input, [tabindex="0"]')]
    .filter(element => element.getClientRects().length > 0 && !element.matches(':disabled'))
  const first = controls[0]
  const last = controls[controls.length - 1]
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
}

function onViewport(event: MediaQueryListEvent) {
  isDesktop.value = event.matches
  mobileOpen.value = false
}

watch(() => route.fullPath, () => { mobileOpen.value = false })
watch(mobileOpen, async open => {
  await nextTick()
  if (open && mobileOpen.value) sidebar.value?.querySelector<HTMLElement>('a')?.focus()
  else if (!isDesktop.value) menuButton.value?.focus()
})
onMounted(() => {
  desktopMedia.addEventListener('change', onViewport)
  window.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  desktopMedia.removeEventListener('change', onViewport)
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div class="dashboard-workspace" :class="{ 'dashboard-collapsed': isDesktop && collapsed, 'dashboard-compact': compact }">
    <a :href="`#${contentId}`" class="dashboard-skip sr-only focus:not-sr-only">{{ label }}</a>
    <button v-if="mobileOpen" type="button" class="fixed inset-0 z-40 bg-black/40 lg:hidden" :aria-label="$t('actions.close')" @click="mobileOpen = false"></button>
    <aside
      :id="navigationId"
      ref="sidebar"
      class="dashboard-sidebar"
      :class="{ 'dashboard-sidebar-open': mobileOpen }"
      :role="!isDesktop && mobileOpen ? 'dialog' : undefined"
      :aria-modal="!isDesktop && mobileOpen ? true : undefined"
      :aria-label="label"
      :inert="!isDesktop && !mobileOpen"
    >
      <div class="dashboard-brand">
        <RouterLink :to="homeTo" class="flex min-w-0 items-center gap-4" :title="expanded ? undefined : 'E-KHMER'">
          <img :src="logo" alt="" class="dashboard-brand-logo" />
          <span v-if="expanded" class="min-w-0"><span class="block text-lg font-extrabold leading-tight tracking-tight">E-KHMER</span><span class="mt-1 block text-sm text-muted">{{ label }}</span></span>
        </RouterLink>
        <button type="button" class="btn-icon shrink-0 lg:hidden" :aria-label="$t('actions.close')" @click="mobileOpen = false"><X :size="20" /></button>
      </div>
      <RouterLink v-if="auth.user" to="/account/profile" class="dashboard-profile" :class="{ 'dashboard-profile-collapsed': !expanded }" :title="expanded ? undefined : auth.user.name">
        <span class="dashboard-profile-avatar"><img v-if="auth.user.avatar" :src="auth.user.avatar" alt="" class="h-full w-full object-cover" /><User v-else :size="20" aria-hidden="true" /></span>
        <span v-if="expanded" class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ auth.user.name }}</span><span class="mt-0.5 block truncate text-xs text-muted">{{ auth.user.email }}</span></span>
        <ChevronRight v-if="expanded" :size="16" class="shrink-0 text-muted" aria-hidden="true" />
      </RouterLink>
      <div v-if="$slots.context && expanded" class="dashboard-context"><slot name="context" /></div>
      <nav class="dashboard-nav" :aria-label="label">
        <div v-for="(group, index) in groups" :key="group.label ?? index" class="dashboard-nav-group">
          <p v-if="group.label && expanded" class="dashboard-nav-label">{{ group.label }}</p>
          <RouterLink v-for="item in group.items" :key="item.id" :to="item.to" class="dashboard-nav-link" :class="{ 'dashboard-nav-active': item.active }" :title="expanded ? undefined : item.label" :aria-current="item.active ? 'page' : undefined">
            <component :is="item.icon" :size="22" :stroke-width="1.8" class="shrink-0" aria-hidden="true" />
            <span v-if="expanded">{{ item.label }}</span>
          </RouterLink>
        </div>
      </nav>
      <div v-if="$slots.note && expanded" class="dashboard-note"><slot name="note" /></div>
      <div class="dashboard-footer">
        <slot name="footer" :expanded="expanded">
          <RouterLink to="/shop" class="dashboard-nav-link" :title="expanded ? undefined : $t('nav.back_to_store')"><Store :size="18" aria-hidden="true" /><span v-if="expanded">{{ $t('nav.back_to_store') }}</span></RouterLink>
          <button type="button" class="dashboard-nav-link dashboard-sign-out w-full" :title="expanded ? undefined : $t('nav.sign_out')" @click="emit('signOut')"><LogOut :size="18" aria-hidden="true" /><span v-if="expanded">{{ $t('nav.sign_out') }}</span></button>
        </slot>
      </div>
      <div v-if="collapsible" class="dashboard-collapse hidden lg:block">
        <button type="button" class="dashboard-nav-link w-full" :title="$t('admin.nav.toggle_sidebar')" :aria-label="$t('admin.nav.toggle_sidebar')" :aria-expanded="expanded" :aria-controls="navigationId" @click="toggleSidebar">
          <component :is="expanded ? PanelLeftClose : PanelLeftOpen" :size="21" aria-hidden="true" /><span v-if="expanded">{{ $t('admin.nav.collapse') }}</span>
        </button>
      </div>
    </aside>
    <div class="dashboard-main" :inert="!isDesktop && mobileOpen">
      <header class="dashboard-topbar">
        <ContentLayout class="dashboard-topbar-content">
          <div class="flex min-w-0 items-center gap-3">
            <button ref="menuButton" type="button" class="btn-icon shrink-0" :class="{ 'lg:hidden': !collapsible }" :aria-label="$t('admin.nav.toggle_sidebar')" :aria-controls="navigationId" :aria-expanded="isDesktop ? expanded : mobileOpen" @click="toggleSidebar"><Menu :size="22" /></button>
            <div class="dashboard-title min-w-0"><p class="truncate">{{ title }}</p></div>
          </div>
          <div class="dashboard-topbar-actions">
            <RouterLink to="/shop" class="dashboard-shopping btn-ghost hidden sm:inline-flex"><ShoppingBag :size="16" aria-hidden="true" />{{ $t('account.ui.shop') }}<ArrowUpRight :size="14" aria-hidden="true" /></RouterLink>
            <slot name="header-actions" />
            <span class="dashboard-language seller-language"><LanguageSwitcher /></span>
            <ThemeToggle />
          </div>
        </ContentLayout>
      </header>
      <ContentLayout as="main" :id="contentId" class="dashboard-content" tabindex="-1"><slot /></ContentLayout>
    </div>
  </div>
</template>

<style scoped>
.dashboard-workspace {
  --color-ink: 17 24 39;
  --color-muted: 107 114 128;
  --color-canvas: 249 250 251;
  --color-surface: 255 255 255;
  --color-border: 229 231 235;
  --color-primary: 37 99 235;
  --dashboard-sidebar-width: 288px;
  --dashboard-header-height: 80px;
  min-height: 100vh;
  color: rgb(var(--color-ink));
  background: rgb(var(--color-canvas));
}
:global(html.dark .dashboard-workspace) {
  --color-ink: 240 245 255;
  --color-muted: 157 173 199;
  --color-canvas: 11 18 32;
  --color-surface: 23 34 53;
  --color-border: 43 57 80;
  --color-primary: 96 153 255;
}
.dashboard-skip { position: fixed; left: 16px; top: 16px; z-index: 60; border-radius: 10px; background: rgb(var(--color-surface)); }
.dashboard-skip:focus { padding: 12px; }
.dashboard-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 50; display: flex; width: var(--dashboard-sidebar-width); flex-direction: column; border-right: 1px solid rgb(var(--color-border)); background: rgb(var(--color-surface)); transform: translateX(-100%); transition: transform .2s, width .2s; }
.dashboard-sidebar-open { transform: translateX(0); }
.dashboard-brand { display: flex; min-height: var(--dashboard-header-height); flex-shrink: 0; align-items: center; justify-content: space-between; border-bottom: 1px solid rgb(var(--color-border)); padding: 12px 20px; }
.dashboard-brand > a { gap: 12px; }
.dashboard-brand-logo { width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px; object-fit: cover; }
.dashboard-profile { display: flex; flex-shrink: 0; align-items: center; gap: 12px; margin: 16px; border: 1px solid rgb(var(--color-border)); border-radius: 12px; background: rgb(var(--color-canvas) / 60%); padding: 12px; transition: border-color .15s; }
.dashboard-profile:hover { border-color: rgb(var(--color-primary) / 30%); }
.dashboard-profile-avatar { display: flex; width: 44px; height: 44px; flex-shrink: 0; align-items: center; justify-content: center; overflow: hidden; border-radius: 50%; background: rgb(var(--color-primary) / 10%); color: rgb(var(--color-primary)); }
.dashboard-profile-collapsed { justify-content: center; margin: 12px 8px; padding: 6px 0; border-color: transparent; background: transparent; }
.dashboard-context { margin: 0 16px 16px; }
.dashboard-nav { min-height: 0; flex: 1; overflow-y: auto; padding: 4px 12px 20px; overscroll-behavior: contain; }
.dashboard-nav-group + .dashboard-nav-group { margin-top: 24px; }
.dashboard-nav-label { padding: 8px 12px 10px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .1em; color: rgb(var(--color-muted)); }
.dashboard-nav-link { display: flex; min-height: 44px; align-items: center; gap: 12px; border-radius: 12px; padding: 10px 12px; font-size: 14px; color: rgb(var(--color-muted)); transition: background .15s, color .15s; }
.dashboard-nav-link > svg { width: 18px; height: 18px; flex-shrink: 0; }
.dashboard-nav-link + .dashboard-nav-link { margin-top: 4px; }
.dashboard-nav-link:hover { background: rgb(var(--color-canvas)); color: rgb(var(--color-ink)); }
.dashboard-nav-link.dashboard-nav-active { font-weight: 600; color: rgb(var(--color-primary)); background: rgb(var(--color-primary) / 10%); }
.dashboard-note { flex-shrink: 0; margin: 0 16px 16px; }
.dashboard-footer { flex-shrink: 0; border-top: 1px solid rgb(var(--color-border)); padding: 12px; }
.dashboard-sign-out:hover { color: rgb(239 68 68); background: rgb(239 68 68 / 10%); }
.dashboard-collapse { flex-shrink: 0; border-top: 1px solid rgb(var(--color-border)); padding: 8px 12px; }
.dashboard-main { min-width: 0; }
.dashboard-topbar { position: sticky; top: 0; z-index: 30; border-bottom: 1px solid rgb(var(--color-border)); background: rgb(var(--color-surface)); }
.dashboard-topbar-content { display: flex; min-height: var(--dashboard-header-height); align-items: center; justify-content: space-between; gap: 12px; padding: 12px 32px; }
.dashboard-title p { font-size: 16px; font-weight: 500; color: rgb(var(--color-muted)); }
.dashboard-topbar-actions { display: flex; flex-shrink: 0; align-items: center; gap: 4px; }
.dashboard-shopping { margin-right: 8px; }
.dashboard-language { display: flex; }
.dashboard-content { padding: 32px; }
.dashboard-content:focus { outline: none; }
.dashboard-compact { --dashboard-header-height: 64px; }
.dashboard-workspace :deep(.card) { border-radius: 16px; box-shadow: 0 1px 2px rgb(17 24 39 / 7%); }
.dashboard-workspace :deep(:focus-visible) { outline: 2px solid rgb(var(--color-primary)); outline-offset: 3px; }
@media (min-width: 1024px) {
  .dashboard-main { padding-left: var(--dashboard-sidebar-width); }
  .dashboard-sidebar { transform: translateX(0); }
  .dashboard-collapsed { --dashboard-sidebar-width: 72px; }
  .dashboard-collapsed .dashboard-brand { justify-content: center; padding-inline: 10px; }
  .dashboard-collapsed .dashboard-nav-link { justify-content: center; padding-inline: 0; }
}
@media (max-width: 1279px) and (min-width: 1024px) {
  .dashboard-workspace:not(.dashboard-collapsed) { --dashboard-sidebar-width: 256px; }
}
@media (max-width: 1023px) { .dashboard-workspace { --dashboard-header-height: 64px; } .dashboard-content { padding: 24px; } .dashboard-topbar-content { padding-inline: 24px; } }
@media (max-width: 639px) {
  .dashboard-sidebar { --dashboard-sidebar-width: min(320px, 88vw); }
  .dashboard-topbar-content { padding: 10px 16px; gap: 8px; }
  .dashboard-title p { max-width: 42vw; font-size: 14px; }
  .dashboard-topbar-actions { gap: 0; }
  .dashboard-content { padding: 24px 16px; }
}
@media (prefers-reduced-motion: reduce) { .dashboard-sidebar { transition: none; } }
</style>
