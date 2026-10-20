import { expect, test, type Page } from '@playwright/test'
import en from '../../src/locales/en.json' with { type: 'json' }
import km from '../../src/locales/km.json' with { type: 'json' }

const user = { id: 1, name: 'Sophea Chan', email: 'sophea@example.test', phone: '012345678', role: 'customer', email_verified: true, created_at: '2026-01-01' }
const meta = { current_page: 1, last_page: 1, per_page: 15, total: 1 }
const product = { id: 2, name: 'Linen Overshirt', slug: 'linen-overshirt', short_description: 'Everyday linen', price: 44, compare_at_price: null, rating_avg: 4.5, rating_count: 8, is_featured: false, is_active: true, in_stock: true, cover_image: null, variants: [{ id: 3, sku: 'LINEN-BLUE', name: 'Blue / M', price: 44, in_stock: true, attributes: [] }], brand: null, category: null }
const address = { id: 1, label: 'Home', full_name: 'Sophea Chan', phone: '012345678', address_line1: '123 Riverside Road', address_line2: null, city: 'Phnom Penh', state: 'Phnom Penh', postal_code: '12000', country: 'KH', is_default: true }
const order = { order_number: 'ORDER-001', status: 'delivered', payment_status: 'paid', total: 44, placed_at: '2026-10-18T12:00:00Z', items_count: 1 }
const detail = { ...order, subtotal: 40, discount_amount: 0, tax_amount: 0, shipping_amount: 4, currency: 'USD', shipping_address: address, billing_address: null, email: user.email, phone: user.phone, customer_name: user.name, note: null, coupon_code: null, items: [{ id: 1, product_id: 2, product_variant_id: 3, product_name: product.name, variant_label: 'Blue / M', sku: 'LINEN-BLUE', image_path: null, unit_price: 40, quantity: 1, line_total: 40 }], payment: { id: 1, method: 'bakong', status: 'paid', amount: 44, paid_at: order.placed_at }, shipment: null, shop_orders: [] }
const review = { id: 1, rating: 4, title: 'Comfortable shirt', body: 'Lovely fabric and fit.', status: 'approved', created_at: order.placed_at, product: { id: 2, name: product.name, slug: product.slug } }

async function setup(page: Page, locale = 'en', dark = false) {
  await page.addInitScript(({ user, locale, dark }) => {
    localStorage.setItem('access_token', 'account-test-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
    localStorage.setItem('app_locale', locale)
    localStorage.setItem('ekhmer_theme', dark ? 'dark' : 'light')
  }, { user, locale, dark })
  const state = { products: [product], addresses: [address], notifications: [{ id: '1', type: 'order', title: 'Your order has arrived', message: 'Thank you for shopping with E-KHMER.', created_at: order.placed_at, read_at: null as string | null }], review: { ...review }, reviewDeleted: false, returnCreated: false }
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url())
    const path = url.pathname
    if (!path.startsWith('/api/')) return route.continue()
    const method = route.request().method()
    let json: unknown = { data: [] }
    if (path === '/api/auth/me') json = { data: user }
    else if (path === '/api/account/dashboard') json = { user, orders_count: 17, reviews_count: 1, wishlist_count: state.products.length }
    else if (path === '/api/cart' || path === '/api/cart/items') json = { data: { id: 1, items: [], totals: { subtotal: 0, total: 0, items_count: 0 } } }
    else if (path === '/api/orders') json = { data: [{ ...order, order_number: url.searchParams.get('page') === '2' ? 'ORDER-002' : order.order_number }], meta: { ...meta, current_page: Number(url.searchParams.get('page') ?? 1), last_page: url.searchParams.has('status') ? 1 : 2, total: url.searchParams.has('status') ? 1 : 17 } }
    else if (path === '/api/orders/ORDER-001') json = { data: detail }
    else if (path === '/api/wishlist') json = { data: state.products }
    else if (path === '/api/wishlist/2') { state.products = []; json = { data: [] } }
    else if (path === '/api/addresses') {
      if (method === 'POST') state.addresses.push({ ...address, id: 2, ...route.request().postDataJSON() })
      json = { data: method === 'POST' ? state.addresses.at(-1) : state.addresses }
    } else if (path === '/api/addresses/1') {
      if (method === 'DELETE') state.addresses = []
      json = { data: address }
    } else if (path === '/api/account/notifications') json = { data: state.notifications }
    else if (path.includes('/notifications/') && method !== 'GET') { state.notifications.forEach(item => { item.read_at = order.placed_at }); json = { data: [] } }
    else if (path === '/api/account/reviews') json = { data: state.reviewDeleted ? [] : [state.review] }
    else if (path === '/api/reviews/1') {
      if (method === 'DELETE') state.reviewDeleted = true
      else state.review = { ...state.review, ...route.request().postDataJSON() }
      json = { data: state.review }
    } else if (path === '/api/account/profile') json = { ...user, ...route.request().postDataJSON() }
    else if (path === '/api/account/password') json = { data: { message: 'Password changed.' } }
    else if (path === '/api/returns') {
      if (method === 'POST') state.returnCreated = true
      json = { data: [], meta: { ...meta, total: 0 } }
    } else if (path === '/api/support') json = { data: [{ id: 1, email: user.email, subject: 'Delivery question', message: 'When will the next delivery arrive?', status: 'answered', reply: 'Your package will arrive tomorrow.' }], meta }
    await route.fulfill({ json })
  })
  return state
}

