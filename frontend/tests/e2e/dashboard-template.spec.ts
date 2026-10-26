import { test, expect, type Page } from '@playwright/test'

const shops = [
  { id: 1, name: 'E-KHMER Official Store', slug: 'e-khmer', status: 'active', logo: null },
  { id: 2, name: 'Second shop', slug: 'second-shop', status: 'active', logo: null }
]
const user = { id: 1, name: 'Super Admin', email: 'dashboard@example.test', role: 'super_admin', managed_shops: shops.map(shop => ({ ...shop, role: 'owner' })) }
const meta = { current_page: 1, last_page: 1, per_page: 20, total: 0 }
const adminDashboard = {
  period: { from: '2026-09-27', to: '2026-10-26', revenue: 0, orders_count: 21, customers_count: 19, average_order_value: 0, revenue_delta: null, orders_delta: null, customers_delta: null },
  metrics: { total_revenue: 0, orders_count: 21, customers_count: 19, total_products: 4, total_categories: 2, total_brands: 2, pending_orders: 0, processing_orders: 0, completed_orders: 6, cancelled_orders: 2, low_stock_products: 1, out_of_stock_products: 0 },
  attention: { pending_reviews: 4, payment_exceptions: 0, open_returns: 0, open_support: 10 },
  pending_reviews: [], payment_exceptions: [], revenue_trend: [], orders_trend: [], status_distribution: [], payment_status_distribution: [], recent_orders: []
}

async function setup(page: Page) {
  await page.addInitScript(({ user }) => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'light')
    localStorage.setItem('access_token', 'dashboard-test-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, { user })
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (path === '/api/auth/me') return route.fulfill({ json: { data: user } })
    if (path === '/api/seller/shops') return route.fulfill({ json: { data: shops } })
    if (path === '/api/admin/dashboard/overview') return route.fulfill({ json: { data: adminDashboard } })
    const overview = path.match(/^\/api\/seller\/shops\/(\d+)\/dashboard$/)
    if (overview) {
      const id = Number(overview[1])
      return route.fulfill({ json: { data: {
        shop: shops[id - 1],
        metrics: { products: 4, active_products: id === 1 ? 4 : 9, inactive_products: 0, low_stock: 1, in_stock: 4, orders: 0, to_pack: 0, to_ship: 0, on_way: 0, returns: 0 },
        recent_orders: [], low_stock: [{ id: 1, name: 'Aurora Wireless Headphones', sku: 'DEMO-001-01', available: 1, threshold: 5 }]
      } } })
    }
    return route.fulfill({ json: { data: [], meta, summary: { total_products: 0, live_products: 0, hidden_products: 0, low_stock_products: 0 }, categories: [] } })
  })
}

test('admin and seller dashboards fit small screens, Khmer and dark mode', async ({ page }, testInfo) => {
  await setup(page)
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  for (const [area, path] of [['admin', '/admin/dashboard'], ['seller', '/seller/shops/1']]) {
    await page.goto(path!)
    await expect(page.locator('.dashboard-metric')).toHaveCount(4)
    if (testInfo.project.name === 'desktop') await page.setViewportSize({ width: 1918, height: 1025 })
    await page.screenshot({ path: testInfo.outputPath(`${area}-dashboard.png`), animations: 'disabled' })
    const widths = testInfo.project.name === 'desktop' ? [360, 768, 1024, 1440, 1918] : [393]
    for (const width of widths) {
      await page.setViewportSize({ width, height: 1025 })
      expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth), `${area} overflow at ${width}`).toBeLessThanOrEqual(1)
    }
    await page.getByRole('button', { name: /Switch theme/ }).click()
    await expect(page.locator('html')).toHaveClass(/dark/)
    await page.locator('.dashboard-language button').click()
    await expect(page.locator('html')).toHaveAttribute('lang', 'km')
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1)
    await page.screenshot({ path: testInfo.outputPath(`${area}-dashboard-khmer-dark.png`), animations: 'disabled' })
    await page.getByRole('button', { name: /Switch theme/ }).click()
    await page.locator('.dashboard-language button').click()
  }
  expect(errors).toEqual([])
})

test('navigation supports desktop collapse and mobile keyboard dismissal', async ({ page }, testInfo) => {
  await setup(page)
  await page.goto('/admin/dashboard')
  await expect(page.getByRole('heading', { name: 'Needs attention', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Notifications', exact: true }).click()
  await expect(page.locator('#admin-notifications-panel')).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.locator('#admin-notifications-panel')).toHaveCount(0)
  if (testInfo.project.name === 'desktop') {
    const sidebar = page.locator('#admin-sidebar')
    const expandedWidth = (await sidebar.boundingBox())!.width
    const collapse = page.locator('.dashboard-collapse button')
    await collapse.click()
    await expect(collapse).toHaveAttribute('aria-expanded', 'false')
    await expect.poll(async () => (await sidebar.boundingBox())!.width).toBeLessThan(expandedWidth)
    await sidebar.getByRole('link', { name: 'Orders', exact: true }).click()
    await expect(page).toHaveURL(/\/admin\/orders$/)
  } else {
    const menu = page.getByRole('button', { name: 'Toggle sidebar', exact: true })
    await menu.click()
    const dialog = page.getByRole('dialog', { name: 'Admin', exact: true })
    await expect(dialog).toBeVisible()
    await expect(dialog.getByRole('link').first()).toBeFocused()
    await page.keyboard.press('Shift+Tab')
    await expect(dialog.getByRole('button', { name: 'Sign out', exact: true })).toBeFocused()
    await page.keyboard.press('Escape')
    await expect(menu).toBeFocused()
    await menu.click()
    await dialog.getByRole('link', { name: 'Orders', exact: true }).click()
    await expect(page).toHaveURL(/\/admin\/orders$/)
    await expect(menu).toHaveAttribute('aria-expanded', 'false')
  }
})

test('shop switching updates overview counts and keeps actions scoped to the selected shop', async ({ page }, testInfo) => {
  await setup(page)
  await page.goto('/seller/shops/1')
  await expect(page.locator('.dashboard-metric').last()).toContainText('4')
  if (testInfo.project.name === 'mobile') await page.getByRole('button', { name: 'Toggle sidebar', exact: true }).click()
  await page.getByLabel('Switch shop', { exact: true }).selectOption('2')
  await expect(page).toHaveURL(/\/seller\/shops\/2$/)
  await expect(page.locator('.dashboard-metric').last()).toContainText('9')
  await expect(page.locator('.dashboard-title')).toContainText('Second shop')
  await page.locator('.dashboard-metric').filter({ has: page.getByRole('heading', { name: 'Low stock', exact: true }) }).click()
  await expect(page).toHaveURL(/\/seller\/shops\/2\/inventory\?stock_status=low$/)
})

test('both dashboard sidebars expose the profile and clear the session on sign out', async ({ page }, testInfo) => {
  await setup(page)
  for (const path of ['/admin/dashboard', '/seller/shops/1']) {
    await page.goto(path)
    await expect(page.locator('.dashboard-metric')).toHaveCount(4)
    if (testInfo.project.name === 'mobile') await page.getByRole('button', { name: 'Toggle sidebar', exact: true }).click()
    await expect(page.locator('.dashboard-profile')).toHaveAttribute('href', '/account/profile')
    await page.getByRole('button', { name: 'Sign out', exact: true }).click()
    await expect(page).toHaveURL('/')
    expect(await page.evaluate(() => localStorage.getItem('access_token'))).toBeNull()
  }
})
