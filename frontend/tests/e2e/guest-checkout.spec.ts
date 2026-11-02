import { expect, test, type Page } from '@playwright/test'
import type { ApiCartItem } from '../../src/api/cart'
import type { ApiOrder, CheckoutPayload, QuotePayload } from '../../src/api/checkout'
import { quoteFixture, type QuoteDelivery } from './quote-fixture'

const buyer = { id: 10, name: 'Account Buyer', email: 'account@example.test', role: 'customer', managed_shops: [] }
const contact = { email: 'guest@example.test', full_name: 'Guest Buyer', phone: '+85512345678', address_line1: 'Street 1', city: 'Phnom Penh', state: 'Phnom Penh', postal_code: '12000', country: 'KH' }
function item(id: number): ApiCartItem {
  return { id, quantity: 1, available: true, unavailable_reason: null, available_quantity: 10,
    variant: { id, price: 100, name: 'Default', sku: `ITEM-${id}`, in_stock: true, compare_at_price: null, attributes: [],
      product: { id, name: `Item ${id}`, slug: `item-${id}`, cover_image: null, shop_id: id, shop_name: `Shop ${id}` } } }
}

async function setup(page: Page, options: { authenticated?: boolean; empty?: boolean } = {}) {
  await page.addInitScript(({ authenticated, buyer }) => {
    localStorage.setItem('app_locale', 'en')
    if (authenticated) {
      localStorage.setItem('access_token', 'guest-flow-test')
      localStorage.setItem('ekhmer_user', JSON.stringify(buyer))
    }
  }, { authenticated: options.authenticated, buyer })
  const state = {
    items: options.empty ? [] as ApiCartItem[] : [item(1), item(2)],
    addressCalls: 0, authenticated: Boolean(options.authenticated),
    payloads: [] as CheckoutPayload[], keys: [] as (string | undefined)[], sessions: [] as (string | undefined)[],
    order: null as ApiOrder | null, errorFields: null as Record<string, string[]> | null,
    failSave: false, failShipping: false, holdOrder: false,
    checkoutFailure: null as { status: number; code: string; message: string; retryAfter?: string } | null,
    reservationExpiresAt: undefined as string | undefined,
    qrReads: 0,
    releaseOrder: (() => {}) as () => void,
  }
  const deliveries: QuoteDelivery[] = [
    { id: 11, shop_id: 1, name: 'Standard 1', price: 5 },
    { id: 12, shop_id: 1, name: 'Express 1', price: 9 },
    { id: 21, shop_id: 2, name: 'Standard 2', price: 7 },
  ]
  function quote(payload: QuotePayload = {}) {
    return quoteFixture(state.items, {
      coupon: payload.coupon_code ? { code: 'GUESTCAP', shop_id: 1, type: 'percentage', value: 50, max_discount_amount: 5 } : undefined,
      delivery: deliveries, selections: payload.shipping_methods,
    }).quote
  }
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    const method = route.request().method()
    let json: unknown = { data: [] }
    if (path === '/api/auth/me') json = { data: buyer }
    else if (path === '/api/auth/login' || path === '/api/auth/register') {
      state.authenticated = true
      json = { data: { token: 'guest-flow-test', user: buyer } }
    } else if (path === '/api/catalog/products' || path === '/api/catalog/featured') {
      json = { data: [1, 2].map(id => ({ id, slug: `item-${id}`, name: `Item ${id}`, price: 100, in_stock: true, is_active: true, rating_avg: 4, rating_count: 1, variants: [{ id, price: 100, in_stock: true, name: 'Default', sku: `ITEM-${id}`, attributes: [] }], brand: null, category: null, cover_image: null })), meta: { current_page: 1, last_page: 1, per_page: 12, total: 2 } }
    } else if (path === '/api/catalog/facets') json = { data: { brands: [], categories: [], colors: [], sizes: [] } }
    else if (path === '/api/cart') {
      if (method === 'POST') {
        const id = route.request().postDataJSON().product_variant_id as number
        if (!state.items.some(row => row.id === id)) state.items.push(item(id))
      }
      if (method === 'DELETE') state.items = []
      json = { data: { id: 1, items: state.items, totals: { subtotal: state.items.length * 100, tax_amount: state.items.length * 10, discount_amount: 0, total: state.items.length * 110, items_count: state.items.length } } }
    } else if (path === '/api/checkout/quote') json = { data: quote(route.request().postDataJSON() as QuotePayload) }
    else if (path === '/api/addresses') {
      state.addressCalls++
      if (!state.authenticated) return route.fulfill({ status: 401, json: { message: 'Unauthenticated' } })
      if (method === 'POST' && state.failSave) return route.fulfill({ status: 422, json: { errors: { state: ['The state field is required.'] } } })
      json = { data: [{ id: 90, ...contact, full_name: buyer.name, is_default: true }] }
    } else if (path === '/api/shipping-methods') {
      if (state.failShipping) return route.fulfill({ status: 503, json: { message: 'Delivery is temporarily unavailable.' } })
      json = { data: deliveries }
    } else if (path === '/api/checkout') {
      const payload = route.request().postDataJSON() as CheckoutPayload
      state.payloads.push(payload)
      state.keys.push(route.request().headers()['idempotency-key'])
      state.sessions.push(route.request().headers()['x-session-id'])
      if (state.holdOrder) await new Promise<void>(resolve => { state.releaseOrder = resolve })
      if (state.checkoutFailure) return route.fulfill({ status: state.checkoutFailure.status,
        headers: state.checkoutFailure.retryAfter ? { 'Retry-After': state.checkoutFailure.retryAfter } : {},
        json: { code: state.checkoutFailure.code, message: state.checkoutFailure.message } })
      if (state.errorFields) return route.fulfill({ status: 422, json: { errors: state.errorFields } })
      const current = quote(payload)
      state.order = { ...current, order_number: 'GUEST-ORDER', status: 'pending', payment_status: 'unpaid', coupon_code: payload.coupon_code ?? null,
        email: payload.email ?? buyer.email, customer_name: payload.address?.full_name ?? buyer.name, phone: payload.address?.phone ?? null,
        shipping_address: { ...payload.address } as Record<string, string>, billing_address: null, note: null, placed_at: '2026-11-02T00:00:00Z',
        payment: { id: 1, method: payload.payment_method, status: 'pending', amount: current.total, transaction_id: null, paid_at: null }, shipment: null,
        items: state.items.map(row => ({ id: row.id, product_id: row.id, product_variant_id: row.id, product_name: `Item ${row.id}`, variant_label: null, sku: `ITEM-${row.id}`, image_path: null, unit_price: 100, quantity: 1, line_total: 100 })) }
      json = { data: state.order, reservation_expires_at: state.reservationExpiresAt }
    } else if (path.endsWith('/GUEST-ORDER/confirm')) {
      if (state.order) state.order.status = 'confirmed'
      state.items = []
      json = { data: state.order }
    } else if (path.endsWith('/GUEST-ORDER/bakong')) {
      state.qrReads++
      json = { data: [{ id: 1, shop_name: 'Shop 1', amount: state.order?.total, currency: 'USD', status: 'pending', qr_payload: 'TEST-KHQR', expires_at: '2099-01-01T00:00:00Z' }] }
    } else if (path.endsWith('/GUEST-ORDER/bakong/verify')) {
      if (state.order) { state.order.status = 'confirmed'; state.order.payment_status = 'paid' }
      state.items = []
      json = { data: state.order }
    } else if (path === '/api/orders/guest/GUEST-ORDER' || path === '/api/orders/GUEST-ORDER') json = { data: state.order }
    await route.fulfill({ json })
  })
  return state
}

