import apiClient from './client'

export interface SellerShop {
  id: number
  name: string
  slug: string
  status: 'pending' | 'active' | 'suspended' | 'rejected'
  description: string | null
  rejection_reason: string | null
}

export interface SellerDashboard {
  shop: SellerShop
  metrics: { products: number; active_products: number; inactive_products: number; low_stock: number }
}

export const sellerApi = {
  getApplication() { return apiClient.get<{ data: SellerShop }>('/seller/application') },
  apply(payload: { name: string; description?: string; email?: string; phone?: string; address_line?: string; city?: string; province?: string; postal_code?: string; country?: string }) {
    return apiClient.post<{ data: SellerShop }>('/seller/application', payload)
  },
  dashboard() { return apiClient.get<{ data: SellerDashboard }>('/seller/dashboard') }
}
