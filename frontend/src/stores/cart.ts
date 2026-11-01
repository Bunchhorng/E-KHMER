import { defineStore } from 'pinia'
import { isAxiosError } from 'axios'
import { cartApi, couponsApi } from '@/api'
import type { ApiCart, ApiCartItem } from '@/api/cart'
import type { CartItem, Coupon } from '@/types'
import { extractErrorMessage } from '@/api/errors'
import i18n from '@/plugins/i18n'

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
  const product = variant?.product
  const available = Boolean(variant && product) && (api.available ?? variant?.in_stock !== false)
  return {
    id: String(api.id),
    available,
    unavailableReason: available ? null : api.unavailable_reason ?? (!variant ? 'variant_deleted' : !product ? 'product_deleted' : 'out_of_stock'),
    availableQuantity: api.available_quantity ?? null,
    shopId: product?.shop_id,
    shopName: product?.shop_name,
    productId: product ? String(product.id) : '',
    slug: product?.slug ?? '',
    title: product?.name ?? i18n.global.t('cart.unavailable_item'),
    brand: '',
    image: product?.cover_image ?? '',
    unitPrice: variant?.price ?? 0,
    quantity: api.quantity,
    variant: variant ? {
      variantId: String(variant.id),
      attributes: variant.attributes ?? [],
      sku: variant.sku
    } : null,
    variantName: variant?.name
  }
}

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [] as CartItem[],
    totals: { ...EMPTY_TOTALS } as CartTotals,
    appliedCoupon: null as Coupon | null,
    couponError: '',
    couponSuccess: '',
    couponValidating: false,
    couponRevision: 0,
    loaded: false,
    loading: false,
    loadError: '',
    mutationError: '',
    pendingMutations: 0
  }),

  getters: {
    mutating(state): boolean {
      return state.pendingMutations > 0
    },
    subtotal(state): number {
      return state.totals.subtotal
    },
    discountAmount(state): number {
      return state.appliedCoupon?.discountAmount ?? 0
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
      return this.loaded && !this.loading && !this.loadError && this.items.length === 0
    },
    availableItems(state): CartItem[] {
      return state.items.filter(item => item.available)
    },
    unavailableItems(state): CartItem[] {
      return state.items.filter(item => !item.available)
    },
    canCheckout(): boolean {
      return this.loaded && !this.loading && !this.loadError && !this.mutating && !this.couponValidating && this.availableItems.length > 0 && this.unavailableItems.length === 0
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
      this.loadError = ''
      try {
        const { data } = await cartApi.get()
        await this.applyCart(data.data)
      } catch {
        this.removeCoupon()
        this.loadError = i18n.global.t('cart.load_error')
      } finally {
        this.loading = false
      }
    },

    async applyCart(cart: ApiCart): Promise<void> {
      const couponCode = this.appliedCoupon?.code
      this.removeCoupon()
      this.items = cart.items.map(toDisplayItem)
      this.totals = { ...EMPTY_TOTALS, ...cart.totals }
      this.loaded = true
      this.loadError = ''
      if (couponCode && this.items.length > 0) await this.applyCoupon(couponCode)
    },

    async mutateCart(request: () => Promise<{ data: { data: ApiCart } }>, fallback: string, clearCoupon = false): Promise<boolean> {
      this.pendingMutations++
      this.mutationError = ''
      try {
        const { data } = await request()
        if (clearCoupon) this.removeCoupon()
        await this.applyCart(data.data)
        return true
      } catch (error) {
        const message = i18n.global.t(fallback)
        this.mutationError = isAxiosError(error) && (error.response?.status ?? 0) >= 500
          ? message
          : extractErrorMessage(error, message)
        return false
      } finally {
        this.pendingMutations--
      }
    },

    async addItem({ variantId, quantity = 1 }: AddToCartInput): Promise<boolean> {
      return this.mutateCart(() => cartApi.addItem(Number(variantId), quantity), 'cart.add_error')
    },

    async updateQuantity(itemId: string | number, quantity: number): Promise<boolean> {
      return this.mutateCart(() => cartApi.updateItem(Number(itemId), quantity), 'cart.update_error')
    },

    async removeItem(itemId: string | number): Promise<boolean> {
      return this.mutateCart(() => cartApi.removeItem(Number(itemId)), 'cart.remove_error')
    },

    async clear(): Promise<boolean> {
      return this.mutateCart(() => cartApi.clear(), 'cart.clear_error', true)
    },

    async applyCoupon(code: string): Promise<boolean> {
      const normalized = code.trim().toUpperCase()
      const revision = ++this.couponRevision
      this.appliedCoupon = null
      this.couponValidating = true
      this.couponError = ''
      this.couponSuccess = ''

      try {
        const { data } = await couponsApi.validate(normalized)
        if (revision !== this.couponRevision) return false
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
          shopId: result.shop_id,
          eligibleSubtotal: result.eligible_subtotal,
          discountAmount: result.discount_amount,
          description: result.message
        }
        this.couponSuccess = result.message || `Coupon ${result.code} applied!`
        return true
      } catch (error) {
        if (revision !== this.couponRevision) return false
        this.couponError = extractErrorMessage(error, `Coupon "${normalized}" is not valid.`)
        return false
      } finally {
        if (revision === this.couponRevision) this.couponValidating = false
      }
    },

    removeCoupon(): void {
      this.couponRevision++
      this.couponValidating = false
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