async function fillGuest(page: Page) {
  await page.getByLabel('Email *', { exact: true }).fill(contact.email)
  for (const [field, value] of Object.entries(contact)) {
    if (field !== 'email') await page.locator(`#guest-${field}`).fill(value)
  }
}
async function review(page: Page, bakong = false) {
  await page.getByRole('button', { name: 'Continue to Delivery' }).click()
  await expect(page.getByRole('group', { name: 'Shop 1' })).toBeVisible()
  await expect(page.getByRole('group', { name: 'Shop 2' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue to Payment' }).click()
  if (bakong) await page.getByRole('main').getByText('Bakong / KHQR', { exact: true }).click()
  await page.getByRole('button', { name: 'Review Order', exact: true }).click()
}

for (const failure of [
  { status: 429, code: 'CHECKOUT_RATE_LIMIT', message: 'Please wait a moment.', retryAfter: '42', expected: 'Please wait 42 seconds before placing another order. Your cart has been kept.' },
  { status: 409, code: 'ACTIVE_CHECKOUT_LIMIT', message: 'Existing checkout limit.', expected: 'Please complete or cancel an existing checkout before placing another order. Your cart has been kept.' },
]) {
  test(`F13 ${failure.code} preserves the cart, restores the submit button and retries the same key`, async ({ page }) => {
    const state = await setup(page)
    await page.goto('/checkout')
    await fillGuest(page)
    await review(page)
    state.checkoutFailure = failure
    state.holdOrder = true
    await page.getByRole('button', { name: /Place Order ·/ }).click()
    await expect(page.getByRole('button', { name: 'Placing order…' })).toBeDisabled()
    await expect.poll(() => state.payloads.length).toBe(1)
    state.releaseOrder()
    await expect(page.getByRole('alert')).toContainText(failure.expected)
    await expect(page.getByRole('button', { name: /Place Order ·/ })).toBeEnabled()
    expect(state.items).toHaveLength(2)
    expect(state.order).toBeNull()
    state.holdOrder = false
    state.checkoutFailure = null
    await page.getByRole('button', { name: /Place Order ·/ }).click()
    await expect(page).toHaveURL(/\/order\/success\/GUEST-ORDER$/)
    expect(state.payloads).toHaveLength(2)
    expect(state.keys[0]).toBe(state.keys[1])
  })
}

test('F13 refresh after a blocked attempt does not submit or create a reservation', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/checkout')
  await fillGuest(page)
  await review(page)
  state.checkoutFailure = { status: 409, code: 'ACTIVE_CHECKOUT_LIMIT', message: 'Existing checkout limit.' }
  await page.getByRole('button', { name: /Place Order ·/ }).click()
  await expect(page.getByRole('alert')).toContainText('complete or cancel an existing checkout')
  await page.reload()
  await expect(page.locator('#guest-email')).toBeVisible()
  expect(state.payloads).toHaveLength(1)
  expect(state.items).toHaveLength(2)
  expect(state.order).toBeNull()
})

test('F13 an expired retry clears its key before the next intentional submission', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/checkout')
  await fillGuest(page)
  await review(page)
  state.reservationExpiresAt = '2000-01-01T00:00:00Z'
  await page.getByRole('button', { name: /Place Order ·/ }).click()
  await expect(page.getByRole('alert')).toContainText(/expired/i)
  expect(state.items).toHaveLength(2)
  state.reservationExpiresAt = undefined
  await page.getByRole('button', { name: /Place Order ·/ }).click()
  await expect(page).toHaveURL(/\/order\/success\/GUEST-ORDER$/)
  expect(state.keys[0]).not.toBe(state.keys[1])
})

test('F13 reopening and refreshing a pending payment reads its QR without another checkout', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/checkout')
  await fillGuest(page)
  await review(page, true)
  await page.getByRole('button', { name: /Place Order ·/ }).click()
  await expect(page).toHaveURL(/\/checkout\/GUEST-ORDER\/payment$/)
  await expect(page.locator('main img')).toBeVisible()
  await page.reload()
  await expect(page.locator('main img')).toBeVisible()
  await page.goto('/checkout/GUEST-ORDER/payment')
  await expect(page.locator('main img')).toBeVisible()
  expect(state.payloads).toHaveLength(1)
  expect(state.qrReads).toBe(3)
})

