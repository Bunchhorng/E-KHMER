import apiClient from './client'

export interface ApiShippingMethod {
  shop_id?: number | null
  id: number
  name: string
  code: string
  description: string | null
  price: number
  estimated_days_min: number | null
  estimated_days_max: number | null
  is_active: boolean
}

export const shippingApi = {
  getActive() {
    return apiClient.get<{ data: ApiShippingMethod[] }>('/shipping-methods')
  }
}
