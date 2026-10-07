import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { sellerApi, type SellerDashboard, type SellerShop } from '@/api/seller'
import { useAuthStore } from './auth'

export const useSellerWorkspace = defineStore('seller-workspace', () => {
  const auth = useAuthStore()
  const shops = ref<SellerShop[]>([])
  const selectedId = ref<number | null>(null)
  const dashboard = ref<SellerDashboard | null>(null)
  const loading = ref(false)
  const overviewLoading = ref(false)
  const currentShop = computed(() => shops.value.find(shop => shop.id === selectedId.value) ?? null)
  let ownerId: number | null = null
  let overviewRequest = 0
  let shopRequest = 0

  function reset() {
    shopRequest++
    overviewRequest++
    ownerId = null
    shops.value = []
    selectedId.value = null
    dashboard.value = null
    loading.value = false
    overviewLoading.value = false
  }

  async function loadShops() {
    const userId = auth.user?.id ?? null
    if (ownerId !== userId) reset()
    ownerId = userId
    if (!userId) return
    const request = ++shopRequest
    loading.value = true
    try {
      const { data } = await sellerApi.listShops()
      if (request !== shopRequest || auth.user?.id !== userId) return
      shops.value = data.data
      let remembered = 0
      try { remembered = Number(localStorage.getItem(`ekhmer_shop_${userId}`)) } catch { /* Storage can be unavailable in private sessions. */ }
      const preferred = selectedId.value ?? remembered
      selectShop(shops.value.some(shop => shop.id === preferred) ? preferred : (shops.value[0]?.id ?? null))
    } finally {
      if (request === shopRequest) loading.value = false
    }
  }

  function selectShop(id: number | null) {
    if (id !== null && !shops.value.some(shop => shop.id === id)) return
    if (selectedId.value !== id) {
      overviewRequest++
      overviewLoading.value = false
      dashboard.value = null
    }
    selectedId.value = id
    if (id && ownerId) {
      try { localStorage.setItem(`ekhmer_shop_${ownerId}`, String(id)) } catch { /* Remembering a shop is optional. */ }
    }
  }

  async function loadOverview() {
    const id = selectedId.value
    if (!id) return
    const request = ++overviewRequest
    overviewLoading.value = true
    try {
      const { data } = await sellerApi.dashboardForShop(id)
      if (request === overviewRequest && selectedId.value === id) dashboard.value = data.data
    } finally {
      if (request === overviewRequest) overviewLoading.value = false
    }
  }

  return { shops, selectedId, currentShop, dashboard, loading, overviewLoading, reset, loadShops, selectShop, loadOverview }
})
