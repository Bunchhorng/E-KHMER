import { expect, test, type Page } from '@playwright/test'
import { quoteFixture } from './quote-fixture'

const shopCoupon = { code: 'SHOPA20', shop_id: 1, type: 'percentage' as const, value: 20, min_order_amount: 50, max_discount_amount: 15 }
const delivery = [{ id: 1, name: 'Shop A delivery', shop_id: 1, price: 2 }, { id: 2, name: 'Shop B delivery', shop_id: 2, price: 5 }]

const buyer = { id: 1, name: 'Buyer', email: 'buyer@example.test', role: 'customer', managed_shops: [] }
const liveItems = [
  { id: 1, quantity: 1, variant: { id: 1, name: 'Default', sku: 'A', price: 100, attributes: [], product: { id: 1, name: 'Shop A item', slug: 'a-item', cover_image: null, shop_id: 1, shop_name: 'Shop A' } } },
  { id: 2, quantity: 1, variant: { id: 2, name: 'Default', sku: 'B', price: 200, attributes: [], product: { id: 2, name: 'Shop B item', slug: 'b-item', cover_image: null, shop_id: 2, shop_name: 'Shop B' } } }
]

async function setup(page: Page) {
  await page.addInitScript(user => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('access_token', 'coupon-browser-test')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, buyer)
  const state = { items: [...liveItems], couponRequests: 0, previewPayloads: [] as unknown[] }
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    let json: unknown = { data: [] }
    if (path === '/api/auth/me') json = { data: buyer }
    else if (path === '/api/cart' || path.startsWith('/api/cart/items/')) {
      if (route.request().method() === 'DELETE') state.items = state.items.filter(item => item.id !== Number(path.split('/').at(-1)))
      const subtotal = state.items.reduce((sum, item) => sum + item.variant.price * item.quantity, 0)
      json = { data: { id: 1, items: state.items, totals: { subtotal, discount_amount: 0, tax_amount: subtotal / 10, total: subtotal * 1.1, items_count: state.items.length } } }
    } else if (path === '/api/checkout/quote') {
      const payload = route.request().postDataJSON()
      if (payload.coupon_code) { state.couponRequests++; state.previewPayloads.push(payload) }
      const result = quoteFixture(state.items, { coupon: payload.coupon_code ? shopCoupon : undefined, delivery, selections: payload.shipping_methods })
      if (result.error) return route.fulfill({ status: 422, json: { message: result.error, errors: { code: [result.error] } } })
      json = { data: result.quote }
    } else if (path === '/api/addresses') {
      json = { data: [{ id: 1, full_name: 'Buyer', address_line1: 'Street 1', city: 'PP', state: 'PP', postal_code: '12000', country: 'KH', is_default: true }] }
    } else if (path === '/api/shipping-methods') {
      json = { data: [{ id: 1, name: 'Shop A delivery', shop_id: 1, price: 2 }, { id: 2, name: 'Shop B delivery', shop_id: 2, price: 5 }] }
    }
    await route.fulfill({ json })
  })
  return state
}

async function applyCoupon(page: Page) {
  await page.getByPlaceholder('Coupon code').fill('SHOPA20')
  await page.getByRole('button', { name: 'Apply Coupon', exact: true }).click()
  await expect(page.getByText('20% off', { exact: true })).toBeVisible()
}

test('mixed-shop cart and checkout display the backend discount cap and total', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/cart')
  await expect(page.getByText('Shop A item', { exact: true })).toBeVisible()
  await applyCoupon(page)
  await expect(page.locator('main')).toContainText('−$15.00')
  await expect(page.locator('main')).toContainText('$320.50')
  expect(state.previewPayloads).toEqual([{ coupon_code: 'SHOPA20', shipping_methods: { 1: 1, 2: 2 } }])
  await page.getByRole('link', { name: 'Proceed to Checkout' }).click()
  await page.getByRole('button', { name: 'Continue to Delivery' }).click()
  await page.getByRole('button', { name: 'Continue to Payment' }).click()
  await page.getByRole('button', { name: 'Review Order' }).click()
  await expect(page.getByRole('button', { name: 'Place Order · $320.50' })).toBeVisible()
})

test('removing the eligible shop revalidates the coupon and shows the server reason', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/cart')
  await expect(page.getByText('Shop A item', { exact: true })).toBeVisible()
  await applyCoupon(page)
  await page.getByRole('button', { name: 'Remove item', exact: true }).first().click()
  await expect(page.getByText('This coupon does not apply to products in your cart.', { exact: true })).toBeVisible()
  await expect(page.locator('main')).toContainText('$225.00')
  await expect(page.getByRole('button', { name: 'Remove coupon', exact: true })).toHaveCount(0)
  expect(state.couponRequests).toBe(2)
})

test('a delayed coupon response cannot restore a discount after the cart changes', async ({ page }) => {
  const state = await setup(page)
  let respond: (() => void) | undefined
  const responseGate = new Promise<void>(resolve => { respond = resolve })
  let delayed = false
  await page.route('**/api/checkout/quote', async route => {
    if (!route.request().postDataJSON().coupon_code || delayed) return route.fallback()
    delayed = true
    const result = quoteFixture(state.items, { coupon: shopCoupon, delivery })
    await responseGate
    await route.fulfill({ json: { data: result.quote } })
  })
  await page.goto('/cart')
  await expect(page.getByText('Shop A item', { exact: true })).toBeVisible()
  const request = page.waitForRequest(request => request.url().endsWith('/api/checkout/quote') && Boolean(request.postDataJSON().coupon_code))
  await page.getByPlaceholder('Coupon code').fill('SHOPA20')
  await page.getByRole('button', { name: 'Apply Coupon', exact: true }).click()
  await request
  await page.getByRole('button', { name: 'Remove item', exact: true }).first().click()
  await expect(page.getByRole('link', { name: 'Shop A item', exact: true })).toHaveCount(0)
  const response = page.waitForResponse(response => response.url().endsWith('/api/checkout/quote') && Boolean(response.request().postDataJSON().coupon_code))
  respond?.()
  await response
  await expect(page.getByRole('button', { name: 'Remove coupon', exact: true })).toHaveCount(0)
  await expect(page.locator('main')).toContainText('$225.00')
})
