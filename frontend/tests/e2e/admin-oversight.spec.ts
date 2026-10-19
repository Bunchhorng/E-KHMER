import { test, expect, type Page } from '@playwright/test'

const meta = { current_page: 1, last_page: 1, per_page: 20, total: 1 }
const user = { id: 1, name: 'Platform Admin', email: 'admin@example.test', role: 'super_admin', managed_shops: [] }
const shop = { id: 1, name: 'Pending shop', slug: 'pending-shop', status: 'pending', code: 'SHOP', email: 'seller@example.test', commission_rate: 12 }
const review = { id: 1, rating: 4, title: 'Older pending review', body: 'Please moderate this review.', status: 'pending', user_name: 'Buyer', product_name: 'Shirt', created_at: '2026-09-01' }
const payment = { id: 1, order_id: 7, order_number: 'ORDER-PARTIAL', currency: 'USD', method: 'bakong', status: 'partial', amount: 40, updated_at: '2026-09-01' }
const dashboard = {
  period: { from: '2026-10-01', to: '2026-10-20', revenue: 120, orders_count: 3, customers_count: 2, average_order_value: 40, revenue_delta: null, orders_delta: null, customers_delta: null },
  metrics: { total_revenue: 500, orders_count: 10, customers_count: 5, total_products: 4, total_categories: 2, total_brands: 2, pending_orders: 1, processing_orders: 1, completed_orders: 6, cancelled_orders: 2, low_stock_products: 1, out_of_stock_products: 0 },
  attention: { pending_reviews: 1, payment_exceptions: 1, open_returns: 2, open_support: 3 },
  pending_reviews: [review], payment_exceptions: [payment], revenue_trend: [{ date: '2026-10-20', revenue: 120 }], orders_trend: [{ date: '2026-10-20', orders: 3 }], status_distribution: [{ status: 'pending', count: 1 }], payment_status_distribution: [{ status: 'partial', count: 1 }], recent_orders: [],
}
const inventory = { id: 1, product_variant_id: 1, product: { id: 1, name: 'Shirt', slug: 'shirt' }, variant: { id: 1, name: 'Blue', sku: 'BLUE', is_active: true }, variant_label: 'Blue', quantity: 10, reserved_quantity: 2, available_quantity: 8, sold_count: 0, low_stock_threshold: 5, is_low_stock: false, is_out_of_stock: false }

async function setup(page: Page, locale = 'en') {
  await page.addInitScript(({ user, locale }) => {
    localStorage.setItem('app_locale', locale)
    localStorage.setItem('access_token', 'admin-test-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, { user, locale })
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url())
    const path = url.pathname
    if (!path.startsWith('/api/')) return route.continue()
    let data: unknown = { data: [], meta: { ...meta, total: 0 } }
    if (path === '/api/auth/me') data = { data: user }
    else if (path === '/api/admin/dashboard/overview') data = { data: { ...dashboard, period: { ...dashboard.period, from: url.searchParams.get('range') === 'today' ? '2026-10-20' : '2026-10-01' } } }
    else if (path === '/api/admin/shops') data = { data: [shop], meta }
    else if (path === '/api/admin/shops/1') data = { data: shop }
    else if (path === '/api/admin/payments') data = { data: [{ ...payment, status: 'pending', order_payment_status: 'partial', customer_name: 'Buyer', paid_at: null, created_at: '2026-09-01' }], meta }
    else if (path === '/api/admin/shop-members') {
      const role = url.searchParams.get('role') ?? 'owner'
      data = { data: [{ id: 1, role, status: 'active', joined_at: '2026-09-01', user: { id: 2, name: role === 'manager' ? 'Demo Manager' : 'Demo Owner', email: 'seller@example.test', phone: null }, shop }], meta }
    } else if (path === '/api/admin/settings') data = { data: { storeName: 'E-KHMER', supportEmail: 'help@example.test', supportPhone: '', storeAddress: '', currency: 'USD', locale: 'en', timezone: 'Asia/Phnom_Penh', lowStockThreshold: 5, emailOrderNotifications: true, emailLowStockAlerts: true, maintenanceMode: false } }
    else if (path === '/api/admin/reports/summary') data = { data: { revenue: 120, items_revenue: 100, refunded: 0, orders_count: 3, customers_count: 2, units_sold: 4, avg_order_value: 40, payment_methods: [], low_stock_count: 1, sales_by_category: [{ id: 1, name: 'Clothing', slug: 'clothing', revenue: 100, order_count: 2 }], top_selling_products: [{ product_id: 1, product_name: 'Best seller shirt', total_qty: 4, revenue: 100 }] } }
    else if (path === '/api/admin/inventory') data = { data: [inventory], meta }
    else if (path === '/api/admin/inventory/1/adjust') data = { data: { ...inventory, quantity: 15, available_quantity: 13 } }
    await route.fulfill({ json: data })
  })
}

