import apiClient from './client'

export type CartUnavailableReason = 'variant_deleted' | 'product_deleted' | 'product_inactive' | 'shop_inactive' | 'variant_inactive' | 'out_of_stock' | 'insufficient_stock' | 'invalid_quantity'

export interface ApiCartItem {
  id: number
  quantity: number
  available: boolean
  unavailable_reason: CartUnavailableReason | null
  available_quantity: number
  variant: {
    id: number
    sku: string
    name: string
    price: number | null
    compare_at_price: number | null
    in_stock: boolean
    attributes: { name: string; value: string }[]
    product: {
      id: number
      slug: string
      name: string
      cover_image: string | null
      shop_id?: number | null
      shop_name?: string | null
    } | null
  } | null
}

export interface ApiCart {
  id: number
  items: ApiCartItem[]
  totals: {
    subtotal: number
    discount_amount: number
    tax_amount: number
    total: number
    items_count: number
  }
}

export const cartApi = {
  get() {
    return apiClient.get<{ data: ApiCart }>('/cart')
  },

  addItem(productVariantId: number, quantity = 1) {
    return apiClient.post<{ data: ApiCart }>('/cart', {
      product_variant_id: productVariantId,
      quantity
    })
  },

  updateItem(cartItemId: number, quantity: number) {
    return apiClient.put<{ data: ApiCart }>(`/cart/items/${cartItemId}`, { quantity })
  },

  removeItem(cartItemId: number) {
    return apiClient.delete<{ data: ApiCart }>(`/cart/items/${cartItemId}`)
  },

  clear() {
    return apiClient.delete<{ data: ApiCart }>('/cart')
  },

  getTotals() {
    return apiClient.get<{ data: { items_count: number; subtotal: number; tax_amount: number; discount_applicable: number; total: number } }>('/cart/totals')
  }
}
