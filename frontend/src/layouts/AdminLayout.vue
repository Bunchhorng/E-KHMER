<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Bell } from 'lucide-vue-next'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { accountApi } from '@/api'
import type { ApiNotification } from '@/api/account'
import { formatDateTime } from '@/utils/format'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import type { DashboardNavGroup, DashboardNavItem } from '@/components/dashboard/types'
import { adminNavGroups, adminNavIcons } from '@/config/adminNavigation'

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

function isActive(routeName: string): boolean {
  return route.matched.some(record => record.meta.adminNav?.parent === routeName || String(record.name) === routeName)
}

const groups = computed<DashboardNavGroup[]>(() => {
  if (!auth.isAdmin) return []

  const visibleRoutes = router.getRoutes().filter(record => {
    const navigation = record.meta.adminNav
    return typeof record.name === 'string'
      && Boolean(navigation?.labelKey && navigation.icon)
      && router.resolve({ name: record.name }).matched.some(match => match.meta.admin)
  })

  return adminNavGroups.map(group => {
    const items = visibleRoutes
      .filter(record => record.meta.adminNav?.group === group.id)
      .sort((left, right) => (left.meta.adminNav?.order ?? 0) - (right.meta.adminNav?.order ?? 0))
      .map(record => {
        const navigation = record.meta.adminNav
        if (!navigation?.labelKey || !navigation.icon || typeof record.name !== 'string') return null
        return {
          id: record.name,
          label: t(navigation.labelKey),
          to: { name: record.name },
          icon: adminNavIcons[navigation.icon],
          active: isActive(record.name)
        } satisfies DashboardNavItem
      })
      .filter((item): item is NonNullable<typeof item> => item !== null)

    return { id: group.id, label: t(group.titleKey), icon: group.icon, items }
  }).filter(group => group.items.length > 0)
})

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
    accordion
    navigation-storage-key="ekhmer_admin_sidebar_expanded_groups"
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
