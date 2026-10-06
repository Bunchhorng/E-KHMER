<script setup lang="ts">
import type { Component } from 'vue'
import { computed, ref, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard,
  Boxes,
  PackagePlus,
  FolderTree,
  Tags,
  ShoppingCart,
  Warehouse,
  Truck,
  Ship,
  CreditCard,
  FileBarChart,
  TicketPercent,
  Users,
  MessageSquare,
  Search,
  Bell,
  ChevronDown,
  Menu,
  PanelLeftClose,
  PanelLeftOpen,
  LogOut,
  Settings,
  Store
} from 'lucide-vue-next'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import ThemeToggle from '@/components/ThemeToggle.vue'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
import { accountApi } from '@/api'
import type { ApiNotification } from '@/api/account'
import { formatDateTime } from '@/utils/format'

interface NavItem {
  labelKey: string
  route: { name: string }
  icon: Component
}

interface NavGroup {
  titleKey: string
  items: NavItem[]
}

const route = useRoute()
const router = useRouter()
const uiStore = useUiStore()
const auth = useAuthStore()

const expanded = computed(() => !uiStore.adminSidebarCollapsed)
const profileOpen = ref(false)
const notifications = ref<ApiNotification[]>([])
const unreadCount = computed(() => notifications.value.filter((n) => !n.read_at).length)

onMounted(async () => {
  try {
    const { data } = await accountApi.getNotifications()
    notifications.value = data.data ?? []
  } catch {
    notifications.value = []
  }
})

const navGroups: NavGroup[] = [
  {
    titleKey: 'admin.nav.group_overview',
    items: [{ labelKey: 'nav.dashboard', route: { name: 'admin-dashboard' }, icon: LayoutDashboard }, { labelKey: 'Shops', route: { name: 'admin-shops' }, icon: Store }]
  },
  {
    titleKey: 'admin.nav.group_catalog',
    items: [
      { labelKey: 'admin.nav.products', route: { name: 'admin-products' }, icon: Boxes },
      { labelKey: 'admin.nav.add_product', route: { name: 'admin-product-create' }, icon: PackagePlus },
      { labelKey: 'admin.nav.categories', route: { name: 'admin-categories' }, icon: FolderTree },
      { labelKey: 'admin.nav.brands', route: { name: 'admin-brands' }, icon: Tags }
    ]
  },
  {
    titleKey: 'admin.nav.group_fulfillment',
    items: [
      { labelKey: 'admin.nav.orders', route: { name: 'admin-orders' }, icon: ShoppingCart },
      { labelKey: 'admin.nav.inventory', route: { name: 'admin-inventory' }, icon: Warehouse },
      { labelKey: 'admin.nav.payments', route: { name: 'admin-payments' }, icon: CreditCard },
      { labelKey: 'admin.nav.shipments', route: { name: 'admin-shipments' }, icon: Ship },
      { labelKey: 'admin.nav.reports', route: { name: 'admin-reports' }, icon: FileBarChart },
      { labelKey: 'admin.nav.shipping_methods', route: { name: 'admin-shipping' }, icon: Truck }
    ]
  },
  {
    titleKey: 'admin.nav.group_marketing',
    items: [{ labelKey: 'admin.nav.coupons', route: { name: 'admin-coupons' }, icon: TicketPercent }]
  },
  {
    titleKey: 'admin.nav.group_community',
    items: [
      { labelKey: 'admin.nav.customers', route: { name: 'admin-customers' }, icon: Users },
      { labelKey: 'nav.reviews', route: { name: 'admin-reviews' }, icon: MessageSquare }
    ]
  },
  {
    titleKey: 'admin.nav.group_system',
    items: [
      { labelKey: 'admin.nav.notifications', route: { name: 'admin-notifications' }, icon: Bell },
      { labelKey: 'admin.nav.settings', route: { name: 'admin-settings' }, icon: Settings }
    ]
  }
]

function isActive(item: NavItem): boolean {
  return route.name === item.route.name
}

