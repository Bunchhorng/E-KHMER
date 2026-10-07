import axios from 'axios'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi, type AdminDashboard, type AdminShop } from '@/api/admin'
import { extractErrorMessage } from '@/api/errors'

export function useAdminDashboard() {
  const { t } = useI18n()
  const dashboard = ref<AdminDashboard | null>(null)
  const loading = ref(false)
  const error = ref('')
  const range = ref('30')
  const customFrom = ref('')
  const customTo = ref('')
  const rangeError = ref('')
  const lastUpdated = ref<Date | null>(null)
  const autoRefresh = ref(true)
  const pendingShops = ref<AdminShop[]>([])
  const shopCounts = ref<{ total: number; active: number; pending: number } | null>(null)
  const shopsLoading = ref(false)
  const shopsError = ref('')
  const shopActionId = ref<number | null>(null)
  const actionError = ref('')
  const actionSuccess = ref('')
  let appliedRange = { range: '30', from: '', to: '' }
  let requestId = 0
  let controller: AbortController | undefined
  let pollTimer: ReturnType<typeof setInterval> | undefined

  async function loadDashboard() {
    const currentId = ++requestId
    controller?.abort()
    controller = new AbortController()
    loading.value = true
    error.value = ''
    try {
      const response = await adminApi.getDashboard(appliedRange.range, appliedRange.from || undefined, appliedRange.to || undefined, controller.signal)
      if (currentId !== requestId) return
      dashboard.value = response.data.data
      lastUpdated.value = new Date()
    } catch (failure) {
      if (currentId === requestId && !axios.isCancel(failure)) {
        error.value = extractErrorMessage(failure, t('admin.dashboard.load_failed'))
      }
    } finally {
      if (currentId === requestId) loading.value = false
    }
  }

  async function applyRange() {
    rangeError.value = ''
    if (range.value === 'custom') {
      if (!customFrom.value || !customTo.value || customFrom.value > customTo.value) {
        rangeError.value = t('admin.dashboard.invalid_dates')
        return
      }
      const days = (Date.parse(customTo.value) - Date.parse(customFrom.value)) / 86_400_000
      if (!Number.isFinite(days) || days > 365) {
        rangeError.value = t('admin.dashboard.range_too_long')
        return
      }
    }
    appliedRange = { range: range.value, from: range.value === 'custom' ? customFrom.value : '', to: range.value === 'custom' ? customTo.value : '' }
    await loadDashboard()
  }

  function onRangeChange() {
    rangeError.value = ''
    if (range.value !== 'custom') void applyRange()
  }

  async function loadShops() {
    if (shopsLoading.value) return
    shopsLoading.value = true
    shopsError.value = ''
    try {
      const [all, active, pending] = await Promise.all([
        adminApi.listShops(),
        adminApi.listShops({ status: 'active' }),
        adminApi.listShops({ status: 'pending' })
      ])
      shopCounts.value = { total: all.data.meta.total, active: active.data.meta.total, pending: pending.data.meta.total }
      pendingShops.value = pending.data.data.slice(0, 5)
    } catch (failure) {
      shopsError.value = extractErrorMessage(failure, t('admin.dashboard.shops_load_failed'))
    } finally {
      shopsLoading.value = false
    }
  }

  async function refresh() {
    await Promise.all([loadDashboard(), loadShops()])
  }

  async function updateShopStatus(shop: AdminShop, status: 'active' | 'rejected', reason?: string): Promise<boolean> {
    if (shopActionId.value !== null) return false
    shopActionId.value = shop.id
    actionError.value = ''
    actionSuccess.value = ''
    try {
      await adminApi.updateShopStatus(shop.id, status, reason)
      actionSuccess.value = t(status === 'active' ? 'admin.dashboard.shop_approved' : 'admin.dashboard.shop_rejected', { name: shop.name })
      await loadShops()
      return true
    } catch (failure) {
      actionError.value = extractErrorMessage(failure, t('admin.dashboard.shop_action_failed'))
      return false
    } finally {
      shopActionId.value = null
    }
  }

  onMounted(() => {
    void refresh()
    pollTimer = setInterval(() => {
      if (autoRefresh.value && document.visibilityState === 'visible' && !loading.value && !shopsLoading.value && shopActionId.value === null) {
        void refresh()
      }
    }, 60_000)
  })

  onBeforeUnmount(() => {
    requestId++
    controller?.abort()
    if (pollTimer) clearInterval(pollTimer)
  })

  return {
    dashboard, loading, error, range, customFrom, customTo, rangeError, lastUpdated, autoRefresh,
    pendingShops, shopCounts, shopsLoading, shopsError, shopActionId, actionError, actionSuccess,
    applyRange, onRangeChange, refresh, loadShops, updateShopStatus
  }
}
