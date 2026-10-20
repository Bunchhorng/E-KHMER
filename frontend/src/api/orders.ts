import apiClient from './client'

export interface ApiOrderListItem {
  order_number: string
  status: string
  payment_status: string
  total: number
  placed_at: string
  items_count: number
}

export const ordersApi = {
  list(status?: string, page = 1) {
    const params: Record<string, string | number> = { page }
    if (status) params.status = status
    return apiClient.get<{ data: ApiOrderListItem[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>('/orders', { params })
  },

  get(orderNumber: string) {
    return apiClient.get<{ data: import('./checkout').ApiOrder }>(`/orders/${orderNumber}`)
  },

  getGuest(orderNumber: string) {
    return apiClient.get<{ data: import('./checkout').ApiOrder }>(`/orders/guest/${orderNumber}`)
  },

  receipt(orderNumber: string) {
    return apiClient.get<Blob>(`/orders/${orderNumber}/receipt`, { responseType: 'blob' })
  },

  cancel(orderNumber: string) {
    return apiClient.post<{ data: import('./checkout').ApiOrder }>(`/orders/${orderNumber}/cancel`)
  }
}