for (const locale of ['en', 'km']) {
  test(`all account features have one localized heading and fit the viewport (${locale})`, async ({ page }, testInfo) => {
    await setup(page, locale, locale === 'km')
    const messages = locale === 'en' ? en : km
    const pages = [
      ['/account', messages.nav.dashboard], ['/account/orders', messages.nav.my_orders], ['/account/orders/ORDER-001', messages.account.ui.order_details],
      ['/account/wishlist', messages.nav.wishlist], ['/account/addresses', messages.account.my_addresses], ['/account/addresses/new', messages.account.add_address],
      ['/account/addresses/1/edit', messages.account.edit_address], ['/account/profile', messages.nav.profile], ['/account/password', messages.account.change_password],
      ['/account/notifications', messages.nav.notifications], ['/account/reviews', messages.nav.reviews], ['/account/returns', messages.returns.title], ['/account/support', messages.support.inbox]
    ]
    const errors: string[] = []
    page.on('pageerror', error => errors.push(error.message))
    page.on('console', message => { if (message.text().includes('[intlify] Not found')) errors.push(message.text()) })
    for (const [path, title] of pages) {
      await page.goto(path!)
      await expect(page.locator('main h1')).toHaveCount(1)
      await expect(page.locator('main h1')).toHaveText(title!)
      await expect(page.getByText(messages.common.loading, { exact: true })).toHaveCount(0)
      await expect(page.locator('main')).not.toContainText('account.ui.')
      expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth), path).toBeLessThanOrEqual(1)
      if (['/account', '/account/orders', '/account/profile'].includes(path!)) await page.screenshot({ path: 'test-results/account-' + (path === '/account' ? 'dashboard' : path!.split('/').at(-1)) + '-' + locale + '-' + testInfo.project.name + '.png', fullPage: true })
    }
    expect(errors).toEqual([])
  })
}

test('orders filter and pagination request server data and recover from errors', async ({ page }) => {
  await setup(page)
  await page.goto('/account/orders')
  await page.getByRole('button', { name: 'Next page' }).click()
  await expect(page.getByRole('link', { name: 'ORDER-002', exact: true }).filter({ visible: true })).toBeVisible()
  const filtered = page.waitForRequest(request => request.url().includes('/api/orders?') && request.url().includes('status=delivered') && request.url().includes('page=1'))
  await page.getByLabel('Status', { exact: true }).selectOption('delivered')
  await filtered
  await expect(page.getByText('1 order', { exact: true })).toBeVisible()
  await page.route('**/api/orders?*', route => route.fulfill({ status: 500, json: { message: 'Unavailable' } }), { times: 1 })
  await page.getByLabel('Status', { exact: true }).selectOption('pending')
  await expect(page.getByRole('alert')).toContainText("We couldn't load")
  await page.getByRole('button', { name: 'Retry' }).click()
  await expect(page.getByText('1 order', { exact: true })).toBeVisible()
})

test('notifications filter, mark-as-read action, and sidebar count stay consistent', async ({ page }) => {
  await setup(page)
  await page.goto('/account/notifications')
  await page.getByRole('button', { name: /^Unread/ }).click()
  await page.getByRole('button', { name: 'Mark all as read' }).click()
  await expect(page.getByText("You're all caught up.", { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'All notifications' }).click()
  await expect(page.getByText('Your order has arrived')).toBeVisible()
  await expect(page.locator('aside nav a[href="/account/notifications"] span').last()).toHaveText('Notifications')
})

test('address deletion requires confirmation and empty state provides the next step', async ({ page }) => {
  await setup(page)
  await page.goto('/account/addresses')
  await page.getByRole('button', { name: 'Delete', exact: true }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.getByRole('button', { name: 'Cancel', exact: true }).click()
  await expect(page.getByText('123 Riverside Road')).toBeVisible()
  await page.getByRole('button', { name: 'Delete', exact: true }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'No addresses saved' })).toBeVisible()
  await page.getByRole('button', { name: /Add New Address/i }).click()
  await expect(page).toHaveURL(/\/account\/addresses\/new$/)
})

