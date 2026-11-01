import { expect, test, type Page } from '@playwright/test'
import type { CodPayment } from '../../src/api/cod'

const shop = { id: 1, name: 'Cash Shop', slug: 'cash-shop', status: 'active', logo: null }
const seller = { id: 7, name: 'Cash Manager', email: 'seller@example.test', role: 'customer', managed_shops: [{ ...shop, role: 'manager' }] }
const meta = { current_page: 1, last_page: 1, per_page: 15, total: 1 }
async function openTracking(page: Page) {
  await Promise.all([
    page.waitForResponse(response => new URL(response.url()).pathname === '/api/orders/SV-COD-44' && response.request().method() === 'GET'),
    page.goto('/order/tracking/SV-COD-44')
  ])
}
async function setup(page: Page, options: { collected?: boolean; eligible?: boolean; admin?: boolean } = {}) {
  const actor = { ...seller, role: options.admin ? 'super_admin' : 'customer' }
  await page.addInitScript(user => {
    localStorage.setItem('app_locale', 'en'); localStorage.setItem('access_token', 'cod-test'); localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, actor)
  const state = { collected: options.collected ?? false, eligible: options.eligible ?? true, posts: 0, error: '', status: 422, hold: null as Promise<void> | null, returned: false }
  const payment = (): CodPayment => ({ id: 11, payment_id: 3, method: 'cod', order_number: 'SV-COD-44', shop_order_number: 'SV-COD-44-1', shop,
    amount: 44, currency: 'USD', status: state.collected ? 'paid' : 'unpaid', can_collect: !state.collected && state.eligible,
    collected_by: state.collected ? actor.name : null, collected_at: state.collected ? '2026-11-02T08:00:00Z' : null })
  const parent = () => ({ id: 3, order_id: 2, order_number: 'SV-COD-44', customer_name: 'Buyer', method: 'cod', currency: 'USD', status: state.collected ? 'completed' : 'pending', order_payment_status: state.collected ? 'paid' : 'unpaid', amount: 44, transaction_id: null,
    paid_at: state.collected ? '2026-11-02T08:00:00Z' : null, created_at: '2026-11-01T08:00:00Z', cod_allocations: [payment()], transactions: state.collected ? [{ id: 1, type: 'collection', status: 'success', amount: 44, reference: 'COD-3-11', recorded_by: actor.name, created_at: '2026-11-02T08:00:00Z' }] : [] })
  const order = () => ({ order_number: 'SV-COD-44', status: 'delivered', payment_status: state.collected ? 'paid' : 'unpaid', subtotal: 40, discount_amount: 0, tax_amount: 4, shipping_amount: 0, total: 44, currency: 'USD', placed_at: '2026-11-01T08:00:00Z', customer_name: 'Buyer', payment: parent(),
    shop_orders: [{ id: 11, shop, shop_order_number: 'SV-COD-44-1', status: 'delivered', payment_status: state.collected ? 'paid' : 'unpaid', total: 44, currency: 'USD', items: [], allowed_transitions: [], tracking_events: [], shipment: { status: 'delivered', delivered_at: '2026-11-02T07:00:00Z' } }],
    items: [{ id: 9, product_id: 1, product_variant_id: 1, product_name: 'Cash item', quantity: 1, unit_price: 40, line_total: 40, image_path: null, variant_label: null }], shipment: null, tracking_events: [] })
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url()); const path = url.pathname; const method = route.request().method()
    if (!path.startsWith('/api/')) return route.continue()
    let json: unknown = { data: [] }
    if (path === '/api/auth/me') json = { data: actor }
    else if (path === '/api/seller/shops') json = { data: [shop] }
    else if (path === '/api/cart') json = { data: { id: 1, items: [], totals: { items_count: 0, subtotal: 0, discount_amount: 0, tax_amount: 0, total: 0 } } }
    else if (path.endsWith('/collect-cod')) {
      state.posts++
      expect(route.request().postData()).toBeNull()
      if (state.hold) await state.hold
      if (state.error) return route.fulfill({ status: state.status, json: { message: state.error, errors: { payment: [state.error] } } })
      state.collected = true
      json = { data: path.startsWith('/api/admin/') ? parent() : payment() }
    } else if (path === '/api/seller/shops/1/payments') json = url.searchParams.get('method') === 'cod' ? { data: [payment()], meta } : { data: [{ id: 8, order_number: 'SV-ONLINE', method: 'bakong', amount: 44, currency: 'USD', status: 'paid', refunded_amount: 0 }], meta }
    else if (path === '/api/admin/payments') json = { data: [parent()], meta }
    else if (path === '/api/admin/payments/3') json = { data: parent() }
    else if (path === '/api/orders/SV-COD-44') json = { data: order() }
    else if (path === '/api/returns') {
      if (method === 'POST') {
        if (!state.collected) return route.fulfill({ status: 422, json: { errors: { order_item_id: ['The payment must be settled before requesting a refund.'] } } })
        state.returned = true
      }
      json = { data: state.returned ? [{ id: 1, product_name: 'Cash item', order_number: 'SV-COD-44', quantity: 1, status: 'requested', reason: 'The item arrived damaged.', refund_amount: 44, currency: 'USD', photo_count: 0, shop_name: shop.name }] : [], meta }
    }
    await route.fulfill({ json })
  })
  return state
}