test('guest browses, adds two shops, applies a capped coupon and completes COD without addresses API', async ({ page }) => {
  const state = await setup(page, { empty: true })
  await page.goto('/shop')
  await page.getByRole('button', { name: 'Add to Cart', exact: true }).first().click()
  await expect.poll(() => state.items.length).toBe(1)
  await page.getByRole('button', { name: 'Add to Cart', exact: true }).nth(1).click()
  await expect.poll(() => state.items.length).toBe(2)
  await page.goto('/cart')
  await page.getByPlaceholder('Coupon Code').fill('GUESTCAP')
  await page.getByRole('button', { name: 'Apply Coupon', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Remove coupon', exact: true })).toBeVisible()
  await page.getByRole('main').getByRole('link', { name: 'Proceed to Checkout' }).click()
  await expect(page).toHaveURL(/\/checkout$/)
  await fillGuest(page)
  await review(page)
  await expect(page.locator('main [data-amount="discount"]')).toContainText('$5.00')
  await page.getByRole('button', { name: 'Place Order · $226.50' }).click()
  await expect(page).toHaveURL(/\/order\/success\/GUEST-ORDER$/)
  await expect(page.getByText('Contact email: guest@example.test')).toBeVisible()
  await expect(page.getByRole('button', { name: /Receipt/ })).toHaveCount(0)
  expect(state.addressCalls).toBe(0)
  expect(state.payloads[0]?.address_id).toBeUndefined()
  expect(state.payloads[0]?.address?.country).toBe('KH')
  expect(state.payloads[0]?.shipping_methods).toEqual({ 1: 11, 2: 21 })
  expect(state.order?.payment_status).toBe('unpaid')
  expect(state.keys[0]).toBeTruthy()
  expect(state.sessions[0]).toBeTruthy()
  const storage = await page.evaluate(() => JSON.stringify([localStorage, sessionStorage]))
  expect(storage).not.toContain(contact.email)
  expect(storage).not.toContain(contact.phone)
  expect(storage).not.toContain(contact.address_line1)
  await page.getByRole('link', { name: 'Track My Order', exact: true }).click()
  await expect(page).toHaveURL(/\/order\/tracking\/GUEST-ORDER$/)
})

test('required guest fields block progression with visible validation', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/checkout')
  await page.getByRole('button', { name: 'Continue to Delivery' }).click()
  await expect(page.getByText('Please enter a valid email address.', { exact: true })).toBeVisible()
  await expect(page.getByText('Phone number is required.', { exact: true })).toBeVisible()
  await expect(page.getByText('Address line 1 is required.', { exact: true })).toBeVisible()
  expect(state.addressCalls).toBe(0)
  expect(state.payloads).toHaveLength(0)
})

test('server contact errors return to editable fields and preserve cart data', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/checkout')
  await fillGuest(page)
  await review(page)
  state.errorFields = { email: ['Please enter a deliverable email address.'], 'address.phone': ['Please check your delivery phone.'] }
  await page.getByRole('button', { name: /Place Order ·/ }).click()
  await expect(page.locator('#guest-email-error')).toContainText('Please enter a deliverable email address.')
  await expect(page.locator('#guest-phone-error')).toContainText('Please check your delivery phone.')
  await expect(page.locator('#guest-address_line1')).toHaveValue(contact.address_line1)
  expect(state.items).toHaveLength(2)
})