test('review editing and deletion keep the moderation and confirmation flows', async ({ page }) => {
  await setup(page)
  await page.goto('/account/reviews')
  await page.getByRole('button', { name: 'Edit', exact: true }).click()
  await page.getByLabel('Review title (optional)').fill('Updated review')
  await page.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(page.getByText('Updated review', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Delete', exact: true }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'No reviews yet' })).toBeVisible()
})

test('profile and password forms expose save feedback and accessible password visibility', async ({ page }) => {
  await setup(page)
  await page.goto('/account/profile')
  await page.route('**/api/account/profile', route => route.fulfill({ status: 422, json: { message: 'Invalid' } }), { times: 1 })
  await page.getByRole('button', { name: /Save Changes/i }).click()
  await expect(page.getByRole('alert')).toContainText("couldn't be saved")
  await page.getByRole('button', { name: /Save Changes/i }).click()
  await expect(page.getByRole('status')).toContainText('Changes saved')
  await page.goto('/account/password')
  await page.getByLabel('Current password', { exact: true }).fill('Old-password123')
  await page.getByLabel('New password', { exact: true }).fill('New-password123!')
  await page.getByRole('button', { name: 'Show password', exact: true }).first().click()
  await expect(page.getByLabel('New password', { exact: true })).toHaveAttribute('type', 'text')
  await page.getByLabel('Confirm new password', { exact: true }).fill('New-password123!')
  await page.getByRole('button', { name: 'Update Password', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Password changed successfully')
})

test('wishlist hydrates from the server and retains the product when cart creation fails', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/account/wishlist')
  await expect(page.getByRole('link', { name: product.name, exact: true })).toBeVisible()
  await page.route('**/api/cart', route => route.request().method() === 'POST' ? route.fulfill({ status: 422, json: { message: 'Out of stock' } }) : route.fallback())
  await page.getByRole('button', { name: /Add to cart/i }).click()
  await expect(page.getByRole('alert')).toBeVisible()
  expect(state.products).toHaveLength(1)
})

test('return request lookup and submission keep evidence and order selection usable', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/account/returns?order=ORDER-001')
  await expect(page.getByLabel('Item to return')).toBeVisible()
  await page.getByLabel('Reason for the return').fill('The size does not fit as expected.')
  await page.getByRole('button', { name: 'Request return', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Your return request has been submitted')
  expect(state.returnCreated).toBe(true)
})

test('mobile account menu traps focus, closes on Escape, and closes after navigation', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile')
  await setup(page)
  await page.goto('/account/orders')
  const toggle = page.getByRole('button', { name: 'Open account menu' })
  await toggle.click()
  await expect(page.getByRole('dialog', { name: 'My Account' })).toBeVisible()
  await page.getByRole('dialog', { name: 'My Account' }).getByRole('button', { name: 'Sign out' }).focus()
  await page.keyboard.press('Tab')
  await expect(page.getByRole('dialog', { name: 'My Account' }).getByRole('link', { name: 'E-KHMER My Account' })).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(page.getByRole('dialog', { name: 'My Account' }).getByRole('button', { name: 'Sign out' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog', { name: 'My Account' })).not.toBeVisible()
  await expect(toggle).toBeFocused()
  await toggle.click()
  await page.getByRole('dialog', { name: 'My Account' }).getByRole('link', { name: 'My Orders', exact: false }).click()
  // Navigating to the current page still closes the menu.
  await expect(page.getByRole('dialog', { name: 'My Account' })).not.toBeVisible()
  expect(await page.evaluate(() => document.body.style.overflow)).toBe('')
})

test('account requests display a retry state instead of a false empty state', async ({ page }) => {
  await setup(page)
  for (const [path, endpoint] of [
    ['/account/addresses', '/addresses'], ['/account/addresses/1/edit', '/addresses'],
    ['/account/orders/ORDER-001', '/orders/ORDER-001'], ['/account/wishlist', '/wishlist'],
    ['/account/notifications', '/account/notifications'], ['/account/reviews', '/account/reviews'], ['/account/support', '/support']
  ]) {
    const matchesEndpoint = (url: URL) => url.pathname === '/api' + endpoint
    await page.route(matchesEndpoint, route => route.fulfill({ status: 500, json: { message: 'Unavailable' } }))
    await page.goto(path!)
    await expect(page.getByRole('alert')).toBeVisible()
    await page.unroute(matchesEndpoint)
    await page.getByRole('button', { name: 'Retry', exact: true }).click()
    await expect(page.getByRole('alert')).toHaveCount(0)
    await expect(page.getByText(en.common.loading, { exact: true })).toHaveCount(0)
  }
})

test('new address form preserves input after a failed save and submits recipient details', async ({ page }) => {
  await setup(page)
  await page.goto('/account/addresses/new')
  await page.locator('#addr-full-name').fill('Sophea Chan')
  await page.locator('#addr-line1').fill('123 Riverside Road')
  await page.locator('#addr-city').fill('Phnom Penh')
  await page.locator('#addr-state').fill('Phnom Penh')
  await page.locator('#addr-postal').fill('12000')
  await page.route('**/api/addresses', route => route.fulfill({ status: 422, json: { message: 'Invalid' } }), { times: 1 })
  await page.getByRole('button', { name: 'Add Address', exact: true }).click()
  await expect(page.getByRole('alert')).toBeVisible()
  await expect(page.locator('#addr-line1')).toHaveValue('123 Riverside Road')
  const saved = page.waitForRequest(request => request.url().endsWith('/api/addresses') && request.method() === 'POST')
  await page.getByRole('button', { name: 'Add Address', exact: true }).click()
  expect((await saved).postDataJSON().address_line1).toBe('123 Riverside Road')
  await expect(page).toHaveURL(/\/account\/addresses$/)
})
