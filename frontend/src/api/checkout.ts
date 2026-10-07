import apiClient from './client'

export interface CheckoutAddress {
  full_name: string
  phone?: string
  address_line1: string
  address_line2?: string
  city: string
  state: string
  postal_code: string
  country?: string
}

export interface CheckoutPayload {
  shipping_method_id: number
  payment_method: string
  coupon_code?: string
  email?: string
  note?: string
  address_id?: number
  address?: CheckoutAddress
}

export interface ApiShopOrder {
  id: number
  order_id: number
  order_number: string
  shop_order_number: string
  status: string
  parent_status: string
  payment_status: string
  currency: string
  customer_name: string | null
  email: string | null
  phone: string | null
  shipping_address: Record<string, string> | null
  placed_at: string | null
  subtotal: number
  discount_amount: number
  tax_amount: number
  shipping_amount: number
  total: number
  items_count: number
  items: ApiOrder['items']
  shop: { id: number; name: string; slug: string; logo: string | null } | null
  shipment: { id: number; status: string; carrier: string | null; tracking_number: string | null; shipped_at: string | null; delivered_at: string | null } | null
  tracking_events: { id: number; from_status: string | null; status: string; description: string | null; at: string }[]
  allowed_transitions: string[]
}

export interface ShopOrderTransitionPayload {
  status: string
  note?: string
  carrier?: string
  tracking_number?: string
}

export interface ShopShipmentPayload {
  status: string
  carrier?: string
  tracking_number?: string
}

export interface ApiOrder {
  order_number: string
  status: string
  can_cancel?: boolean
  payment_status: string
  subtotal: number
  discount_amount: number
  tax_amount: number
  shipping_amount: number
  total: number
  currency: string
  shipping_address: Record<string, string> | null
  billing_address: Record<string, string> | null
  email: string | null
  phone: string | null
  customer_name: string | null
  note: string | null
  coupon_code: string | null
  placed_at: string | null
  shop_orders?: ApiShopOrder[]
  tracking_events?: {
    status: string
    description: string | null
    at: string
  }[]
  items: {
    id: number
    product_id: number
    product_variant_id: number
    product_name: string
    variant_label: string | null
    sku: string
    image_path: string | null
    unit_price: number
    quantity: number
    line_total: number
  }[]
  payment: {
    id: number
    method: string
    status: string
    transaction_id: string | null
    amount: number
    paid_at: string | null
  } | null
  shipment: {
    shop_order_id?: number | null
    tracking_number: string | null
    carrier: string | null
    status: string
    shipped_at: string | null
    delivered_at: string | null
    address_snapshot: Record<string, string> | null
  } | null
}

export const checkoutApi = {
  begin(payload: CheckoutPayload) {
    return apiClient.post<{ data: ApiOrder; reservation_expires_at: string }>('/checkout', payload)
  },

  confirm(orderNumber: string, transactionId?: string) {
    const body: Record<string, string> = {}
    if (transactionId) body.transaction_id = transactionId
    return apiClient.post<{ data: ApiOrder }>(`/checkout/${orderNumber}/confirm`, body)
  },

  cancel(orderNumber: string) {
    return apiClient.post<{ data: ApiOrder }>(`/checkout/${orderNumber}/cancel`)
  }
}
