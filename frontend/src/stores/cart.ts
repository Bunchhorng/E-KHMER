import { defineStore } from 'pinia'
import { isAxiosError, isCancel } from 'axios'
import { cartApi } from '@/api'
import { checkoutApi, type CheckoutQuote } from '@/api/checkout'
import type { ApiCart, ApiCartItem } from '@/api/cart'
import type { CartItem, Coupon } from '@/types'
import { extractErrorMessage } from '@/api/errors'
import i18n from '@/plugins/i18n'

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

// Cart endpoints return the whole cart. Serialize reads/writes so an older
// response cannot replace a later mutation; keep each action's pending state.
// Key by $state: Pinia devtools can give each action a different `this` proxy.
const cartRequests = new WeakMap<object, Promise<void>>()
async function inCartOrder<T>(store: object, request: () => Promise<T>): Promise<T> {
  const previous = cartRequests.get(store) ?? Promise.resolve()
  let release: () => void = () => {}
  const current = new Promise<void>(resolve => { release = resolve })
  cartRequests.set(store, current)
  await previous
  try { return await request() }
  finally {
    release()
    if (cartRequests.get(store) === current) cartRequests.delete(store)
  }
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
    couponCode: '',
    quote: null as CheckoutQuote | null,
    quoteLoading: false,
    quoteError: '',
    quoteNotice: '',
    quoteRevision: 0,
    shippingSelections: {} as Record<string, number>,
    loaded: false,
    loading: false,
    loadError: '',
    mutationError: '',
    pendingMutations: 0,
    pendingActions: {} as Record<string, boolean>
  }),

  getters: {
    couponValidating(state): boolean {
      return state.quoteLoading && Boolean(state.couponCode)
    },
    mutating(state): boolean {
      return state.pendingMutations > 0
    },
    isPending: (state) => (key: string): boolean => Boolean(state.pendingActions[key]),
    subtotal(state): number {
      return state.quote?.subtotal ?? 0
    },
    discountAmount(state): number {
      return state.quote?.discount_amount ?? 0
    },
    taxAmount(): number {
      return this.quote?.tax_amount ?? 0
    },
    totalAmount(): number {
      return this.quote?.total ?? 0
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
      return this.loaded && !this.loading && !this.loadError && !this.mutating && !this.quoteLoading && !this.quoteError && Boolean(this.quote?.can_checkout) && this.availableItems.length > 0 && this.unavailableItems.length === 0
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
      this.invalidateQuote()
      try {
        await inCartOrder(this.$state, async () => {
          const { data } = await cartApi.get()
          await this.applyCart(data.data)
        })
      } catch {
        this.invalidateQuote()
        this.quoteLoading = false
        this.loadError = i18n.global.t('cart.load_error')
      } finally {
        this.loading = false
      }
    },

    async applyCart(cart: ApiCart): Promise<void> {
      this.invalidateQuote()
      this.items = cart.items.map(toDisplayItem)
      this.totals = { ...EMPTY_TOTALS, ...cart.totals }
      this.loaded = true
      this.loadError = ''
      await this.refreshQuote()
    },

    async mutateCart(key: string, request: () => Promise<{ data: { data: ApiCart } }>, fallback: string, clearCoupon = false): Promise<boolean> {
      if (this.isPending(key)) return false
      this.pendingActions[key] = true
      this.pendingMutations++
      try {
        return await inCartOrder(this.$state, async () => {
          this.mutationError = ''
          this.invalidateQuote()
          try {
            const { data } = await request()
            if (clearCoupon) {
              this.couponCode = ''
              this.appliedCoupon = null
              this.couponError = ''
              this.couponSuccess = ''
            }
            await this.applyCart(data.data)
            return true
          } catch (error) {
            if (!isCancel(error)) {
              const status = isAxiosError(error) ? error.response?.status : undefined
              const fallbackMessage = i18n.global.t(fallback)
              this.mutationError = status === 419 || status === 401
                ? i18n.global.t('cart.session_expired')
                : status && status >= 500 ? fallbackMessage : extractErrorMessage(error, fallbackMessage)
            }
            await this.refreshQuote()
            return false
          }
        })
      } finally {
        delete this.pendingActions[key]
        this.pendingMutations--
      }
    },

    async addItem({ variantId, quantity = 1 }: AddToCartInput): Promise<boolean> {
      return this.mutateCart(`add:${variantId}`, () => cartApi.addItem(Number(variantId), quantity), 'cart.add_error')
    },

    async updateQuantity(itemId: string | number, quantity: number): Promise<boolean> {
      return this.mutateCart(`update:${itemId}`, () => cartApi.updateItem(Number(itemId), quantity), 'cart.update_error')
    },

    async removeItem(itemId: string | number): Promise<boolean> {
      return this.mutateCart(`remove:${itemId}`, () => cartApi.removeItem(Number(itemId)), 'cart.remove_error')
    },

    async clear(): Promise<boolean> {
      return this.mutateCart('clear', () => cartApi.clear(), 'cart.clear_error', true)
    },

    async applyCoupon(code: string): Promise<boolean> {
      this.couponCode = code.trim().toUpperCase()
      this.appliedCoupon = null
      this.couponError = ''
      this.couponSuccess = ''
      return this.refreshQuote()
    },

    invalidateQuote(): void {
      this.quoteRevision++
      this.quote = null
      this.quoteLoading = true
      this.quoteError = ''
    },

    async selectShipping(shopId: number, methodId: number): Promise<void> {
      this.shippingSelections = { ...this.shippingSelections, [shopId]: methodId }
      await this.refreshQuote()
    },

    async refreshQuote(): Promise<boolean> {
      this.invalidateQuote()
      const revision = this.quoteRevision
      if (this.items.length === 0) {
        this.couponCode = ''
        this.appliedCoupon = null
        this.shippingSelections = {}
        this.quoteLoading = false
        return true
      }
      const code = this.couponCode

      try {
        const { data } = await checkoutApi.quote({
          ...(code ? { coupon_code: code } : {}),
          ...(Object.keys(this.shippingSelections).length ? { shipping_methods: { ...this.shippingSelections } } : {})
        })
        if (revision !== this.quoteRevision) return false
        this.quote = data.data
        this.shippingSelections = Object.fromEntries(data.data.shipping_methods.map(method => [String(method.shop_id), method.id]))
        const result = data.data.coupon
        this.appliedCoupon = result ? {
          id: 'coupon-' + result.code, code: result.code, type: result.type, value: result.value,
          minOrderAmount: result.min_order_amount ?? 0, shopId: result.shop_id,
          eligibleSubtotal: result.eligible_subtotal, discountAmount: result.discount_amount, description: result.message
        } : null
        this.couponSuccess = result?.message ?? ''
        return true
      } catch (error) {
        if (revision !== this.quoteRevision) return false
        if (code && isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.status === 422 && error.response.data.errors?.code) {
          this.couponError = extractErrorMessage(error, i18n.global.t('cart.coupon_invalid'))
          this.couponCode = ''
          this.appliedCoupon = null
          this.couponSuccess = ''
          await this.refreshQuote()
          return false
        }
        if (isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.status === 422 && Object.keys(error.response.data.errors ?? {}).some(field => field.startsWith('shipping_methods')) && Object.keys(this.shippingSelections).length) {
          this.shippingSelections = {}
          this.quoteNotice = i18n.global.t('cart.delivery_changed')
          await this.refreshQuote()
          return false
        }
        this.quoteError = isAxiosError(error) && (error.response?.status ?? 0) < 500
          ? extractErrorMessage(error, i18n.global.t('cart.quote_error'))
          : i18n.global.t('cart.quote_error')
        return false
      } finally {
        if (revision === this.quoteRevision) this.quoteLoading = false
      }
    },

    async removeCoupon(): Promise<void> {
      this.couponCode = ''
      this.appliedCoupon = null
      this.couponSuccess = ''
      this.couponError = ''
      await this.refreshQuote()
    },

    clearCouponFeedback(): void {
      this.couponSuccess = ''
      this.couponError = ''
    }
  }
})
