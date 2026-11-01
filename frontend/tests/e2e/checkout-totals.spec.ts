import { expect, test, type Page } from '@playwright/test'
import { quoteFixture, type QuoteCoupon, type QuoteDelivery } from './quote-fixture'
import type { ApiCartItem } from '../../src/api/cart'
import type { ApiOrder, CheckoutQuote, QuotePayload } from '../../src/api/checkout'

const buyer = { id: 1, name: 'Buyer', email: 'buyer@example.test', role: 'customer', managed_shops: [] }
const coupons: Record<string, QuoteCoupon> = {
  CAP5: { code: 'CAP5', shop_id: 1, type: 'percentage', value: 10, max_discount_amount: 5 },
  CAP15: { code: 'CAP15', shop_id: 1, type: 'percentage', value: 10, max_discount_amount: 15 }
}
function item(id = 1, price = 100): ApiCartItem {
  return { id, quantity: 1, available: true, unavailable_reason: null, available_quantity: 50,
    variant: { id, price, name: 'Default', sku: `ITEM-${id}`, in_stock: true, compare_at_price: null, attributes: [],
      product: { id, name: `Item ${id}`, slug: `item-${id}`, cover_image: null, shop_id: 1, shop_name: 'Shop A' } } }
}

async function setup(page: Page, initialItems = [item()]) {
  await page.addInitScript(user => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('access_token', 'totals-test')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, buyer)
  const state = {
    items: initialItems, delivery: [
      { id: 1, shop_id: 1, name: 'Standard', price: 0 },
      { id: 2, shop_id: 1, name: 'Express', price: 7 },
      { id: 3, shop_id: 1, name: 'Priority', price: 9 }
    ] as QuoteDelivery[],
    quoteFailure: false, couponError: '', repriceNextCheckout: false, taxOverride: null as number | null,
    checkoutPayloads: [] as Record<string, unknown>[], order: null as ApiOrder | null
  }
  function quote(payload: QuotePayload) {
    const result = quoteFixture(state.items, { coupon: payload.coupon_code ? coupons[payload.coupon_code] : undefined, delivery: state.delivery, selections: payload.shipping_methods })
    if (state.taxOverride !== null && result.quote.coupon) {
      result.quote.tax_amount = state.taxOverride
      result.quote.total = 100 - 5 + state.taxOverride
    }
    return result.quote
  }
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    const method = route.request().method()
    let json: unknown = { data: [] }
    if (path === '/api/auth/me') json = { data: buyer }
    else if (path === '/api/cart' || path.startsWith('/api/cart/items/')) {
      const id = Number(path.split('/').at(-1))
      if (method === 'PUT') { const row = state.items.find(row => row.id === id); if (row) row.quantity = route.request().postDataJSON().quantity }
      if (method === 'DELETE') state.items = state.items.filter(row => row.id !== id)
      const subtotal = state.items.reduce((sum, row) => sum + (row.variant?.price ?? 0) * row.quantity, 0)
      json = { data: { id: 1, items: state.items, totals: { subtotal, tax_amount: subtotal / 10, discount_amount: 0, total: subtotal * 1.1, items_count: state.items.reduce((sum, row) => sum + row.quantity, 0) } } }
    } else if (path === '/api/checkout/quote') {
      if (state.quoteFailure) return route.fulfill({ status: 503, json: { message: 'Internal exception' } })
      const payload = route.request().postDataJSON() as QuotePayload
      if (state.couponError && payload.coupon_code) return route.fulfill({ status: 422, json: { message: state.couponError, errors: { code: [state.couponError] } } })
      if (Object.values(payload.shipping_methods ?? {}).some(id => !state.delivery.some(method => method.id === id))) {
        return route.fulfill({ status: 422, json: { errors: { shipping_methods: ['Choose an available delivery method for every shop.'] } } })
      }
      json = { data: quote(payload) }
    } else if (path === '/api/addresses') {
      json = { data: [{ id: 1, full_name: 'Buyer', address_line1: 'Street 1', city: 'PP', state: 'PP', postal_code: '12000', country: 'KH', is_default: true }] }
    } else if (path === '/api/shipping-methods') json = { data: state.delivery }
    else if (path === '/api/checkout') {
      const payload = route.request().postDataJSON()
      state.checkoutPayloads.push(payload)
      if (state.couponError && payload.coupon_code) return route.fulfill({ status: 422, json: { errors: { code: [state.couponError] } } })
      if (state.repriceNextCheckout) {
        state.repriceNextCheckout = false
        const variant = state.items[0]?.variant
        if (variant) variant.price = 120
      }
      const current = quote(payload)
      if (current.fingerprint !== payload.quote_fingerprint) return route.fulfill({ status: 409, json: { errors: { quote: ['Totals changed. Review the updated total before placing your order.'] } } })
      state.order = {
        ...current, order_number: 'TOTAL-ORDER', status: 'pending', payment_status: 'unpaid', coupon_code: payload.coupon_code ?? null,
        email: buyer.email, customer_name: buyer.name, phone: null, note: null, placed_at: '2026-11-02T00:00:00Z', shipping_address: null, billing_address: null,
        payment: { id: 1, method: 'cod', status: 'pending', amount: current.total, transaction_id: null, paid_at: null }, shipment: null,
        items: state.items.map(row => ({ id: row.id, product_id: row.id, product_variant_id: row.id, product_name: `Item ${row.id}`, variant_label: 'Default', sku: `ITEM-${row.id}`, image_path: null, unit_price: row.variant?.price ?? 0, quantity: row.quantity, line_total: (row.variant?.price ?? 0) * row.quantity }))
      }
      json = { data: state.order }
    } else if (path === '/api/checkout/TOTAL-ORDER/confirm') {
      if (state.order) state.order.status = 'confirmed'
      state.items = []
      json = { data: state.order }
    } else if (path === '/api/orders/TOTAL-ORDER') json = { data: state.order }
    await route.fulfill({ json })
  })
  return { state, quote }
}

