import apiClient from './client'
import type { AdminInventoryItem, AdminProduct, InventoryTransaction } from './admin'
import type { PaginatedResponse } from './catalog'

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

export interface SellerShopOrder {
  id: number
  shop_order_number: string
  status: string
  total: number
  items_count: number
  customer_name: string | null
  payment_status: string | null
  placed_at: string | null
}

export const sellerApi = {
  getApplication() { return apiClient.get<{ data: SellerShop }>('/seller/application') },
  apply(payload: { name: string; description?: string; email?: string; phone?: string; address_line?: string; city?: string; province?: string; postal_code?: string; country?: string }) {
    return apiClient.post<{ data: SellerShop }>('/seller/application', payload)
  },
  dashboard() { return apiClient.get<{ data: SellerDashboard }>('/seller/dashboard') },
  listShops() { return apiClient.get<{ data: SellerShop[] }>('/seller/shops') },
  dashboardForShop(shopId: number) { return apiClient.get<{ data: SellerDashboard }>(`/seller/shops/${shopId}/dashboard`) },
  listProducts(shopId: number, params: { q?: string; page?: number } = {}) { return apiClient.get<PaginatedResponse<AdminProduct>>(`/seller/shops/${shopId}/products`, { params }) },
  getProduct(shopId: number, productId: number) { return apiClient.get<{ data: AdminProduct }>(`/seller/shops/${shopId}/products/${productId}`) },
  createProduct(shopId: number, payload: Record<string, unknown>) { return apiClient.post<{ data: AdminProduct }>(`/seller/shops/${shopId}/products`, payload) },
  updateProduct(shopId: number, productId: number, payload: Record<string, unknown>) { return apiClient.put<{ data: AdminProduct }>(`/seller/shops/${shopId}/products/${productId}`, payload) },
  deleteProduct(shopId: number, productId: number) { return apiClient.delete(`/seller/shops/${shopId}/products/${productId}`) },
  listInventory(shopId: number, params: { q?: string; stock_status?: string; page?: number } = {}) { return apiClient.get<PaginatedResponse<AdminInventoryItem>>(`/seller/shops/${shopId}/inventory`, { params }) },
  listInventoryTransactions(shopId: number, inventoryId: number, params: { type?: string; page?: number } = {}) {
    return apiClient.get<PaginatedResponse<InventoryTransaction>>(`/seller/shops/${shopId}/inventory/${inventoryId}/transactions`, { params })
  },
  adjustInventory(shopId: number, inventoryId: number, quantity: number) {
    return apiClient.post<{ data: AdminInventoryItem }>(`/seller/shops/${shopId}/inventory/${inventoryId}/adjust`, { quantity })
  },
  listOrders(shopId: number, params: { q?: string; status?: string; page?: number } = {}) { return apiClient.get<PaginatedResponse<SellerShopOrder>>(`/seller/shops/${shopId}/orders`, { params }) }
}