async function signOut() {
  await auth.logout()
  router.push('/')
}
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-canvas">
    <aside
      class="flex shrink-0 flex-col border-r border-border-gray bg-surface transition-all duration-200"
      :class="expanded ? 'w-64' : 'w-[72px]'"
    >
      <div class="flex h-16 items-center border-b border-border-gray p-4" :class="expanded ? '' : 'justify-center'">
        <RouterLink v-if="expanded" to="/admin" class="flex items-center gap-2.5">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-sm font-extrabold text-white">EK</div>
          <div class="flex flex-col items-start leading-none">
            <span class="text-base font-extrabold tracking-tight text-ink">E-KHMER</span>
            <span class="chip mt-1 !px-2 !py-0 text-[10px] uppercase tracking-wide">{{ $t('admin.nav.admin_label') }}</span>
          </div>
        </RouterLink>
        <RouterLink v-else to="/admin" :title="$t('admin.nav.admin_label')">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-sm font-extrabold text-white">EK</div>
        </RouterLink>
      </div>

      <nav class="scrollbar-none flex-1 space-y-1 overflow-y-auto p-3">
        <template v-for="group in navGroups" :key="group.titleKey">
          <div v-if="expanded" class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
            {{ $t(group.titleKey) }}
          </div>
          <RouterLink
            v-for="item in group.items"
            :key="item.labelKey"
            :to="item.route"
            :title="expanded ? undefined : $t(item.labelKey)"
            class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
            :class="[isActive(item) ? 'bg-primary/10 text-primary' : 'text-gray-600 hover:bg-gray-100 dark:text-muted dark:hover:bg-surface-hover', expanded ? '' : 'justify-center']"
          >
            <component :is="item.icon" :size="18" />
            <span v-if="expanded">{{ $t(item.labelKey) }}</span>
          </RouterLink>
        </template>
      </nav>

      <div class="border-t border-border-gray p-3">
        <button
          class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-primary dark:text-muted dark:hover:bg-surface-hover dark:hover:text-primary"
          :class="expanded ? '' : 'justify-center'"
          @click="uiStore.toggleAdminSidebar()"
        >
          <component :is="expanded ? PanelLeftClose : PanelLeftOpen" :size="18" />
          <span v-if="expanded">{{ $t('admin.nav.collapse') }}</span>
        </button>
      </div>
    </aside>

    <div class="flex flex-1 flex-col overflow-hidden">
      <header class="flex h-16 shrink-0 items-center gap-3 border-b border-border-gray bg-surface px-4 sm:px-6">
        <button class="btn-icon" :title="$t('admin.nav.toggle_sidebar')" @click="uiStore.toggleAdminSidebar()">
          <Menu class="h-5 w-5" />
        </button>
        <div class="flex-1"></div>

        <div class="relative hidden md:block">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input type="text" class="input h-9 w-64 pl-9" :placeholder="$t('admin.nav.search_placeholder')" />
        </div>

        <div class="flex items-center gap-1">
          <LanguageSwitcher />
          <ThemeToggle />
          <div class="relative">
            <button class="btn-icon relative" :title="$t('nav.notifications')" @click="uiStore.toggleAdminNotifications()">
              <Bell class="h-5 w-5" />
              <span v-if="unreadCount > 0" class="absolute right-2.5 top-2.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-border-gray"></span>
            </button>

            <template v-if="uiStore.adminNotificationsOpen">
              <div class="fixed inset-0 z-40" @click="uiStore.adminNotificationsOpen = false"></div>
              <div class="absolute right-0 top-12 z-50 w-80 overflow-hidden rounded-xl border border-border-gray bg-surface shadow-popover">
              <div class="flex items-center justify-between border-b border-border-gray px-4 py-3">
                <span class="text-sm font-semibold text-ink">{{ $t('nav.notifications') }}</span>
                <span class="chip !py-0.5 text-[11px]">{{ $t('admin.dashboard.new_count', { count: unreadCount }) }}</span>
              </div>
              <div class="max-h-80 divide-y divide-border-gray overflow-y-auto">
                <div v-for="n in notifications" :key="n.id" class="flex gap-3 px-4 py-3">
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                      <span class="truncate text-sm font-medium text-ink">{{ n.title }}</span>
                      <span v-if="!n.read_at" class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary"></span>
                    </div>
                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-muted">{{ n.message }}</p>
                    <div class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ formatDateTime(n.created_at) }}</div>
                  </div>
                </div>
              </div>
              <RouterLink
                to="/account/notifications"
                class="block border-t border-border-gray px-4 py-2.5 text-center text-sm font-medium text-primary transition-colors hover:bg-canvas"
                @click="uiStore.adminNotificationsOpen = false"
              >
                {{ $t('actions.view_all') }}
              </RouterLink>
            </div>
          </template>
        </div>

        <div class="relative">
          <button class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-gray-100 dark:hover:bg-surface-hover" @click="profileOpen = !profileOpen">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{{ auth.user?.name?.charAt(0) ?? 'A' }}</div>
            <span class="hidden text-sm font-medium text-ink sm:block">{{ auth.user?.name ?? $t('admin.nav.admin_label') }}</span>
            <ChevronDown class="h-4 w-4 text-gray-400" />
          </button>

          <template v-if="profileOpen">
            <div class="fixed inset-0 z-40" @click="profileOpen = false"></div>
            <div class="absolute right-0 top-12 z-50 w-48 overflow-hidden rounded-xl border border-border-gray bg-surface py-1 shadow-popover">
              <RouterLink to="/account/profile" class="flex items-center px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-ink dark:text-muted dark:hover:bg-surface-hover dark:hover:text-ink" @click="profileOpen = false">
                {{ $t('nav.profile') }}
              </RouterLink>
              <RouterLink to="/admin/settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-ink dark:text-muted dark:hover:bg-surface-hover dark:hover:text-ink" @click="profileOpen = false">
                <Settings class="h-4 w-4" />
                {{ $t('admin.nav.settings') }}
              </RouterLink>
              <div class="my-1 border-t border-border-gray"></div>
              <button class="flex w-full items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10" @click="signOut">
                <LogOut class="h-4 w-4" />
                {{ $t('nav.sign_out') }}
              </button>
            </div>
          </template>
        </div>
        </div>
      </header>

      <main class="scrollbar-none flex-1 overflow-y-auto p-4 sm:p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
