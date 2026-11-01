import { expect, test, type Page } from '@playwright/test'
import type { ApiCartItem } from '../../src/api/cart'
import { quoteFixture } from './quote-fixture'

const buyer = { id: 1, name: 'Buyer', email: 'buyer@example.test', role: 'customer', managed_shops: [] }
function liveItem(id: number, price: number, quantity = 1): ApiCartItem {
  return { id, quantity, available: true, unavailable_reason: null, available_quantity: 20,
    variant: { id, name: 'Default', sku: `ITEM-${id}`, price, compare_at_price: null, in_stock: true, attributes: [],
      product: { id, name: `Valid item ${id}`, slug: `item-${id}`, cover_image: null, shop_id: id, shop_name: `Shop ${id}` } } }
}
const deletedItem = (): ApiCartItem => ({ id: 2, quantity: 1, available: false, unavailable_reason: 'variant_deleted', available_quantity: 0, variant: null })

async function setup(page: Page, items = [liveItem(1, 50), deletedItem(), liveItem(3, 30)], authenticated = true) {
  await page.addInitScript(({ user, authenticated }) => {
    localStorage.setItem('app_locale', 'en')
    if (authenticated) {
      localStorage.setItem('access_token', 'cart-recovery-test')
      localStorage.setItem('ekhmer_user', JSON.stringify(user))
    }
  }, { user: buyer, authenticated })
  const state = { items, loadFailures: 0, mutationFailure: '', checkoutRequests: 0, checkoutPayloads: [] as unknown[], guestSessions: [] as (string | null)[] }
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    const method = route.request().method()
    let json: unknown = { data: [] }
    if (path === '/api/auth/me') json = { data: buyer }
    else if (path === '/api/cart' || path.startsWith('/api/cart/items/')) {
      state.guestSessions.push(await route.request().headerValue('x-session-id'))
      if (method === 'GET' && state.loadFailures > 0) {
        state.loadFailures--
        return route.fulfill({ status: 503, json: { message: 'Server unavailable' } })
      }
      if (method !== 'GET' && state.mutationFailure) {
        return route.fulfill({ status: 422, json: { message: state.mutationFailure, errors: { cart: [state.mutationFailure] } } })
      }
      const id = Number(path.split('/').at(-1))
      if (method === 'DELETE') state.items = path === '/api/cart' ? [] : state.items.filter(item => item.id !== id)
      if (method === 'PUT') {
        const item = state.items.find(item => item.id === id)
        if (item) {
          item.quantity = route.request().postDataJSON().quantity
          item.available = item.quantity <= (item.available_quantity ?? 0)
          item.unavailable_reason = item.available ? null : 'insufficient_stock'
        }
      }
      const subtotal = state.items.reduce((sum, item) => sum + (item.available ? (item.variant?.price ?? 0) * item.quantity : 0), 0)
      json = { data: { id: 1, items: state.items, totals: { subtotal, discount_amount: 0, tax_amount: subtotal / 10, total: subtotal * 1.1, items_count: state.items.reduce((sum, item) => sum + item.quantity, 0) } } }
    } else if (path === '/api/checkout/quote') {
      const payload = route.request().postDataJSON()
      json = { data: quoteFixture(state.items, { selections: payload.shipping_methods }).quote }
    } else if (path === '/api/addresses') {
      json = { data: [{ id: 1, full_name: 'Buyer', address_line1: 'Street 1', city: 'PP', state: 'PP', postal_code: '12000', country: 'KH', is_default: true }] }
    } else if (path === '/api/shipping-methods') {
      json = { data: [1, 3].map(id => ({ id, shop_id: id, name: `Shop ${id} delivery`, price: 0 })) }
    } else if (path === '/api/checkout') {
      state.checkoutRequests++
      state.checkoutPayloads.push(route.request().postDataJSON())
      json = { data: { order_number: 'RECOVERED-ORDER', status: 'confirmed', payment_status: 'paid' } }
    }
    await route.fulfill({ json })
  })
  return state
}