test('duplicate clicks are disabled and a failed submission retries with the same key', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/checkout')
  await fillGuest(page)
  await review(page)
  state.holdOrder = true
  state.errorFields = { payment: ['Payment unavailable. Try again.'] }
  await page.getByRole('button', { name: /Place Order ·/ }).click()
  await expect(page.getByRole('button', { name: 'Placing order…' })).toBeDisabled()
  await expect.poll(() => state.payloads.length).toBe(1)
  state.releaseOrder()
  await expect(page.getByRole('alert')).toContainText('Payment unavailable. Try again.')
  state.holdOrder = false
  state.errorFields = null
  await page.getByRole('button', { name: /Place Order ·/ }).click()
  await expect(page).toHaveURL(/\/order\/success\/GUEST-ORDER$/)
  expect(state.payloads).toHaveLength(2)
  expect(state.keys[0]).toBe(state.keys[1])
})

test('guest Bakong renders KHQR and verifies without account-only navigation', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/checkout')
  await fillGuest(page)
  await review(page, true)
  await page.getByRole('button', { name: /Place Order ·/ }).click()
  await expect(page).toHaveURL(/\/checkout\/GUEST-ORDER\/payment$/)
  await expect(page.locator('main img')).toBeVisible()
  await expect(page.locator('main a[href="/account/orders"]')).toHaveCount(0)
  await page.getByRole('button', { name: /Check payment/i }).click()
  await expect(page).toHaveURL(/\/order\/success\/GUEST-ORDER$/)
  expect(state.addressCalls).toBe(0)
})

