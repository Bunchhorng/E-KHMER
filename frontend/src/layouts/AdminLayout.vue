<script setup lang="ts">
import type { Component } from 'vue'
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  LayoutDashboard,
  Boxes,
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
  Bell,
  Settings,
  Store
} from 'lucide-vue-next'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { accountApi } from '@/api'
import type { ApiNotification } from '@/api/account'
import { formatDateTime } from '@/utils/format'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import type { DashboardNavGroup } from '@/components/dashboard/types'

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
const { t } = useI18n()

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
    items: [
      { labelKey: 'nav.dashboard', route: { name: 'admin-dashboard' }, icon: LayoutDashboard },
      { labelKey: 'admin.nav.shops', route: { name: 'admin-shops' }, icon: Store },
      { labelKey: 'admin.overview.shop_members', route: { name: 'admin-shop-members' }, icon: Users }
    ]
  },
  {
    titleKey: 'admin.nav.group_catalog',
    items: [
      { labelKey: 'admin.nav.products', route: { name: 'admin-products' }, icon: Boxes },
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
      { labelKey: 'returns.title', route: { name: 'admin-returns' }, icon: FileBarChart },
      { labelKey: 'support.inbox', route: { name: 'admin-support' }, icon: FileBarChart },
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
  return item.route.name === 'admin-shop-members'
    ? ['admin-shop-members', 'admin-shop-owners', 'admin-shop-admins', 'admin-shop-owner-create', 'admin-shop-owner-edit', 'admin-shop-admin-create', 'admin-shop-admin-edit'].includes(String(route.name))
    : route.name === item.route.name
}


const groups = computed<DashboardNavGroup[]>(() => navGroups.map(group => ({
  label: t(group.titleKey),
  items: group.items.map(item => ({
    id: item.route.name, label: t(item.labelKey), to: item.route, icon: item.icon, active: isActive(item)
  }))
})))

async function signOut() {
  await auth.logout()
  router.push('/')
}
</script>

<template>
  <DashboardLayout
    class="admin-workspace"
    navigation-id="admin-sidebar"
    content-id="admin-content"
    :label="t('admin.nav.admin_label')"
    :title="t('admin.nav.admin_label')"
    home-to="/admin"
    :groups="groups"
    v-model:collapsed="uiStore.adminSidebarCollapsed"
    collapsible
    @sign-out="signOut"
  >
    <template #header-actions>
      <div class="relative" @keydown.esc.stop="uiStore.adminNotificationsOpen = false">
        <button type="button" class="btn-icon relative" :aria-label="t('nav.notifications')" :aria-expanded="uiStore.adminNotificationsOpen" aria-controls="admin-notifications-panel" @click="uiStore.toggleAdminNotifications()">
          <Bell class="h-5 w-5" aria-hidden="true" />
          <span v-if="unreadCount > 0" class="absolute right-2.5 top-2.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-surface"></span>
        </button>
        <template v-if="uiStore.adminNotificationsOpen">
          <div class="fixed inset-0 z-40" @click="uiStore.adminNotificationsOpen = false"></div>
          <div id="admin-notifications-panel" class="fixed right-4 top-16 z-50 w-80 max-w-[calc(100vw-32px)] overflow-hidden rounded-xl border border-border-gray bg-surface shadow-popover sm:absolute sm:right-0 sm:top-12">
            <div class="flex items-center justify-between border-b border-border-gray px-4 py-3">
              <span class="text-sm font-semibold text-ink">{{ t('nav.notifications') }}</span>
              <span class="chip !py-0.5 text-[11px]">{{ t('admin.dashboard.new_count', { count: unreadCount }) }}</span>
            </div>
            <div class="max-h-80 divide-y divide-border-gray overflow-y-auto">
              <div v-for="notification in notifications" :key="notification.id" class="px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                  <span class="truncate text-sm font-medium text-ink">{{ notification.title }}</span>
                  <span v-if="!notification.read_at" class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary"></span>
                </div>
                <p class="mt-0.5 truncate text-xs text-muted">{{ notification.message }}</p>
                <p class="mt-1 text-[11px] text-muted">{{ formatDateTime(notification.created_at) }}</p>
              </div>
            </div>
            <RouterLink to="/admin/notifications" class="block border-t border-border-gray px-4 py-2.5 text-center text-sm font-medium text-primary hover:bg-canvas" @click="uiStore.adminNotificationsOpen = false">{{ t('actions.view_all') }}</RouterLink>
          </div>
        </template>
      </div>
    </template>
    <RouterView />
  </DashboardLayout>
</template>
