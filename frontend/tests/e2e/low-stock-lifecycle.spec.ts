import { expect, test, type Page } from '@playwright/test'
import type { ApiNotification } from '../../src/api/account'

const shop = { id: 1, name: 'Aurora Shop', slug: 'aurora', status: 'active', logo: null }
const user = { id: 1, name: 'Shop Owner', email: 'owner@example.test', role: 'customer', email_verified: true, managed_shops: [{ ...shop, role: 'owner' }] }
const meta = { current_page: 1, last_page: 1, per_page: 15, total: 1 }

async function setup(page: Page) {
  const state = { quantity: 10, notified: false, notifications: [] as ApiNotification[] }
  function inventory() {
    return { id: 1, product_variant_id: 2, shop_id: 1, product_name: 'Aurora Headphones', sku: 'AURORA-BLK-XL', variant_label: 'Black / XL',
      quantity: state.quantity, reserved_quantity: 0, available_quantity: state.quantity, sold_count: 0, low_stock_threshold: 5,
      is_low_stock: state.quantity <= 5, is_out_of_stock: state.quantity === 0, low_stock_notified_at: state.notified ? '2026-11-02T06:00:00Z' : null }
  }
  await page.addInitScript(({ user }) => {
    localStorage.setItem('access_token', 'low-stock-browser-test')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'light')
  }, { user })
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (path === '/api/auth/me') return route.fulfill({ json: { data: user } })
    if (path === '/api/seller/shops') return route.fulfill({ json: { data: [shop] } })
    if (path === '/api/account/dashboard') return route.fulfill({ json: { user, orders_count: 0, reviews_count: 0, wishlist_count: 0 } })
    if (path === '/api/cart') return route.fulfill({ json: { data: { items: [], item_count: 0, subtotal: 0 } } })
    if (path === '/api/account/notifications') return route.fulfill({ json: { data: state.notifications } })
    const read = path.match(/^\/api\/account\/notifications\/([^/]+)\/read$/)
    if (read) {
      state.notifications.forEach(item => { if (read[1] === 'all' || item.id === read[1]) item.read_at = '2026-11-02T06:01:00Z' })
      return route.fulfill({ json: { data: { message: 'Read' } } })
    }
    if (path === '/api/seller/shops/1/inventory/1/adjust') {
      state.quantity = Number(route.request().postDataJSON().quantity)
      if (state.quantity > 5) state.notified = false
      else if (!state.notified) {
        state.notified = true
        state.notifications.unshift({ id: String(state.notifications.length + 1), type: 'inventory', title: 'Low stock alert',
          message: `"Aurora Headphones (Black / XL)" at Aurora Shop is running low on stock: only ${state.quantity} left. SKU: AURORA-BLK-XL. Threshold: 5.`,
          url: '/seller/shops/1/inventory', read_at: null, created_at: '2026-11-02T06:00:00Z' })
      }
      return route.fulfill({ json: { data: inventory() } })
    }
    if (path === '/api/seller/shops/1/inventory') return route.fulfill({ json: { data: [inventory()], meta } })
    if (path === '/api/seller/shops/1/inventory/1/transactions') return route.fulfill({ json: { data: [], meta: { ...meta, total: 0 } } })
    if (path === '/api/seller/shops/1/dashboard') return route.fulfill({ json: { data: {
      shop, metrics: { products: 1, active_products: 1, inactive_products: 0, low_stock: state.quantity <= 5 ? 1 : 0, in_stock: 1, orders: 0, to_pack: 0, to_ship: 0, on_way: 0, returns: 0 },
      recent_orders: [], low_stock: state.quantity <= 5 ? [{ id: 1, name: 'Aurora Headphones', sku: 'AURORA-BLK-XL', available: state.quantity, threshold: 5 }] : []
    } } })
    return route.fulfill({ json: { data: [] } })
  })
  return state
}

async function adjust(page: Page, quantity: number) {
  await page.getByRole('button', { name: 'Update stock', exact: true }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByRole('spinbutton').fill(String(quantity))
  await dialog.getByRole('button', { name: 'Update stock', exact: true }).click()
  await expect(dialog.getByRole('status').filter({ hasText: 'Stock updated' })).toBeVisible()
  await expect(dialog.getByRole('spinbutton')).toBeEnabled()
  await page.keyboard.press('Escape')
  await expect(dialog).not.toBeVisible()
}

test('seller stock, dashboard and inbox follow low, recovered, low again', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/seller/shops/1/inventory')
  await adjust(page, 4)
  await expect(page.locator('article')).toContainText('Low stock')
  await page.goto('/seller/shops/1')
  await expect(page.locator('.dashboard-metric[data-tone="amber"] .dashboard-metric-value')).toHaveText('1')

  await page.goto('/account/notifications')
  await expect(page.getByText('Low stock alert', { exact: true })).toHaveCount(1)
  await expect(page.getByText(/only 4 left/)).toBeVisible()
  const inventoryLink = page.getByRole('link', { name: 'Stock', exact: true })
  await expect(inventoryLink).toHaveAttribute('href', '/seller/shops/1/inventory')
  await inventoryLink.click()
  await adjust(page, 10)
  await expect(page.locator('article')).not.toContainText('Low stock')
  await page.goto('/seller/shops/1')
  await expect(page.locator('.dashboard-metric[data-tone="amber"] .dashboard-metric-value')).toHaveText('0')
  await expect(page.locator('.stock-alert')).toHaveCount(0)
  await page.goto('/seller/shops/1/inventory')
  await adjust(page, 4)
  await page.goto('/account/notifications')
  await expect(page.getByText('Low stock alert', { exact: true })).toHaveCount(2)
  expect(state.notifications).toHaveLength(2)
  await page.getByRole('button', { name: /Low stock alert/ }).first().click()
  await expect(page.getByRole('button', { name: /Low stock alert/ }).first()).toBeDisabled()
  await expect(page.getByRole('link', { name: 'Stock', exact: true })).toHaveCount(2)
})

test('remaining low including zero keeps one notification and links remain usable after reading', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/seller/shops/1/inventory')
  for (const quantity of [5, 4, 0]) await adjust(page, quantity)
  expect(state.notifications).toHaveLength(1)
  await page.goto('/account/notifications')
  await page.getByRole('button', { name: /Low stock alert/ }).click()
  await expect(page.getByRole('button', { name: /Low stock alert/ })).toBeDisabled()
  await page.getByRole('link', { name: 'Stock', exact: true }).click()
  await expect(page).toHaveURL(/\/seller\/shops\/1\/inventory$/)
  await expect(page.locator('article')).toContainText('Out of stock')
})

test('inventory notifications never render external action links', async ({ page }) => {
  const state = await setup(page)
  state.notifications.push({ id: 'unsafe', type: 'inventory', title: 'Low stock alert', message: '<script>alert(1)</script>',
    url: 'https://example.test/private', read_at: null, created_at: '2026-11-02T06:00:00Z' })
  await page.goto('/account/notifications')
  await expect(page.getByText('<script>alert(1)</script>', { exact: true })).toBeVisible()
  await expect(page.locator('a[href="https://example.test/private"]')).toHaveCount(0)
})