for (const mode of ['login', 'register'] as const) {
  test(`${mode} during guest checkout resumes saved addresses and preserves multi-shop cart`, async ({ page }) => {
    const state = await setup(page)
    await page.goto('/checkout')
    await page.getByRole('main').getByRole('link', { name: mode === 'login' ? 'Sign in' : 'Create Account', exact: true }).click()
    await page.locator(mode === 'login' ? '#login-email' : '#reg-email').fill(buyer.email)
    await page.locator(mode === 'login' ? '#login-password' : '#reg-password').fill('Password123!')
    if (mode === 'register') {
      await page.locator('#reg-name').fill(buyer.name)
      await page.locator('#reg-confirm').fill('Password123!')
    }
    await page.getByRole('button', { name: mode === 'login' ? 'Sign In' : 'Create your account', exact: true }).click()
    await expect(page).toHaveURL(/\/checkout$/)
    await expect(page.getByText(buyer.name, { exact: true }).last()).toBeVisible()
    await expect(page.locator('#guest-email')).toHaveCount(0)
    expect(state.addressCalls).toBeGreaterThan(0)
    expect(state.items).toHaveLength(2)
    await review(page)
    await page.getByRole('button', { name: /Place Order ·/ }).click()
    await expect(page).toHaveURL(/\/order\/success\/GUEST-ORDER$/)
    expect(state.payloads[0]?.address_id).toBe(90)
    expect(state.payloads[0]?.address).toBeUndefined()
  })
}

test('authenticated inline address errors are visible', async ({ page }) => {
  const state = await setup(page, { authenticated: true })
  state.failSave = true
  await page.goto('/checkout')
  await page.getByRole('button', { name: 'Add New Address', exact: true }).click()
  await page.getByRole('button', { name: 'Save Address', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('The state field is required.')
})

test('expired guest cart shows an empty state without identity requests or order creation', async ({ page }) => {
  const state = await setup(page, { empty: true })
  await page.goto('/checkout')
  await expect(page.getByText('Your cart is empty', { exact: true }).last()).toBeVisible()
  expect(state.addressCalls).toBe(0)
  expect(state.payloads).toHaveLength(0)
})

test('failed delivery loading shows retry and blocks progression until methods are available', async ({ page }) => {
  const state = await setup(page)
  state.failShipping = true
  await page.goto('/checkout')
  await fillGuest(page)
  await page.getByRole('button', { name: 'Continue to Delivery' }).click()
  await expect(page.getByRole('alert')).toContainText('Delivery is temporarily unavailable.')
  await expect(page.getByRole('button', { name: 'Continue to Payment' })).toBeDisabled()
  state.failShipping = false
  await page.getByRole('button', { name: 'Retry', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Continue to Payment' })).toBeEnabled()
})