test('admin overview prioritizes live queues and removes redundant dashboard feeds', async ({ page }, testInfo) => {
  await setup(page)
  await page.goto('/admin/dashboard')
  await expect(page.getByRole('heading', { name: 'Needs attention', exact: true })).toBeVisible()
  await expect(page.getByText('Marketplace paid order value', { exact: true })).toBeVisible()
  await expect(page.getByText('Older pending review')).toBeVisible()
  await expect(page.getByText('ORDER-PARTIAL')).toBeVisible()
  for (const title of ['Recent customers', 'Recent payments', 'Top-selling products', 'Sales by category', 'Low stock alerts']) {
    await expect(page.getByRole('heading', { name: new RegExp(`^${title}$`, 'i') })).toHaveCount(0)
  }
  await page.getByLabel('Date range', { exact: true }).selectOption('today')
  await expect(page.getByText('Older pending review')).toBeVisible()
  if (testInfo.project.name === 'mobile') await page.getByRole('button', { name: 'Toggle sidebar' }).click()
  const sidebar = page.locator('#admin-sidebar')
  await expect(sidebar.getByRole('link', { name: 'Shop members', exact: true })).toBeVisible()
  await expect(sidebar.getByRole('link', { name: 'Add Product', exact: true })).toHaveCount(0)
  await expect(page.locator('header input[type="text"]')).toHaveCount(0)
  if (testInfo.project.name === 'mobile') await page.keyboard.press('Escape')
  await page.screenshot({ path: `test-results/admin-overview-${testInfo.project.name}.png`, fullPage: true })
})

test('payment exception link opens the server-filtered attention view', async ({ page }) => {
  await setup(page)
  await page.goto('/admin/dashboard')
  await page.locator('section[aria-labelledby="admin-attention-title"]').getByRole('link', { name: /Payment exceptions/ }).click()
  await expect(page).toHaveURL(/\/admin\/payments\?attention=1/)
  await expect(page.getByLabel('Needs attention only')).toBeChecked()
  await expect(page.getByText('Partially paid', { exact: true }).last()).toBeVisible()
  await page.getByLabel('Needs attention only').uncheck()
  await expect(page).toHaveURL(/\/admin\/payments$/)
})

test('shop member roles share a page while preserving separate creation and editing', async ({ page }) => {
  await setup(page)
  await page.goto('/admin/shop-members')
  await expect(page.getByRole('heading', { name: 'Shop members', exact: true })).toBeVisible()
  await expect(page.getByText('Demo Owner', { exact: true })).toBeVisible()
  await page.getByLabel('Role', { exact: true }).selectOption('manager')
  await expect(page).toHaveURL(/role=manager/)
  await expect(page.getByText('Demo Manager', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Add Shop Admin', exact: true }).click()
  await expect(page).toHaveURL(/\/admin\/shop-admins\/new$/)
})

test('currency and commission controls describe actual payment behavior', async ({ page }) => {
  await setup(page)
  await page.goto('/admin/settings')
  await expect(page.getByLabel('Default currency', { exact: true })).toBeDisabled()
  await expect(page.getByLabel('Default currency', { exact: true })).toHaveValue('USD')
  await expect(page.locator('#currency option')).toHaveCount(1)
  await expect(page.getByText('Checkout currently supports USD. Other currencies are unavailable.')).toBeVisible()
  await page.goto('/admin/shops/1/edit')
  await expect(page.getByLabel('Reference commission rate (%)', { exact: false })).toHaveValue('12')
  await expect(page.getByText('Accounting reference only. Direct Bakong payments do not deduct or collect this fee.')).toBeVisible()
})

test('merchandising analytics are available on the reports page', async ({ page }) => {
  await setup(page)
  await page.goto('/admin/reports')
  await expect(page.getByRole('heading', { name: 'Merchandising analysis', exact: true })).toBeVisible()
  await expect(page.getByText('Best seller shirt', { exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: /^Sales by category$/i })).toBeVisible()
})

test('stock corrections require deliberate opt-in and keep the reservation-aware ledger flow', async ({ page }) => {
  await setup(page)
  await page.goto('/admin/inventory')
  await page.getByRole('row', { name: /Shirt Blue BLUE/ }).click()
  await expect(page.getByRole('button', { name: 'Adjust stock', exact: true })).toHaveCount(0)
  await page.getByLabel('Enable admin corrections', { exact: true }).check()
  await page.getByLabel('New on-hand quantity', { exact: true }).fill('15')
  page.once('dialog', dialog => dialog.accept())
  await page.getByRole('button', { name: 'Adjust stock', exact: true }).click()
  await expect(page.getByText('Stock adjusted and recorded in the inventory ledger.')).toBeVisible()
})

test('Khmer overview translates the attention queues and new navigation', async ({ page }, testInfo) => {
  await setup(page, 'km')
  await page.goto('/admin/dashboard')
  await expect(page.getByRole('heading', { name: 'ត្រូវការការយកចិត្តទុកដាក់', exact: true })).toBeVisible()
  await expect(page.getByText('តម្លៃការបញ្ជាទិញដែលបានបង់ប្រាក់', { exact: true })).toBeVisible()
  await expect(page.getByText('admin.overview.', { exact: false })).toHaveCount(0)
  expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(1)
  await page.screenshot({ path: `test-results/admin-overview-km-${testInfo.project.name}.png`, fullPage: true })
})