const summary = (page: Page) => page.locator('main [data-quote-summary]')
async function coupon(page: Page, code = 'CAP5') {
  await page.getByPlaceholder('Coupon Code').fill(code)
  await page.getByRole('button', { name: 'Apply Coupon', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Remove coupon', exact: true })).toBeVisible()
  await expect(summary(page).locator('[data-amount="discount"]')).toContainText('−$')
}
async function review(page: Page) {
  await page.getByRole('main').getByRole('link', { name: 'Proceed to Checkout' }).click()
  await page.getByRole('button', { name: 'Continue to Delivery' }).click()
  await page.getByRole('button', { name: 'Continue to Payment' }).click()
  await page.getByRole('button', { name: 'Review Order' }).click()
}

test('capped quote stays identical in cart, drawer, checkout, order and payment', async ({ page }, testInfo) => {
  const { state } = await setup(page)
  await page.goto('/cart')
  await coupon(page)
  await expect(summary(page).locator('[data-amount="subtotal"]')).toContainText('$100.00')
  await expect(summary(page).locator('[data-amount="discount"]')).toContainText('−$5.00')
  await expect(summary(page).locator('[data-amount="tax"]')).toContainText('$9.50')
  await expect(summary(page).locator('[data-amount="shipping"]')).toContainText('$0.00')
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$104.50')
  await page.screenshot({ path: testInfo.outputPath('capped-total.png'), fullPage: true })
  await page.getByRole('button', { name: 'Open cart' }).click()
  await expect(page.locator('aside [data-amount="total"]')).toContainText('$104.50')
  await page.getByRole('button', { name: 'Close cart' }).click()
  await review(page)
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$104.50')
  await page.getByRole('button', { name: 'Place Order · $104.50' }).click()
  await expect(page).toHaveURL(/\/order\/success\/TOTAL-ORDER$/)
  await expect(page.locator('main')).toContainText('$104.50')
  expect(state.order?.total).toBe(104.5)
  expect(state.order?.payment?.amount).toBe(104.5)
  const payload = state.checkoutPayloads[0]
  expect(payload?.quote_fingerprint).toMatch(/^[a-f0-9]{64}$/)
  for (const field of ['subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total', 'payment_amount']) expect(payload).not.toHaveProperty(field)
})

test('quantity and coupon removal replace all amounts from the backend quote', async ({ page }) => {
  await setup(page)
  await page.goto('/cart')
  await coupon(page)
  await page.getByRole('button', { name: 'Increase quantity' }).click()
  await expect(summary(page).locator('[data-amount="subtotal"]')).toContainText('$200.00')
  await expect(summary(page).locator('[data-amount="discount"]')).toContainText('−$5.00')
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$214.50')
  await page.getByRole('button', { name: 'Remove coupon' }).click()
  await expect(summary(page).locator('[data-amount="discount"]')).toHaveCount(0)
  await expect(summary(page).locator('[data-amount="tax"]')).toContainText('$20.00')
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$220.00')
})

test('item removal reduces a formerly capped discount to the new eligible amount', async ({ page }) => {
  await setup(page, [item(1, 150), item(2, 50)])
  await page.goto('/cart')
  await coupon(page, 'CAP15')
  await expect(summary(page).locator('[data-amount="discount"]')).toContainText('−$15.00')
  await page.locator('[data-cart-item="1"]').getByRole('button', { name: 'Remove item' }).click()
  await expect(summary(page).locator('[data-amount="discount"]')).toContainText('−$5.00')
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$49.50')
})

test('shipping requote hides stale totals, blocks progression, and ignores an older response', async ({ page }) => {
  const { state, quote } = await setup(page)
  await page.goto('/cart')
  await coupon(page)
  await review(page)
  await page.getByRole('button', { name: 'Edit', exact: true }).nth(1).click()
  let release: (() => void) | undefined
  const gate = new Promise<void>(resolve => { release = resolve })
  await page.route('**/api/checkout/quote', async route => {
    const payload = route.request().postDataJSON() as QuotePayload
    if (payload.shipping_methods?.['1'] !== 2) return route.fallback()
    const response = quote(payload)
    await gate
    await route.fulfill({ json: { data: response } })
  })
  await page.getByRole('radio').nth(1).check()
  await expect(summary(page).getByRole('status')).toContainText('Updating totals')
  await expect(summary(page).locator('[data-amount="total"]')).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Continue to Payment' })).toBeDisabled()
  await page.getByRole('radio').nth(2).check()
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$113.50')
  const oldResponse = page.waitForResponse(response => response.url().endsWith('/api/checkout/quote') && response.request().postDataJSON().shipping_methods?.['1'] === 2)
  release?.()
  await oldResponse
  await expect(summary(page).locator('[data-amount="shipping"]')).toContainText('$9.00')
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$113.50')
  await page.getByRole('button', { name: 'Continue to Payment' }).click()
  await page.getByRole('button', { name: 'Review Order' }).click()
  await page.getByRole('button', { name: 'Place Order · $113.50' }).click()
  await expect.poll(() => state.order?.payment?.amount).toBe(113.5)
})

test('failed quote shows retry and cannot present an old amount as payable', async ({ page }) => {
  const { state } = await setup(page)
  state.quoteFailure = true
  await page.goto('/cart')
  await expect(summary(page).getByRole('alert')).toContainText("We couldn't update your totals")
  await expect(summary(page).locator('[data-amount="total"]')).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Proceed to Checkout' })).toBeDisabled()
  state.quoteFailure = false
  await summary(page).getByRole('button', { name: 'Retry' }).click()
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$110.00')
  await expect(page.getByRole('link', { name: 'Proceed to Checkout' })).toBeVisible()
})

test('price changes require reviewing the new quote before creating an order', async ({ page }) => {
  const { state } = await setup(page)
  await page.goto('/cart')
  await coupon(page)
  await review(page)
  state.repriceNextCheckout = true
  await page.getByRole('button', { name: 'Place Order · $104.50' }).click()
  await expect(page.getByRole('alert')).toContainText('Totals changed. Review the updated total')
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$126.50')
  expect(state.order).toBeNull()
  await page.getByRole('button', { name: 'Place Order · $126.50' }).click()
  await expect.poll(() => state.order?.total).toBe(126.5)
  expect(state.checkoutPayloads[0]?.quote_fingerprint).not.toBe(state.checkoutPayloads[1]?.quote_fingerprint)
})

test('coupon expiry at submission clears the discount and shows the business reason', async ({ page }) => {
  const { state } = await setup(page)
  await page.goto('/cart')
  await coupon(page)
  await review(page)
  state.couponError = 'This coupon has expired.'
  await page.getByRole('button', { name: 'Place Order · $104.50' }).click()
  await expect(page.getByRole('alert')).toContainText('This coupon has expired.')
  await expect(summary(page).locator('[data-amount="discount"]')).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Place Order · $110.00' })).toBeVisible()
  expect(state.order).toBeNull()
})

test('tax and grand total are rendered from the response without a local tax formula', async ({ page }) => {
  const { state } = await setup(page)
  // Deliberately distinct response cents prove that Vue renders, rather than derives, tax.
  state.taxOverride = 9.51
  await page.goto('/cart')
  await coupon(page)
  await expect(summary(page).locator('[data-amount="tax"]')).toContainText('$9.51')
  await expect(summary(page).locator('[data-amount="total"]')).toContainText('$104.51')
  await review(page)
  await expect(page.getByRole('button', { name: 'Place Order · $104.51' })).toBeVisible()
})

test('a removed delivery method requotes defaults and asks the customer to review fees', async ({ page }) => {
  const { state } = await setup(page)
  await page.goto('/cart')
  await coupon(page)
  state.delivery = state.delivery.filter(method => method.id !== 1)
  await review(page)
  await expect(summary(page).getByRole('status')).toContainText('Delivery options changed')
  await expect(summary(page).locator('[data-amount="shipping"]')).toContainText('$7.00')
  await expect(page.getByRole('button', { name: 'Place Order · $111.50' })).toBeVisible()
  expect(state.order).toBeNull()
})
