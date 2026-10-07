import apiClient from './client'
import type { ApiOrder, ApiShopOrder, ShopOrderTransitionPayload, ShopShipmentPayload } from './checkout'
import type { CatalogProduct, PaginatedResponse } from './catalog'

export interface AdminDashboard {
  range?: string
  period: {
    from: string
    to: string
    revenue: number
    orders_count: number
    customers_count: number
    average_order_value: number
    revenue_delta: number | null
    orders_delta: number | null
    customers_delta: number | null
  }
  metrics: {
    total_revenue: number
    today_revenue: number
    week_revenue: number
    month_revenue: number
    revenue_delta: number | null
    orders_count: number
    orders_delta: number | null
    pending_orders: number
    processing_orders: number
    completed_orders: number
    cancelled_orders: number
    customers_count: number
    customers_delta: number | null
    total_products: number
    total_categories: number
    total_brands: number
    low_stock_products: number
    out_of_stock_products: number
  }
  revenue_trend: { date: string; revenue: number }[]
  orders_trend: { date: string; orders: number }[]
  status_distribution: { status: string; count: number }[]
  payment_status_distribution: { status: string; count: number }[]
  sales_by_category: { id: number; name: string; slug: string; revenue: number; order_count: number }[]
  top_selling_products: { product_id: number; product_name: string; total_qty: number; revenue: number }[]
  low_stock: {
    id: number
    product_id: number | null
    product_name: string | null
    product_slug: string | null
    variant_name: string | null
    sku: string | null
    quantity: number
    reserved_quantity: number
    available_quantity: number
    low_stock_threshold: number
    is_out_of_stock: boolean
  }[]
  recent_customers: { id: number; name: string; email: string; avatar: string | null; created_at: string }[]
  recent_reviews: { id: number; rating: number; title: string | null; body: string | null; status: string; user_name: string | null; product_name: string | null; created_at: string }[]
  recent_payments: { id: number; order_id: number; currency: string | null; order_number: string | null; method: string | null; status: string; amount: number; transaction_id: string | null; paid_at: string | null; created_at: string | null }[]
  recent_orders: (AdminOrderItem & { customer_name: string | null; email: string | null; currency: string; user: { id: number; name: string; email: string } | null })[]
}

export interface AdminProduct extends CatalogProduct {
  description: string | null
  sku: string
  weight: number | null
  meta_title: string | null
  meta_description: string | null
  gallery: { id: number; image_path: string; alt_text: string | null; sort_order: number; is_cover: boolean }[]
  attributes: {
    id: number
    name: string
    slug: string
    type: string
    values: { id: number; value: string; swatch_color: string | null }[]
  }[]
  variants: {
    id: number
    sku: string
    name: string
    price: number
    compare_at_price: number | null
    is_default: boolean
    is_active: boolean
    available_quantity: number
    in_stock: boolean
    attributes: { attribute_slug: string; name: string; value: string }[]
  }[]
}

export interface AdminShop {
  id: number
  name: string
  slug: string
  code: string
  description: string | null
  logo: string | null
  email: string | null
  phone: string | null
  branch_type?: string | null
  banner?: string | null
  address_line?: string | null
  mall?: string | null
  city: string | null
  province?: string | null
  postal_code?: string | null
  country?: string | null
  latitude?: number | null
  longitude?: number | null
  commission_rate?: number | null
  is_default?: boolean
  status: 'pending' | 'active' | 'suspended' | 'rejected' | 'closed'
  rejection_reason: string | null
  products_count?: number
  inventories_count?: number
  created_at: string
}

export interface AdminCategory {
  id: number
  name: string
  slug: string
  description: string | null
  image: string | null
  sort_order: number
  is_active: boolean
  parent_id: number | null
  products_count?: number
  children?: AdminCategory[]
}

export interface AdminBrand {
  id: number
  name: string
  slug: string
  description: string | null
  logo: string | null
  is_active: boolean
  products_count?: number
}

export interface AdminShippingMethod {
  id: number
  name: string
  code: string
  description: string | null
  price: number
  estimated_days_min: number | null
  estimated_days_max: number | null
  is_active: boolean
}

export interface AdminOrderItem {
  id: number
  order_number: string
  status: string
  payment_status: string
  total: number
  placed_at: string
  items_count: number
  user?: { id: number; name: string; email: string } | null
}

export interface AdminCoupon {
  id: number
  code: string
  type: 'percentage' | 'fixed'
  value: number
  min_order_amount: number | null
  max_discount_amount: number | null
  usage_limit: number | null
  per_user_limit: number | null
  starts_at: string | null
  expires_at: string | null
  is_active: boolean
}

export interface AdminReview {
  id: number
  rating: number
  title: string | null
  body: string | null
  verified: boolean
  status: string
  helpful_count: number
  created_at: string
  user: { id: number; name: string }
  product: { id: number; name: string; slug: string }
}

