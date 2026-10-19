<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { Save, Store, BellRing, Database } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api/admin'
import type { AdminSettings } from '@/api/admin'

const { t } = useI18n()

const defaults: AdminSettings = {
  storeName: 'E-KHMER',
  supportEmail: 'support@e-khmer.com',
  supportPhone: '',
  storeAddress: '',
  currency: 'USD',
  locale: 'en',
  timezone: 'Asia/Phnom_Penh',
  lowStockThreshold: 5,
  emailOrderNotifications: true,
  emailLowStockAlerts: true,
  maintenanceMode: false
}

const timezones = [
  'UTC',
  'Asia/Phnom_Penh',
  'Asia/Bangkok',
  'Asia/Ho_Chi_Minh',
  'Asia/Singapore',
  'Asia/Kuala_Lumpur',
  'Asia/Manila',
  'Asia/Jakarta',
  'Asia/Kolkata',
  'Asia/Shanghai',
  'Asia/Tokyo',
  'Asia/Seoul',
  'Australia/Sydney',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Sao_Paulo'
]

const form = reactive<AdminSettings>({ ...defaults })
const loaded = ref(false)
const saving = ref(false)
const saved = ref(false)

onMounted(async () => {
  try {
    const { data } = await adminApi.getSettings()
    Object.assign(form, data.data)
  } catch {
    // Fall back to defaults
  }
  loaded.value = true
})

const toast = ref('')
let toastTimer: ReturnType<typeof setTimeout> | undefined
function showToast(msg: string) {
  toast.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.value = ''
  }, 2500)
}

async function saveSettings() {
  if (saving.value) return
  saving.value = true
  saved.value = true
  try {
    const { data } = await adminApi.updateSettings({ ...form })
    Object.assign(form, data.data)
    showToast(t('admin.settings.toast_saved'))
  } catch {
    showToast(t('admin.settings.toast_error'))
  } finally {
    saving.value = false
    setTimeout(() => {
      saved.value = false
    }, 1500)
  }
}

