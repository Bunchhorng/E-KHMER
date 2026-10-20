<script setup lang="ts">
import { Bell, Check, Package, ShieldCheck } from 'lucide-vue-next'
import { accountApi, type ApiNotification } from '@/api/account'
import { formatDateTime } from '@/utils/format'
import { computed, inject, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AccountState from '@/components/account/AccountState.vue'
import EmptyState from '@/components/EmptyState.vue'

const { t } = useI18n()
const updateBadge = inject<(count: number) => void>('account-notifications-read', () => {})
const notifications = ref<ApiNotification[]>([])
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const busy = ref(false)
const unreadOnly = ref(false)
const unreadCount = computed(() => notifications.value.filter(n => !n.read_at).length)
const visible = computed(() => unreadOnly.value ? notifications.value.filter(n => !n.read_at) : notifications.value)
function icon(type: string) { return type === 'order' ? Package : type === 'promo' ? Bell : ShieldCheck }
async function load() {
  loading.value = true; error.value = ''
  try { notifications.value = (await accountApi.getNotifications()).data.data; updateBadge(unreadCount.value) }
  catch { error.value = t('account.ui.load_error') }
  finally { loading.value = false }
}
async function markRead(notification?: ApiNotification) {
  if (busy.value || (notification ? notification.read_at : !unreadCount.value)) return
  busy.value = true; actionError.value = ''
  try {
    if (notification) await accountApi.markNotificationRead(notification.id)
    else await accountApi.markAllNotificationsRead()
    const now = new Date().toISOString()
    notifications.value.forEach(item => { if (!notification || notification.id === item.id) item.read_at = item.read_at || now })
    updateBadge(unreadCount.value)
  } catch { actionError.value = t('account.ui.action_error') }
  finally { busy.value = false }
}
onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="account-toolbar">
      <div class="inline-flex rounded-xl border border-border-gray bg-surface p-1">
        <button type="button" :aria-pressed="!unreadOnly" class="min-h-10 rounded-lg px-3 text-sm" :class="!unreadOnly ? 'bg-primary/10 font-semibold text-primary' : 'text-muted'" @click="unreadOnly = false">{{ $t('account.ui.all_notifications') }}</button>
        <button type="button" :aria-pressed="unreadOnly" class="min-h-10 rounded-lg px-3 text-sm" :class="unreadOnly ? 'bg-primary/10 font-semibold text-primary' : 'text-muted'" @click="unreadOnly = true">{{ $t('account.ui.unread') }}<span v-if="unreadCount" class="ml-1.5 rounded-full bg-primary/10 px-1.5 py-0.5 text-xs text-primary">{{ unreadCount }}</span></button>
      </div>
      <button v-if="unreadCount && !loading" type="button" class="btn-secondary min-h-10" :disabled="busy" @click="markRead()"><Check class="h-4 w-4" />{{ $t('account.mark_all_read') }}</button>
    </div>
    <p v-if="actionError" role="alert" class="text-sm text-red-500">{{ actionError }}</p>
    <AccountState :loading="loading" :error="error" @retry="load">
      <div v-if="visible.length" class="card divide-y divide-border-gray overflow-hidden">
        <button v-for="notification in visible" :key="notification.id" type="button" :disabled="busy || !!notification.read_at" class="flex w-full items-start gap-3 p-4 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary sm:gap-4 sm:p-5" :class="notification.read_at ? 'bg-surface' : 'bg-primary/5 hover:bg-primary/10'" @click="markRead(notification)">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"><component :is="icon(notification.type)" class="h-5 w-5" /></span>
          <span class="min-w-0 flex-1"><span class="block break-words text-sm" :class="notification.read_at ? 'font-medium text-muted' : 'font-semibold text-ink'">{{ notification.title }}</span><span class="mt-1 block break-words text-sm leading-relaxed text-muted">{{ notification.message }}</span><span class="mt-2 block text-xs text-muted">{{ formatDateTime(notification.created_at) }}</span></span>
          <span v-if="!notification.read_at" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" :aria-label="$t('account.ui.unread')"></span>
        </button>
      </div>
      <EmptyState v-else :title="$t('account.all_caught_up')" :description="$t('account.ui.notifications_empty_help')"><template #icon><Bell class="h-7 w-7" /></template></EmptyState>
    </AccountState>
  </div>
</template>