test('seller confirms actual cash, reloads paid history, and customer can request a return', async ({ page }, testInfo) => {
  const state = await setup(page)
  await page.goto('/seller/shops/1/payments')
  await expect(page.getByText('Awaiting collection', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Record cash collected' }).click()
  const dialog = page.getByRole('dialog', { name: 'Record COD payment?' })
  await expect(dialog).toContainText('$44.00')
  await expect(dialog).toContainText('cash was actually received')
  await dialog.getByRole('button', { name: 'Cancel', exact: true }).click()
  expect(state.posts).toBe(0)
  await page.getByRole('button', { name: 'Record cash collected' }).click()
  await dialog.getByRole('button', { name: 'Confirm collection', exact: true }).click()
  await expect(page.locator('[data-cod-status]')).toHaveText('Collected · Paid')
  await expect(page.getByText(/Collected by Cash Manager/)).toBeVisible()
  await page.reload()
  await expect(page.locator('[data-cod-status]')).toHaveText('Collected · Paid')
  await expect(page.getByRole('button', { name: 'Record cash collected' })).toHaveCount(0)
  expect(state.posts).toBe(1)
  await page.screenshot({ path: testInfo.outputPath('cod-collected.png'), fullPage: true })
  await openTracking(page)
  await expect(page.locator('[data-customer-cod]')).toContainText('Cash on Delivery · Paid')
  await page.goto('/account/returns?order=SV-COD-44')
  await page.getByLabel('Reason for the return', { exact: true }).fill('The item arrived damaged.')
  await page.getByRole('button', { name: 'Request return', exact: true }).click()
  await expect.poll(() => state.returned).toBe(true)
  await expect(page.getByText('Your return request has been submitted.')).toBeVisible()
})

test('collection loading prevents repeated submissions', async ({ page }) => {
  const state = await setup(page)
  let release!: () => void
  state.hold = new Promise<void>(resolve => { release = resolve })
  await page.goto('/seller/shops/1/payments')
  await page.getByRole('button', { name: 'Record cash collected' }).click()
  await page.getByRole('button', { name: 'Confirm collection', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Recording collection…' })).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Cancel', exact: true })).toBeDisabled()
  expect(state.posts).toBe(1)
  release()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  expect(state.posts).toBe(1)
})

test('paid COD and online payments have no collection action', async ({ page }) => {
  await setup(page, { collected: true })
  await page.goto('/seller/shops/1/payments')
  await expect(page.locator('[data-cod-status]')).toHaveText('Collected · Paid')
  await expect(page.getByRole('button', { name: 'Record cash collected' })).toHaveCount(0)
  await page.getByRole('button', { name: 'Bakong', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'SV-ONLINE' })).toBeVisible()
  await expect(page.locator('[data-cod-payment]')).toHaveCount(0)
})

test('unfulfilled COD shows delivery requirement without a financial action', async ({ page }) => {
  await setup(page, { eligible: false })
  await page.goto('/seller/shops/1/payments')
  await expect(page.getByText("Collection is available after this shop's order is delivered.")).toBeVisible()
  await expect(page.getByRole('button', { name: 'Record cash collected' })).toHaveCount(0)
})

for (const serverFailure of [false, true]) {
  test(`collection ${serverFailure ? 'unexpected error is safe' : 'business error is actionable'} and retry succeeds`, async ({ page }) => {
    const state = await setup(page)
    state.status = serverFailure ? 500 : 422
    state.error = serverFailure ? 'SQLSTATE secret stack trace' : 'Cash can only be collected for a delivered, unpaid COD shop order.'
    await page.goto('/seller/shops/1/payments')
    await page.getByRole('button', { name: 'Record cash collected' }).click()
    await page.getByRole('button', { name: 'Confirm collection', exact: true }).click()
    await expect(page.getByRole('alert')).toContainText(serverFailure ? 'Collection could not be confirmed' : 'Cash can only be collected')
    await expect(page.getByRole('alert')).not.toContainText('SQLSTATE')
    state.error = ''
    await page.getByRole('button', { name: 'Confirm collection', exact: true }).click()
    await expect(page.locator('[data-cod-status]')).toHaveText('Collected · Paid')
  })
}

test('super admin records an audited shop COD collection', async ({ page }) => {
  const state = await setup(page, { admin: true })
  await page.goto('/admin/payments')
  const row = page.locator('tbody tr').filter({ hasText: 'SV-COD-44' })
  await row.getByRole('button').last().click()
  await page.getByRole('button', { name: 'View details', exact: true }).click()
  await page.getByRole('button', { name: 'Record cash collected' }).click()
  await page.getByRole('button', { name: 'Confirm collection', exact: true }).click()
  await expect(page.locator('[data-cod-status]')).toHaveText('Collected · Paid')
  await expect(page.getByText(/Collected by Cash Manager/)).toBeVisible()
  await expect(page.locator('td').filter({ hasText: /^collection$/ })).toBeVisible()
  expect(state.posts).toBe(1)
})

test('customer sees pay on delivery until collection and cannot return unpaid goods', async ({ page }) => {
  await setup(page)
  await openTracking(page)
  await expect(page.locator('[data-customer-cod]')).toContainText('Pay on delivery')
  await page.goto('/account/returns?order=SV-COD-44')
  await page.getByLabel('Reason for the return', { exact: true }).fill('The item arrived damaged.')
  await page.getByRole('button', { name: 'Request return', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('The payment must be settled')
})