async function resetSettings() {
  if (saving.value) return
  saving.value = true
  try {
    const { data } = await adminApi.updateSettings({ ...defaults })
    Object.assign(form, data.data)
    showToast(t('admin.settings.toast_reset'))
  } catch {
    showToast(t('admin.settings.toast_error'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="!loaded" class="card p-10 text-center text-sm text-gray-500">
    {{ $t('common.loading') }}
  </div>

  <div v-else class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-ink">{{ $t('admin.settings.title') }}</h1>
      <p class="mt-0.5 text-sm text-gray-500">{{ $t('admin.settings.subtitle') }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <div class="card p-6">
        <div class="flex items-center gap-2">
          <Store class="h-5 w-5 text-primary" />
          <h2 class="text-base font-semibold text-ink">{{ $t('admin.settings.store_profile') }}</h2>
        </div>
        <p class="mt-1 text-sm text-gray-500">{{ $t('admin.settings.store_profile_description') }}</p>

        <div class="mt-5 space-y-4">
          <div>
            <label class="label" for="store-name">{{ $t('admin.settings.store_name') }}</label>
            <input id="store-name" v-model="form.storeName" class="input" type="text" />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label" for="support-email">{{ $t('admin.settings.support_email') }}</label>
              <input id="support-email" v-model="form.supportEmail" class="input" type="email" />
            </div>
            <div>
              <label class="label" for="support-phone">{{ $t('admin.settings.support_phone') }}</label>
              <input id="support-phone" v-model="form.supportPhone" class="input" type="tel" />
            </div>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label" for="currency">{{ $t('admin.settings.currency') }}</label>
              <select id="currency" v-model="form.currency" class="input" disabled aria-describedby="currency-help">
                <option value="USD">USD — US Dollar</option>
              </select>
              <p id="currency-help" class="mt-1 text-xs text-muted">{{ t('admin.overview.usd_only') }}</p>
            </div>
            <div>
              <label class="label" for="locale">{{ $t('admin.settings.locale') }}</label>
              <select id="locale" v-model="form.locale" class="input">
                <option value="en">English</option>
                <option value="km">ខ្មែរ (Khmer)</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label" for="store-address">{{ $t('admin.settings.store_address') }}</label>
            <textarea
              id="store-address"
              v-model="form.storeAddress"
              class="input min-h-[90px] resize-y"
              rows="3"
            ></textarea>
          </div>
          <div>
            <label class="label" for="timezone">{{ $t('admin.settings.timezone') }}</label>
            <select id="timezone" v-model="form.timezone" class="input">
              <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="card p-6">
          <div class="flex items-center gap-2">
            <BellRing class="h-5 w-5 text-primary" />
            <h2 class="text-base font-semibold text-ink">{{ $t('admin.settings.notifications') }}</h2>
          </div>
          <p class="mt-1 text-sm text-gray-500">{{ $t('admin.settings.notifications_description') }}</p>

          <div class="mt-5 space-y-4">
            <div>
              <label class="label" for="low-stock-threshold">{{ $t('admin.settings.low_stock_threshold') }}</label>
              <input
                id="low-stock-threshold"
                v-model.number="form.lowStockThreshold"
                class="input"
                type="number"
                min="0"
                step="1"
              />
            </div>
            <label class="flex items-start justify-between gap-4 rounded-lg border border-border-gray p-4">
              <span>
                <span class="block text-sm font-medium text-ink">{{ $t('admin.settings.email_order_notifications') }}</span>
                <span class="text-xs text-gray-500">{{ $t('admin.settings.email_order_notifications_desc') }}</span>
              </span>
              <button
                type="button"
                class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                :class="form.emailOrderNotifications ? 'bg-success' : 'bg-gray-200 dark:bg-surface-hover'"
                :aria-checked="form.emailOrderNotifications"
                role="switch"
                @click="form.emailOrderNotifications = !form.emailOrderNotifications"
              >
                <span
                  class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all"
                  :class="form.emailOrderNotifications ? 'left-[22px]' : 'left-0.5'"
                ></span>
              </button>
            </label>
            <label class="flex items-start justify-between gap-4 rounded-lg border border-border-gray p-4">
              <span>
                <span class="block text-sm font-medium text-ink">{{ $t('admin.settings.email_low_stock_alerts') }}</span>
                <span class="text-xs text-gray-500">{{ $t('admin.settings.email_low_stock_alerts_desc') }}</span>
              </span>
              <button
                type="button"
                class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                :class="form.emailLowStockAlerts ? 'bg-success' : 'bg-gray-200 dark:bg-surface-hover'"
                :aria-checked="form.emailLowStockAlerts"
                role="switch"
                @click="form.emailLowStockAlerts = !form.emailLowStockAlerts"
              >
                <span
                  class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all"
                  :class="form.emailLowStockAlerts ? 'left-[22px]' : 'left-0.5'"
                ></span>
              </button>
            </label>
          </div>
        </div>

        <div class="card p-6">
          <div class="flex items-center gap-2">
            <Database class="h-5 w-5 text-primary" />
            <h2 class="text-base font-semibold text-ink">{{ $t('admin.settings.maintenance') }}</h2>
          </div>
          <p class="mt-1 text-sm text-gray-500">{{ $t('admin.settings.maintenance_description') }}</p>
          <label class="mt-5 flex items-start justify-between gap-4 rounded-lg border border-border-gray p-4">
            <span>
              <span class="block text-sm font-medium text-ink">{{ $t('admin.settings.maintenance_mode') }}</span>
              <span class="text-xs text-gray-500">{{ $t('admin.settings.maintenance_mode_desc') }}</span>
            </span>
            <button
              type="button"
              class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
              :class="form.maintenanceMode ? 'bg-red-500' : 'bg-gray-200 dark:bg-surface-hover'"
              :aria-checked="form.maintenanceMode"
              role="switch"
              @click="form.maintenanceMode = !form.maintenanceMode"
            >
              <span
                class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all"
                :class="form.maintenanceMode ? 'left-[22px]' : 'left-0.5'"
              ></span>
            </button>
          </label>
        </div>

        <div class="card p-6">
          <div class="flex items-center gap-2">
            <Database class="h-5 w-5 text-primary" />
            <h2 class="text-base font-semibold text-ink">{{ $t('admin.settings.stored_in_db') }}</h2>
          </div>
          <p class="mt-1 text-sm text-gray-500">{{ $t('admin.settings.stored_in_db_description') }}</p>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <button type="button" class="btn-primary" :disabled="saving" @click="saveSettings">
        <Save v-if="!saved" class="h-4 w-4" />
        {{ saved ? $t('admin.settings.saved') : $t('admin.settings.save_changes') }}
      </button>
      <button type="button" class="btn-secondary" @click="resetSettings">
        {{ $t('admin.settings.reset') }}
      </button>
    </div>

    <transition name="fade">
      <div v-if="toast" class="fixed bottom-6 right-6 card px-5 py-3 text-sm shadow-popover">
        {{ toast }}
      </div>
    </transition>
  </div>
</template>