export interface AdminSettings {
  storeName: string
  supportEmail: string
  supportPhone: string
  storeAddress: string
  currency: string
  locale: string
  timezone: string
  lowStockThreshold: number
  emailOrderNotifications: boolean
  emailLowStockAlerts: boolean
  maintenanceMode: boolean
}

export interface AdminInventoryItem {
  id: number
  product_variant_id: number
  product: { id: number; name: string; slug: string }
  variant: { id: number; name: string; sku: string; is_active: boolean }
  variant_label: string
  product_name: string | null
  sku: string | null
  quantity: number
  reserved_quantity: number
  available_quantity: number
  sold_count: number
  low_stock_threshold: number
  is_low_stock: boolean
  is_out_of_stock: boolean
  low_stock_notified_at: string | null
  updated_at: string
}

export interface InventoryTransaction {
  id: number
  inventory_id: number
  type: 'reserve' | 'release' | 'deduct' | 'restock' | 'adjust' | 'in' | 'out'
  quantity: number
  balance_after: number
  reference: string | null
  note: string | null
  created_by: { id: number; name: string } | null
  created_at: string
}

export interface AdminCustomer {
  id: number
  name: string
  email: string
  phone: string | null
  role: string
  avatar: string | null
  created_at: string
}

export interface AdminCustomerDetail {
  user: AdminCustomer
  orders_count: number
  lifetime_spend: number
  recent_orders: AdminOrderItem[]
}

export interface AdminShopMember {
  id: number
  status: string
  role: 'owner' | 'manager'
  joined_at: string
  user: { id: number; name: string; email: string; phone: string | null; avatar: string | null }
  shop: { id: number; name: string; slug: string; logo: string | null; status: string }
}

export interface AdminPayment {
  id: number
  order_id: number
  order_number: string | null
  customer_name: string | null
  method: string | null
  status: string
  amount: number
  transaction_id: string | null
  paid_at: string | null
  created_at: string
  transactions?: {
    id: number
    type: string
    status: string
    amount: number
    reference: string | null
    created_at: string
  }[]
}

export interface AdminShipment {
  id: number
  order_id: number
  order_number: string | null
  customer_name: string | null
  shipping_method_id: number | null
  shipping_method: string | null
  tracking_number: string | null
  carrier: string | null
  status: string
  address_snapshot: Record<string, unknown> | null
  shipped_at: string | null
  delivered_at: string | null
  created_at: string
}

export interface AdminNotification {
  id: string
  type: string
  title: string | null
  message: string | null
  read_at: string | null
  created_at: string
}

