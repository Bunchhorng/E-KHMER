import apiClient from './client'

export interface CouponValidation {
  shop_id: number | null
  code: string
  type: 'percentage' | 'fixed'
  value: number
  min_order_amount: number | null
  max_discount_amount: number | null
  valid: boolean
  discount_amount: number
  eligible_subtotal: number
  message: string
}

export const couponsApi = {
  validate(code: string) {
    return apiClient.post<{ data: CouponValidation; subtotal: number }>('/coupons/validate', {
      code
    })
  }
}