test('mixed multi-shop cart preserves valid items, removes a null variant, and checks out only valid shops', async ({ page }, testInfo) => {
  const state = await setup(page)
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  await page.goto('/cart')
  await expect(page.getByRole('region', { name: 'Available', exact: true })).toContainText('Valid item 1')
  await expect(page.getByRole('region', { name: 'Available', exact: true })).toContainText('Valid item 3')
  const stale = page.locator('[data-cart-item="2"]')
  await expect(stale).toContainText('This option is no longer available.')
  await expect(stale.getByRole('link')).toHaveCount(0)
  await expect(page.getByText('Your cart is empty', { exact: true })).toHaveCount(0)
  await expect(page.getByText('3 items in cart', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Proceed to Checkout', exact: true })).toBeDisabled()
  await expect(page.locator('main')).toContainText('$88.00')
  await page.screenshot({ path: testInfo.outputPath('cart-recovery.png'), fullPage: true })
  await stale.getByRole('button', { name: 'Remove item' }).click()
  await expect(stale).toHaveCount(0)
  await page.getByRole('link', { name: 'Proceed to Checkout' }).click()
  await page.getByRole('button', { name: 'Continue to Delivery' }).click()
  await expect(page.getByRole('group', { name: 'Shop 2', exact: true })).toHaveCount(0)
  await page.getByRole('button', { name: 'Continue to Payment' }).click()
  await page.getByRole('button', { name: 'Review Order' }).click()
  await page.getByRole('button', { name: 'Place Order · $88.00' }).click()
  await expect.poll(() => state.checkoutRequests).toBe(1)
  expect(state.checkoutPayloads[0]).toMatchObject({ shipping_methods: { 1: 1, 3: 3 } })
  expect(state.items.map(item => item.id)).toEqual([1, 3])
  expect(errors).toEqual([])
})

test('drawer preserves a guest cart and can remove the unavailable row', async ({ page }) => {
  const state = await setup(page, undefined, false)
  await page.goto('/cart')
  await page.getByRole('button', { name: 'Open cart', exact: true }).click()
  const drawer = page.locator('aside').filter({ has: page.getByRole('heading', { name: /Your Cart/ }) })
  await expect(drawer).toContainText('Valid item 1')
  await expect(drawer).toContainText('Valid item 3')
  await expect(drawer).toContainText('This option is no longer available.')
  await expect(drawer.getByRole('button', { name: 'Proceed to Checkout', exact: true })).toBeDisabled()
  await drawer.locator('[data-cart-item="2"]').getByRole('button', { name: 'Remove item' }).click()
  await expect(drawer.getByRole('link', { name: 'Proceed to Checkout', exact: true })).toBeVisible()
  expect(state.guestSessions.every(session => Boolean(session))).toBe(true)
  expect(state.items).toHaveLength(2)
})

test('missing product is safe and a cart containing only unavailable rows is not empty', async ({ page }) => {
  const missingProduct = liveItem(4, 100)
  if (missingProduct.variant) missingProduct.variant.product = null
  missingProduct.available = false
  missingProduct.unavailable_reason = 'product_deleted'
  await setup(page, [deletedItem(), missingProduct])
  await page.goto('/cart')
  await expect(page.locator('[data-cart-item]')).toHaveCount(2)
  await expect(page.getByText('This product is no longer available.', { exact: true })).toBeVisible()
  await expect(page.getByText('Your cart is empty', { exact: true })).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Proceed to Checkout' })).toBeDisabled()
  await expect(page.locator('main')).toContainText('$0.00')
  await page.getByRole('button', { name: 'Clear cart', exact: true }).click()
  await expect(page.getByText('Your cart is empty', { exact: true })).toBeVisible()
})

test('direct checkout navigation blocks unresolved stale rows and links to recovery', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/checkout')
  await expect(page.getByRole('heading', { name: 'Needs attention' })).toBeVisible()
  await expect(page.getByRole('alert')).toContainText('1 item(s) need attention before checkout')
  await expect(page.getByRole('button', { name: /Place Order/ })).toHaveCount(0)
  await page.getByRole('link', { name: 'Back to Cart', exact: true }).click()
  await expect(page).toHaveURL(/\/cart$/)
  expect(state.checkoutRequests).toBe(0)
})

test('quantity shortage is resolved only by an explicit customer change', async ({ page }) => {
  const item = liveItem(1, 10, 5)
  item.available = false
  item.unavailable_reason = 'insufficient_stock'
  item.available_quantity = 2
  const state = await setup(page, [item])
  await page.goto('/cart')
  await expect(page.getByText('Only 2 are currently available. Reduce the quantity to continue.')).toBeVisible()
  expect(state.items[0]?.quantity).toBe(5)
  await expect(page.locator('main')).toContainText('$0.00')
  await page.getByRole('button', { name: 'Decrease quantity' }).click()
  await expect(page.getByRole('link', { name: 'Proceed to Checkout' })).toBeVisible()
  expect(state.items[0]?.quantity).toBe(2)
  await expect(page.locator('main')).toContainText('$22.00')
})

test('cart load errors show retry instead of empty and retry restores all rows', async ({ page }) => {
  const state = await setup(page)
  state.loadFailures = 1
  await page.goto('/cart')
  await expect(page.getByRole('alert')).toContainText("We couldn't load your cart")
  await expect(page.getByText('Your cart is empty', { exact: true })).toHaveCount(0)
  await page.getByRole('button', { name: 'Retry', exact: true }).click()
  await expect(page.locator('[data-cart-item]')).toHaveCount(3)
  await expect(page.getByRole('alert')).toHaveCount(0)
})

test('checkout refresh invalidates a shop coupon when its eligible item becomes unavailable', async ({ page }) => {
  const state = await setup(page, [liveItem(1, 100), liveItem(3, 200)])
  let quotes = 0
  await page.route('**/api/checkout/quote', async route => {
    if (!route.request().postDataJSON().coupon_code) return route.fallback()
    quotes++
    if (!state.items.find(item => item.id === 1)?.available) {
      return route.fulfill({ status: 422, json: { message: 'This coupon does not apply to products in your cart.', errors: { code: ['This coupon does not apply to products in your cart.'] } } })
    }
    await route.fulfill({ json: { data: quoteFixture(state.items, { coupon: { code: 'SHOPA20', shop_id: 1, type: 'percentage', value: 20, min_order_amount: 50, max_discount_amount: 15 } }).quote } })
  })
  await page.goto('/cart')
  await page.getByPlaceholder('Coupon Code').fill('SHOPA20')
  await page.getByRole('button', { name: 'Apply Coupon' }).click()
  await expect(page.locator('main')).toContainText('−$15.00')
  const item = state.items.find(item => item.id === 1)
  if (item) { item.available = false; item.unavailable_reason = 'variant_inactive' }
  await page.getByRole('link', { name: 'Proceed to Checkout' }).click()
  await expect(page.getByRole('heading', { name: 'Needs attention' })).toBeVisible()
  await page.getByRole('link', { name: 'Back to Cart' }).click()
  await expect(page.getByText('This coupon does not apply to products in your cart.', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Remove coupon' })).toHaveCount(0)
  await expect(page.locator('main')).toContainText('$220.00')
  expect(quotes).toBe(2)
  expect(state.checkoutRequests).toBe(0)
})

test('a pending initial cart request shows loading instead of empty', async ({ page }) => {
  await setup(page)
  let release: (() => void) | undefined
  const gate = new Promise<void>(resolve => { release = resolve })
  await page.route('**/api/cart', async route => { await gate; await route.fallback() })
  await page.goto('/cart')
  await expect(page.getByRole('status')).toContainText('Loading your cart')
  await expect(page.getByText('Your cart is empty', { exact: true })).toHaveCount(0)
  release?.()
  await expect(page.locator('[data-cart-item]')).toHaveCount(3)
})

for (const operation of ['update', 'remove', 'clear']) {
  test(`failed ${operation} mutation shows feedback and preserves the cart`, async ({ page }) => {
    const state = await setup(page)
    state.mutationFailure = `Could not ${operation} the cart. Please try again.`
    await page.goto('/cart')
    const before = structuredClone(state.items)
    if (operation === 'update') await page.locator('[data-cart-item="1"]').getByRole('button', { name: 'Increase quantity' }).click()
    else if (operation === 'remove') await page.locator('[data-cart-item="2"]').getByRole('button', { name: 'Remove item' }).click()
    else await page.getByRole('button', { name: 'Clear cart', exact: true }).click()
    await expect(page.getByRole('alert')).toContainText(state.mutationFailure)
    await expect(page.locator('[data-cart-item]')).toHaveCount(3)
    expect(state.items).toEqual(before)
    await page.getByRole('button', { name: 'Dismiss', exact: true }).click()
    state.mutationFailure = ''
    await page.locator('[data-cart-item="2"]').getByRole('button', { name: 'Remove item' }).click()
    await expect(page.locator('[data-cart-item]')).toHaveCount(2)
    await expect(page.getByRole('alert')).toHaveCount(0)
  })
}

test('server mutation failures show a safe retry message without internal errors', async ({ page }) => {
  await setup(page)
  await page.route('**/api/cart/items/2', route => route.fulfill({ status: 500, json: { message: 'SQLSTATE internal failure', errors: { server: ['TypeError internal failure'] } } }))
  await page.goto('/cart')
  await page.locator('[data-cart-item="2"]').getByRole('button', { name: 'Remove item' }).click()
  await expect(page.getByRole('alert')).toContainText('Could not remove this item. Please try again.')
  await expect(page.getByRole('alert')).not.toContainText(/SQLSTATE|TypeError/)
  await expect(page.locator('[data-cart-item]')).toHaveCount(3)
})