export const adminApi = {
  transitionShopOrder(orderId: number, shopOrderId: number, payload: ShopOrderTransitionPayload) {
    return apiClient.put<{ data: ApiShopOrder }>(`/admin/orders/${orderId}/shop-orders/${shopOrderId}/transition`, payload)
  },
  updateShopShipment(orderId: number, shopOrderId: number, payload: ShopShipmentPayload) {
    return apiClient.put<{ data: ApiShopOrder }>(`/admin/orders/${orderId}/shop-orders/${shopOrderId}/shipment`, payload)
  },
  getDashboard(range?: string, from?: string, to?: string, signal?: AbortSignal) {
    const params: Record<string, string> = {}
    if (range) params.range = range
    if (from) params.from = from
    if (to) params.to = to
    return apiClient.get<{ data: AdminDashboard }>('/admin/dashboard/overview', { params, signal })
  },

  getOrdersCsv(status?: string, from?: string, to?: string) {
    const params: Record<string, string> = {}
    if (status) params.status = status
    if (from) params.from = from
    if (to) params.to = to
    return apiClient.get<Blob>('/admin/reports/orders.csv', { params, responseType: 'blob' })
  },

  getOrdersPdf(status?: string, from?: string, to?: string) {
    const params: Record<string, string> = {}
    if (status) params.status = status
    if (from) params.from = from
    if (to) params.to = to
    return apiClient.get<Blob>('/admin/reports/orders.pdf', { params, responseType: 'blob' })
  },

  getProductsCsv(from?: string, to?: string) {
    const params: Record<string, string> = {}
    if (from) params.from = from
    if (to) params.to = to
    return apiClient.get<Blob>('/admin/reports/products.csv', { params, responseType: 'blob' })
  },

  getPaymentsCsv(status?: string, from?: string, to?: string) {
    const params: Record<string, string> = {}
    if (status) params.status = status
    if (from) params.from = from
    if (to) params.to = to
    return apiClient.get<Blob>('/admin/reports/payments.csv', { params, responseType: 'blob' })
  },

  getReportsSummary(params: { from?: string; to?: string } = {}) {
    return apiClient.get<{
      data: {
        revenue: number
        items_revenue: number
        refunded: number
        orders_count: number
        customers_count: number
        units_sold: number
        avg_order_value: number
        payment_methods: { method: string; count: number; amount: number }[]
        low_stock_count: number
      }
    }>('/admin/reports/summary', { params })
  },

  listProducts(params: { q?: string; category_id?: number; brand_id?: number; stock_status?: string; deleted?: boolean } = {}) {
    return apiClient.get<PaginatedResponse<AdminProduct>>('/admin/products', { params })
  },

  getProduct(id: number) {
    return apiClient.get<{ data: AdminProduct }>(`/admin/products/${id}`)
  },

  createProduct(payload: Record<string, unknown>) {
    return apiClient.post<{ data: AdminProduct }>('/admin/products', payload)
  },

  updateProduct(id: number, payload: Record<string, unknown>) {
    return apiClient.put<{ data: AdminProduct }>(`/admin/products/${id}`, payload)
  },

  deleteProduct(id: number) {
    return apiClient.delete<{ data: { message: string } }>(`/admin/products/${id}`)
  },

  listCategories() {
    return apiClient.get<{ data: AdminCategory[] }>('/admin/categories')
  },

  createCategory(payload: Record<string, unknown>) {
    return apiClient.post<{ data: AdminCategory }>('/admin/categories', payload)
  },

  updateCategory(id: number, payload: Record<string, unknown>) {
    return apiClient.put<{ data: AdminCategory }>(`/admin/categories/${id}`, payload)
  },

  deleteCategory(id: number) {
    return apiClient.delete<{ data: { message: string } }>(`/admin/categories/${id}`)
  },

  listBrands() {
    return apiClient.get<{ data: AdminBrand[] }>('/admin/brands')
  },

  createBrand(payload: Record<string, unknown>) {
    return apiClient.post<{ data: AdminBrand }>('/admin/brands', payload)
  },

  updateBrand(id: number, payload: Record<string, unknown>) {
    return apiClient.put<{ data: AdminBrand }>(`/admin/brands/${id}`, payload)
  },

  deleteBrand(id: number) {
    return apiClient.delete<{ data: { message: string } }>(`/admin/brands/${id}`)
  },

  listShippingMethods() {
    return apiClient.get<{ data: AdminShippingMethod[] }>('/admin/shipping-methods')
  },

  createShippingMethod(payload: Record<string, unknown>) {
    return apiClient.post<{ data: AdminShippingMethod }>('/admin/shipping-methods', payload)
  },

  updateShippingMethod(id: number, payload: Record<string, unknown>) {
    return apiClient.put<{ data: AdminShippingMethod }>(`/admin/shipping-methods/${id}`, payload)
  },

  deleteShippingMethod(id: number) {
    return apiClient.delete<{ data: { message: string } }>(`/admin/shipping-methods/${id}`)
  },

  listOrders(params: { status?: string; q?: string } = {}) {
    return apiClient.get<PaginatedResponse<AdminOrderItem>>('/admin/orders', { params })
  },

  getOrder(id: number) {
    return apiClient.get<{ data: ApiOrder }>(`/admin/orders/${id}`)
  },

  transitionOrder(id: number, status: string) {
    return apiClient.put<{ data: ApiOrder }>(`/admin/orders/${id}/transition`, { status })
  },

  getOrderReceipt(id: number) {
    return apiClient.get<Blob>(`/admin/orders/${id}/receipt`, { responseType: 'blob' })
  },

  listCoupons() {
    return apiClient.get<PaginatedResponse<AdminCoupon>>('/admin/coupons')
  },

  createCoupon(payload: Record<string, unknown>) {
    return apiClient.post<{ data: AdminCoupon }>('/admin/coupons', payload)
  },

  updateCoupon(id: number, payload: Record<string, unknown>) {
    return apiClient.put<{ data: AdminCoupon }>(`/admin/coupons/${id}`, payload)
  },

  deleteCoupon(id: number) {
    return apiClient.delete<{ data: { message: string } }>(`/admin/coupons/${id}`)
  },

  listReviews(params: { status?: string } = {}) {
    return apiClient.get<PaginatedResponse<AdminReview>>('/admin/reviews', { params })
  },

  approveReview(id: number) {
    return apiClient.post<{ data: AdminReview }>(`/admin/reviews/${id}/approve`)
  },

  rejectReview(id: number) {
    return apiClient.post<{ data: AdminReview }>(`/admin/reviews/${id}/reject`)
  },

  deleteReview(id: number) {
    return apiClient.delete<{ data: { message: string } }>(`/admin/reviews/${id}`)
  },

  getSettings() {
    return apiClient.get<{ data: AdminSettings }>('/admin/settings')
  },

  updateSettings(payload: Partial<AdminSettings>) {
    return apiClient.put<{ data: AdminSettings }>('/admin/settings', payload)
  },

  listCustomers(params: { q?: string } = {}) {
    return apiClient.get<PaginatedResponse<AdminCustomer>>('/admin/customers', { params })
  },

  getCustomer(id: number) {
    return apiClient.get<AdminCustomerDetail>(`/admin/customers/${id}`)
  },

  listInventory(params: { q?: string; stock_status?: string; page?: number } = {}) {
    return apiClient.get<PaginatedResponse<AdminInventoryItem>>('/admin/inventory', { params })
  },

  listInventoryTransactions(inventoryId: number, params: { type?: string; page?: number } = {}) {
    return apiClient.get<PaginatedResponse<InventoryTransaction>>(`/admin/inventory/${inventoryId}/transactions`, { params })
  },

  listShopMembers(params: { role: 'owner' | 'manager'; q?: string; status?: string; shop_status?: string; page?: number }) {
    return apiClient.get<PaginatedResponse<AdminShopMember>>('/admin/shop-members', { params })
  },

  createShopMember(payload: { name: string; email: string; phone?: string; password: string; shop_id: number; role: 'owner' | 'manager'; status: string }) {
    return apiClient.post<{ data: AdminShopMember }>('/admin/shop-members', payload)
  },

  getShopMember(id: number) {
    return apiClient.get<{ data: AdminShopMember }>(`/admin/shop-members/${id}`)
  },

  updateShopMember(id: number, payload: { name: string; email: string; phone?: string; password?: string; shop_id: number; role: 'owner' | 'manager'; status: string }) {
    return apiClient.put<{ data: AdminShopMember }>(`/admin/shop-members/${id}`, payload)
  },

  removeShopMember(id: number) {
    return apiClient.delete<{ message: string }>(`/admin/shop-members/${id}`)
  },

  listShops(params: { q?: string; status?: string; page?: number } = {}) {
    return apiClient.get<PaginatedResponse<AdminShop>>('/admin/shops', { params })
  },

  getShop(id: number) {
    return apiClient.get<{ data: AdminShop }>(`/admin/shops/${id}`)
  },

  createShop(payload: Partial<AdminShop>) {
    return apiClient.post<{ data: AdminShop }>('/admin/shops', payload)
  },

  updateShop(id: number, payload: Partial<AdminShop>) {
    return apiClient.put<{ data: AdminShop }>(`/admin/shops/${id}`, payload)
  },

  deleteShop(id: number) {
    return apiClient.delete<{ message: string }>(`/admin/shops/${id}`)
  },

  updateShopStatus(id: number, status: AdminShop['status'], rejection_reason?: string) {
    return apiClient.patch<{ data: AdminShop }>(`/admin/shops/${id}/status`, { status, rejection_reason })
  },

  adjustInventory(inventoryId: number, quantity: number) {
    return apiClient.post<{ data: AdminInventoryItem }>(`/admin/inventory/${inventoryId}/adjust`, { quantity })
  },

  listPayments(params: { status?: string; method?: string; q?: string; page?: number } = {}) {
    return apiClient.get<PaginatedResponse<AdminPayment>>('/admin/payments', { params })
  },

  getPayment(id: number) {
    return apiClient.get<{ data: AdminPayment }>(`/admin/payments/${id}`)
  },

  listShipments(params: { status?: string; q?: string; page?: number } = {}) {
    return apiClient.get<PaginatedResponse<AdminShipment>>('/admin/shipments', { params })
  },

  getShipment(id: number) {
    return apiClient.get<{ data: AdminShipment }>(`/admin/shipments/${id}`)
  },

  updateShipment(id: number, payload: { tracking_number?: string; carrier?: string; status: string }) {
    return apiClient.put<{ data: AdminShipment }>(`/admin/shipments/${id}`, payload)
  },

  listNotifications(params: { filter?: string; page?: number } = {}) {
    return apiClient.get<
      PaginatedResponse<AdminNotification> & { meta: { unread_count: number } }
    >('/admin/notifications', {
      params,
    })
  },

  getNotificationUnreadCount() {
    return apiClient.get<{ data: { unread_count: number } }>('/admin/notifications/unread-count')
  },

  markNotificationRead(id: string | 'all') {
    return apiClient.post<{ data: { message: string } }>(`/admin/notifications/${id}/read`)
  },

  deleteNotification(id: string) {
    return apiClient.delete(`/admin/notifications/${id}`)
  },

  updateProductStatus(ids: number[], isActive: boolean) {
    return apiClient.patch<{ data: { updated: number } }>('/admin/products', { ids, is_active: isActive })
  },

  restoreProduct(id: number) {
    return apiClient.post<{ data: AdminProduct }>(`/admin/products/${id}/restore`)
  }
}
