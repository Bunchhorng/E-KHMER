import { expect, test, type Page } from '@playwright/test'

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
    } else if (path === '/api/coupons/validate') {
      state.couponRequests++
      state.previewPayloads.push(route.request().postDataJSON())
      if (!state.items.some(item => item.variant.product.shop_id === 1)) {
        return route.fulfill({ status: 422, json: { message: 'This coupon does not apply to products in your cart.', errors: { code: ['This coupon does not apply to products in your cart.'] } } })
      }
      json = { data: { code: 'SHOPA20', shop_id: 1, type: 'percentage', value: 20, min_order_amount: 50, max_discount_amount: 15, valid: true, eligible_subtotal: 100, discount_amount: 15, message: '20% off' } }
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
  await expect(page.locator('main')).toContainText('$313.50')
  expect(state.previewPayloads).toEqual([{ code: 'SHOPA20' }])
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
  await expect(page.locator('main')).toContainText('$220.00')
  await expect(page.getByRole('button', { name: 'Remove coupon', exact: true })).toHaveCount(0)
  expect(state.couponRequests).toBe(2)
})

test('a delayed coupon response cannot restore a discount after the cart changes', async ({ page }) => {
  await setup(page)
  let respond: (() => void) | undefined
  const responseGate = new Promise<void>(resolve => { respond = resolve })
  await page.route('**/api/coupons/validate', async route => {
    await responseGate
    await route.fulfill({ json: { data: { code: 'SHOPA20', shop_id: 1, type: 'percentage', value: 20, min_order_amount: 50, max_discount_amount: 15, valid: true, eligible_subtotal: 100, discount_amount: 15, message: '20% off' } } })
  })
  await page.goto('/cart')
  await expect(page.getByText('Shop A item', { exact: true })).toBeVisible()
  const request = page.waitForRequest('**/api/coupons/validate')
  await page.getByPlaceholder('Coupon code').fill('SHOPA20')
  await page.getByRole('button', { name: 'Apply Coupon', exact: true }).click()
  await request
  await page.getByRole('button', { name: 'Remove item', exact: true }).first().click()
  await expect(page.getByRole('link', { name: 'Shop A item', exact: true })).toHaveCount(0)
  const response = page.waitForResponse('**/api/coupons/validate')
  respond?.()
  await response
  await expect(page.getByRole('button', { name: 'Remove coupon', exact: true })).toHaveCount(0)
  await expect(page.locator('main')).toContainText('$220.00')
})
