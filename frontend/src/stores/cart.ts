import { defineStore } from 'pinia'
import { cartApi, couponsApi } from '@/api'
import type { ApiCart, ApiCartItem } from '@/api/cart'
import type { CartItem, Coupon } from '@/types'

export const TAX_RATE = 0.1

export interface AddToCartInput {
  variantId: string | number
  quantity?: number
}

export interface CartTotals {
  subtotal: number
  discount_amount: number
  tax_amount: number
  total: number
  items_count: number
}

const EMPTY_TOTALS: CartTotals = { subtotal: 0, discount_amount: 0, tax_amount: 0, total: 0, items_count: 0 }

function round2(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100
}

function toDisplayItem(api: ApiCartItem): CartItem {
  const variant = api.variant
  return {
    id: String(api.id),
    productId: String(variant.product?.id ?? variant.id),
    slug: variant.product?.slug ?? '',
    title: variant.product?.name ?? variant.name,
    brand: '',
    image: variant.product?.cover_image ?? '',
    unitPrice: variant.price ?? 0,
    quantity: api.quantity,
      variant: {
        variantId: String(variant.id),
      attributes: variant.attributes ?? [],
      sku: variant.sku
    },
    variantName: variant.name
  }
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [] as CartItem[],
    totals: { ...EMPTY_TOTALS } as CartTotals,
    appliedCoupon: null as Coupon | null,
    couponError: '',
    couponSuccess: '',
    loaded: false,
    loading: false
  }),

  getters: {
    subtotal(state): number {
      return state.totals.subtotal
    },
    discountAmount(state): number {
      if (!state.appliedCoupon) return 0
      if (state.appliedCoupon.type === 'percentage') {
        return round2((this.totals.subtotal * state.appliedCoupon.value) / 100)
      }
      return round2(Math.min(state.appliedCoupon.value, this.totals.subtotal))
    },
    taxAmount(): number {
      // Checkout taxes the post-discount amount, so keep the cart estimate in
      // lockstep with the server-side checkout calculation.
      return round2(Math.max(this.totals.subtotal - this.discountAmount, 0) * TAX_RATE)
    },
    totalAmount(): number {
      return round2(Math.max(this.totals.subtotal - this.discountAmount, 0) + this.taxAmount)
    },
    totalItemCount(): number {
      return this.totals.items_count
    },
    isEmpty(): boolean {
      return this.items.length === 0
    }
  },

  actions: {
    async initialize(): Promise<void> {
      if (this.loaded || this.loading) return
      await this.fetch()
    },

    async fetch(): Promise<void> {
      if (this.loading) return
      this.loading = true
      try {
        const { data } = await cartApi.get()
        this.applyCart(data.data)
      } catch {
        /* keep last known state */
      } finally {
        this.loading = false
        this.loaded = true
      }
    },

    applyCart(cart: ApiCart): void {
      this.items = cart.items.map(toDisplayItem)
      this.totals = { ...EMPTY_TOTALS, ...cart.totals }
    },

    async addItem({ variantId, quantity = 1 }: AddToCartInput): Promise<void> {
      const { data } = await cartApi.addItem(Number(variantId), quantity)
      this.applyCart(data.data)
      this.clearCouponFeedback()
    },

    async updateQuantity(itemId: string | number, quantity: number): Promise<void> {
      const { data } = await cartApi.updateItem(Number(itemId), quantity)
      this.applyCart(data.data)
    },

    async removeItem(itemId: string | number): Promise<void> {
      const { data } = await cartApi.removeItem(Number(itemId))
      this.applyCart(data.data)
    },

    async clear(): Promise<void> {
      const { data } = await cartApi.clear()
      this.applyCart(data.data)
      this.appliedCoupon = null
      this.couponSuccess = ''
      this.couponError = ''
    },

    async applyCoupon(code: string): Promise<boolean> {
      const normalized = code.trim().toUpperCase()
      this.couponError = ''
      this.couponSuccess = ''

      try {
        const { data } = await couponsApi.validate(normalized, this.totals.subtotal)
        const result = data.data

        if (!result.valid) {
          this.couponError = result.message || `Coupon "${normalized}" is not valid.`
          return false
        }

        this.appliedCoupon = {
          id: 'coupon-' + normalized,
          code: result.code,
          type: result.type,
          value: result.value,
          minOrderAmount: result.min_order_amount ?? 0,
          description: result.message
        }
        this.couponSuccess = result.message || `Coupon ${result.code} applied!`
        return true
      } catch {
        this.couponError = `Coupon "${normalized}" is not valid.`
        return false
      }
    },

    removeCoupon(): void {
      this.appliedCoupon = null
      this.couponSuccess = ''
      this.couponError = ''
    },

    clearCouponFeedback(): void {
      this.couponSuccess = ''
      this.couponError = ''
    }
  }
})
