<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowUpRight, Menu, ShoppingBag } from 'lucide-vue-next'
import { useWishlistStore } from '@/stores/wishlist'
import { useAuthStore } from '@/stores/auth'
import { accountApi } from '@/api'
import AccountNavigation from '@/components/account/AccountNavigation.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import ContentLayout from '@/layouts/ContentLayout.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const wishlist = useWishlistStore()
const drawer = ref<HTMLDialogElement | null>(null)
const menuButton = ref<HTMLButtonElement | null>(null)
const drawerOpen = ref(false)
const ordersCount = ref(0)
const unreadCount = ref(0)
provide('account-notifications-read', (count: number) => { unreadCount.value = count })
const pageKeys: Record<string, [string, string]> = {
  'account-dashboard': ['nav.dashboard', 'dashboard_help'],
  'account-orders': ['nav.my_orders', 'orders_help'],
  'account-order-detail': ['account.ui.order_details', 'order_detail_help'],
  'account-wishlist': ['nav.wishlist', 'wishlist_help'],
  'account-addresses': ['account.my_addresses', 'addresses_help'],
  'account-address-create': ['account.add_address', 'address_form_help'],
  'account-address-edit': ['account.edit_address', 'address_form_help'],
  'account-profile': ['nav.profile', 'profile_help'],
  'account-notifications': ['nav.notifications', 'notifications_help'],
  'account-reviews': ['nav.reviews', 'reviews_help'],
  'account-password': ['account.change_password', 'password_help'],
  'account-returns': ['returns.title', 'returns_help'],
  'account-support': ['support.inbox', 'support_help']
}
const page = computed(() => pageKeys[String(route.name)] ?? pageKeys['account-dashboard']!)
const resending = ref(false)
const resendSent = ref(false)
const resendError = ref(false)
const showVerifyBanner = computed(() => auth.isAuthenticated && !auth.user?.email_verified)
let previousOverflow = ''
function closeDrawer() { drawer.value?.close() }
function openDrawer() {
  if (drawer.value?.open) return
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  drawer.value?.showModal()
  drawerOpen.value = true
}
function onDrawerClosed() {
  drawerOpen.value = false
  document.body.style.overflow = previousOverflow
  menuButton.value?.focus()
}
function backdropClick(event: MouseEvent) {
  if (event.target === drawer.value) closeDrawer()
}
function onDrawerKeydown(event: KeyboardEvent) {
  if (event.key !== 'Tab' || !drawer.value) return
  const controls = [...drawer.value.querySelectorAll<HTMLElement>('a[href], button:not([disabled])')]
    .filter(element => element.getClientRects().length > 0)
  const first = controls[0]
  const last = controls[controls.length - 1]
  if (!first || !last) return
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}
watch(() => route.fullPath, closeDrawer)
const desktopQuery = window.matchMedia('(min-width: 1024px)')
function onViewportChange() { if (desktopQuery.matches) closeDrawer() }
onMounted(async () => {
  desktopQuery.addEventListener('change', onViewportChange)
  const results = await Promise.allSettled([accountApi.getNotifications(), accountApi.getDashboard(), wishlist.fetchProducts()])
  const notifications = results[0]
  if (notifications.status === 'fulfilled') unreadCount.value = (notifications.value.data.data ?? []).filter(n => !n.read_at).length
  const dashboard = results[1]
  if (dashboard.status === 'fulfilled') ordersCount.value = dashboard.value.data.orders_count ?? 0
})
onBeforeUnmount(() => {
  desktopQuery.removeEventListener('change', onViewportChange)
  if (drawer.value?.open) document.body.style.overflow = previousOverflow
})
async function resendVerification() {
  resending.value = true; resendSent.value = false; resendError.value = false
  try { await auth.sendVerificationEmail(); resendSent.value = true }
  catch { resendError.value = true }
  finally { resending.value = false }
}
async function signOut() { closeDrawer(); await auth.logout(); router.push('/auth/login') }
</script>

<template>
  <div class="account-shell flex min-h-screen bg-canvas">
    <a href="#account-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-surface focus:p-3">{{ $t('account.ui.skip_content') }}</a>
    <aside class="sticky top-0 hidden h-dvh w-64 shrink-0 border-r border-border-gray lg:block xl:w-72">
      <AccountNavigation :orders-count="ordersCount" :unread-count="unreadCount" :wishlist-count="wishlist.count" @sign-out="signOut" />
    </aside>
    <dialog id="account-navigation" ref="drawer" :aria-label="$t('account.my_account')" class="account-drawer fixed inset-y-0 left-0 m-0 h-dvh max-h-none w-80 max-w-[88vw] border-0 bg-surface p-0 text-ink shadow-2xl backdrop:bg-black/50" @click="backdropClick" @close="onDrawerClosed" @keydown="onDrawerKeydown">
      <div class="h-full" @click.stop><AccountNavigation mobile :orders-count="ordersCount" :unread-count="unreadCount" :wishlist-count="wishlist.count" @navigate="closeDrawer" @close="closeDrawer" @sign-out="signOut" /></div>
    </dialog>
    <div class="min-w-0 flex-1">
      <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-border-gray bg-surface/95 px-4 backdrop-blur sm:px-6 lg:h-20 lg:px-8">
        <div class="flex min-w-0 items-center gap-2">
          <button ref="menuButton" type="button" class="btn-icon shrink-0 lg:hidden" :aria-label="$t('account.ui.open_menu')" :aria-expanded="drawerOpen" aria-controls="account-navigation" @click="openDrawer"><Menu class="h-5 w-5" /></button>
          <RouterLink to="/account" class="truncate text-sm font-medium text-muted hover:text-primary">{{ $t('account.my_account') }}</RouterLink>
        </div>
        <div class="flex shrink-0 items-center gap-1">
          <RouterLink to="/shop" class="btn-ghost mr-2 hidden sm:inline-flex"><ShoppingBag class="h-4 w-4" />{{ $t('account.ui.shop') }}<ArrowUpRight class="h-3.5 w-3.5" /></RouterLink>
          <LanguageSwitcher /><ThemeToggle />
        </div>
      </header>
      <ContentLayout as="main" id="account-content" tabindex="-1" class="account-content py-6 sm:py-8">
        <div class="mb-6 sm:mb-8"><h1 class="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{{ $t(page[0]) }}</h1><p class="mt-2 max-w-2xl text-sm leading-relaxed text-muted">{{ $t('account.ui.' + page[1]) }}</p></div>
        <div v-if="showVerifyBanner" class="mb-6 flex flex-col gap-3 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm sm:flex-row sm:items-center">
          <div class="flex-1"><p class="font-semibold">{{ $t('verify.banner_message') }}</p><p class="mt-1 text-muted">{{ $t('verify.banner_hint') }}</p><p v-if="resendSent" role="status" class="mt-2 text-success">{{ $t('verify.email_sent') }}</p><p v-if="resendError" role="alert" class="mt-2 text-red-500">{{ $t('verify.resend_failed') }}</p></div>
          <button type="button" class="btn-secondary shrink-0" :disabled="resending" @click="resendVerification">{{ $t(resending ? 'verify.sending' : 'verify.banner_resend') }}</button>
        </div>
        <router-view />
      </ContentLayout>
    </div>
  </div>
</template>
