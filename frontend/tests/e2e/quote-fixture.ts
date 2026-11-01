import { createHash } from 'node:crypto'
import type { CheckoutQuote } from '../../src/api/checkout'

interface QuoteItem {
  id: number
  quantity: number
  available?: boolean
  variant: { price: number | null; product: { shop_id?: number | null } | null } | null
}
export interface QuoteCoupon {
  code: string
  shop_id: number | null
  type: 'percentage' | 'fixed'
  value: number
  max_discount_amount: number | null
  min_order_amount?: number
}
export interface QuoteDelivery { id: number; shop_id: number | null; name: string; price: number }

/** Test-server fixture only. Production components render the returned amounts. */
export function quoteFixture(items: QuoteItem[], options: { coupon?: QuoteCoupon; delivery?: QuoteDelivery[]; selections?: Record<string, number> } = {}): { quote: CheckoutQuote; error: string | null } {
  const live = items.filter(item => item.available !== false && item.variant?.product)
  const subtotal = live.reduce((sum, item) => sum + (item.variant?.price ?? 0) * item.quantity, 0)
  const eligible = options.coupon ? live.filter(item => options.coupon?.shop_id === null || item.variant?.product?.shop_id === options.coupon?.shop_id) : []
  const eligibleSubtotal = eligible.reduce((sum, item) => sum + (item.variant?.price ?? 0) * item.quantity, 0)
  const raw = options.coupon ? options.coupon.type === 'percentage' ? Math.round(eligibleSubtotal * options.coupon.value) / 100 : options.coupon.value : 0
  const discount = options.coupon ? Math.min(raw, eligibleSubtotal, options.coupon.type === 'percentage' ? options.coupon.max_discount_amount ?? subtotal : subtotal) : 0
  const tax = Math.round((subtotal - discount) * 10) / 100
  const shipping = [...new Set(live.map(item => item.variant?.product?.shop_id ?? 0))].map(shopId => {
    const method = options.delivery?.find(method => method.id === options.selections?.[shopId])
      ?? options.delivery?.filter(method => method.shop_id === null || method.shop_id === shopId).sort((a, b) => a.price - b.price)[0]
      ?? { id: shopId, name: `Shop ${shopId} delivery`, price: 0 }
    return { ...method, shop_id: shopId }
  })
  const shippingAmount = shipping.reduce((sum, method) => sum + method.price, 0)
  const coupon = options.coupon ? { ...options.coupon, valid: true, eligible_subtotal: eligibleSubtotal, discount_amount: discount, raw_discount: raw, min_order_amount: options.coupon.min_order_amount ?? 0, message: options.coupon.type === 'percentage' ? `${options.coupon.value}% off` : `$${options.coupon.value.toFixed(2)} off` } : null
  const quote: CheckoutQuote = {
    currency: 'USD', subtotal, eligible_subtotal: eligibleSubtotal, raw_discount: raw, discount_amount: discount,
    tax_amount: tax, shipping_amount: shippingAmount, total: Math.round((subtotal - discount + tax + shippingAmount) * 100) / 100,
    coupon, shipping_methods: shipping, shipping_complete: shipping.length > 0, can_checkout: live.length > 0 && live.length === items.length,
    fingerprint: createHash('sha256').update(JSON.stringify([items, shipping, coupon])).digest('hex')
  }
  const error = options.coupon && eligibleSubtotal <= 0 ? 'This coupon does not apply to products in your cart.'
    : options.coupon && eligibleSubtotal < (options.coupon.min_order_amount ?? 0) ? 'This coupon requires a minimum order amount of 50.00.' : null
  return { quote, error }
}
