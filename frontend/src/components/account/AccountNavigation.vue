<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Bell, ChevronRight, Heart, KeyRound, LayoutDashboard, LogOut, MapPin, MessageSquare, RotateCcw, ShoppingBag, Star, Store, User, X } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import logo from '@/assets/E-KHMER-LOGO.jpg'

withDefaults(defineProps<{ ordersCount: number; unreadCount: number; wishlistCount: number; mobile?: boolean }>(), { mobile: false })
const emit = defineEmits<{ navigate: []; close: []; signOut: [] }>()
const auth = useAuthStore()
const route = useRoute()
const groups = computed(() => [
  { label: 'account.ui.shopping', items: [
    { key: 'nav.dashboard', name: 'account-dashboard', icon: LayoutDashboard },
    { key: 'nav.my_orders', name: 'account-orders', icon: ShoppingBag, badge: 'orders' },
    { key: 'nav.wishlist', name: 'account-wishlist', icon: Heart, badge: 'wishlist' },
    { key: 'returns.title', name: 'account-returns', icon: RotateCcw },
    { key: 'nav.reviews', name: 'account-reviews', icon: Star },
    { key: 'support.inbox', name: 'account-support', icon: MessageSquare }
  ] },
  { label: 'account.ui.settings', items: [
    { key: 'nav.profile', name: 'account-profile', icon: User },
    { key: 'nav.addresses', name: 'account-addresses', icon: MapPin },
    { key: 'nav.notifications', name: 'account-notifications', icon: Bell, badge: 'unread' },
    { key: 'nav.change_password', name: 'account-password', icon: KeyRound }
  ] }
])
function active(name: string) {
  if (name === 'account-orders') return ['account-orders', 'account-order-detail'].includes(String(route.name))
  if (name === 'account-addresses') return String(route.name).startsWith('account-address')
  return route.name === name
}
</script>

<template>
  <div class="flex h-full min-h-0 flex-col bg-surface">
    <div class="flex h-20 shrink-0 items-center gap-3 border-b border-border-gray px-5">
      <RouterLink to="/" class="flex min-w-0 items-center gap-3" @click="emit('navigate')">
        <img :src="logo" alt="" class="h-10 w-10 rounded-xl object-cover" />
        <div><span class="text-lg font-extrabold tracking-tight">E-KHMER</span><p class="mt-0.5 text-xs text-muted">{{ $t('account.my_account') }}</p></div>
      </RouterLink>
      <button v-if="mobile" type="button" class="btn-icon ml-auto shrink-0" :aria-label="$t('actions.close')" @click="emit('close')"><X class="h-5 w-5" /></button>
    </div>
    <RouterLink to="/account/profile" class="m-4 flex items-center gap-3 rounded-xl border border-border-gray bg-canvas/60 p-3 hover:border-primary/30" @click="emit('navigate')">
      <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-primary">
        <img v-if="auth.user?.avatar" :src="auth.user.avatar" alt="" class="h-full w-full object-cover" /><User v-else class="h-5 w-5" />
      </div>
      <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ auth.user?.name }}</p><p class="mt-0.5 truncate text-xs text-muted">{{ auth.user?.email }}</p></div>
      <ChevronRight class="h-4 w-4 shrink-0 text-muted" />
    </RouterLink>
    <nav :aria-label="$t('account.my_account')" class="min-h-0 flex-1 space-y-5 overflow-y-auto px-3 pb-5">
      <section v-for="group in groups" :key="group.label">
        <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-widest text-muted">{{ $t(group.label) }}</p>
        <div class="space-y-1">
          <RouterLink v-for="item in group.items" :key="item.name" :to="{ name: item.name }" :aria-current="active(item.name) ? 'page' : undefined" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" :class="active(item.name) ? 'bg-primary/10 font-semibold text-primary' : 'text-muted hover:bg-canvas hover:text-ink'" @click="emit('navigate')">
            <component :is="item.icon" class="h-[18px] w-[18px] shrink-0" />
            <span class="flex-1">{{ $t(item.key) }}</span>
            <span v-if="item.badge && (item.badge === 'orders' ? ordersCount : item.badge === 'unread' ? unreadCount : wishlistCount)" class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">{{ item.badge === 'orders' ? ordersCount : item.badge === 'unread' ? unreadCount : wishlistCount }}</span>
          </RouterLink>
        </div>
      </section>
    </nav>
    <div class="shrink-0 space-y-1 border-t border-border-gray p-3">
      <RouterLink to="/shop" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm text-muted hover:bg-canvas hover:text-ink" @click="emit('navigate')"><Store class="h-[18px] w-[18px]" />{{ $t('nav.back_to_store') }}</RouterLink>
      <button type="button" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-muted hover:bg-red-500/10 hover:text-red-500" @click="emit('signOut')"><LogOut class="h-[18px] w-[18px]" />{{ $t('nav.sign_out') }}</button>
    </div>
  </div>
</template>
