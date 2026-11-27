import { expect, test, type Page } from '@playwright/test'
import type { ApiOrder } from '../../src/api/checkout'

const buyer = { id: 1, name: 'Sokha Dara', email: 'sokha@example.test', role: 'customer', managed_shops: [] }

function buildOrder(overrides: Partial<ApiOrder> = {}): ApiOrder {
  return {
    order_number: 'ORD-2026-8812',
    status: 'confirmed',
    payment_status: 'unpaid',
    subtotal: 150,
    discount_amount: 10,
    tax_amount: 14,
    shipping_amount: 8,
    total: 162,
    currency: 'USD',
    coupon_code: 'SAVE10',
    email: 'sokha@example.test',
    phone: '+855 12 345 678',
    customer_name: 'Sokha Dara',
    note: null,
    placed_at: '2026-11-27T08:00:00Z',
    shipping_address: {
      full_name: 'Sokha Dara',
      address_line1: 'No. 12, Street 271',
      city: 'Phnom Penh',
      state: 'Phnom Penh',
      postal_code: '12351',
      country: 'KH',
      phone: '+855 12 345 678',
    },
    billing_address: null,
    payment: {
      id: 1,
      method: 'cod',
      status: 'pending',
      transaction_id: null,
      amount: 162,
      paid_at: null,
    },
    shipment: null,
    items: [
      {
        id: 1,
        product_id: 10,
        product_variant_id: 100,
        shop_id: 1,
        shop: { id: 1, name: 'Phnom Penh Tech', slug: 'phnom-penh-tech' },
        product_name: 'Wireless Mechanical Keyboard With Aluminum Frame And Extra Long Description',
        variant_label: 'Switch: Tactile · Layout: US',
        sku: 'KB-100',
        image_path: null,
        unit_price: 45,
        quantity: 2,
        line_total: 90,
      },
      {
        id: 2,
        product_id: 20,
        product_variant_id: 200,
        shop_id: 2,
        shop: { id: 2, name: 'Angkor Apparel', slug: 'angkor-apparel' },
        product_name: 'Cotton Oxford Shirt',
        variant_label: 'Size: M · Color: Navy',
        sku: 'SHIRT-200',
        image_path: null,
        unit_price: 60,
        quantity: 1,
        line_total: 60,
      },
    ],
    shop_orders: [
      {
        id: 11,
        order_id: 1,
        order_number: 'ORD-2026-8812',
        shop_order_number: 'SHOP-11',
        status: 'confirmed',
        parent_status: 'confirmed',
        payment_status: 'unpaid',
        currency: 'USD',
        customer_name: 'Sokha Dara',
        email: 'sokha@example.test',
        phone: '+855 12 345 678',
        shipping_address: null,
        placed_at: '2026-11-27T08:00:00Z',
        subtotal: 90,
        discount_amount: 10,
        tax_amount: 8,
        shipping_amount: 3,
        total: 91,
        items_count: 2,
        shop: { id: 1, name: 'Phnom Penh Tech', slug: 'phnom-penh-tech', logo: null },
        shipment: {
          id: 5,
          status: 'shipped',
          carrier: 'J&T Express',
          tracking_number: 'JT-882910',
          shipped_at: '2026-11-27T09:00:00Z',
          delivered_at: null,
        },
        tracking_events: [],
        allowed_transitions: [],
        items: [
          {
            id: 1,
            product_id: 10,
            product_variant_id: 100,
            shop_id: 1,
            shop: { id: 1, name: 'Phnom Penh Tech', slug: 'phnom-penh-tech' },
            product_name: 'Wireless Mechanical Keyboard With Aluminum Frame And Extra Long Description',
            variant_label: 'Switch: Tactile · Layout: US',
            sku: 'KB-100',
            image_path: null,
            unit_price: 45,
            quantity: 2,
            line_total: 90,
          },
        ],
      },
      {
        id: 12,
        order_id: 1,
        order_number: 'ORD-2026-8812',
        shop_order_number: 'SHOP-12',
        status: 'confirmed',
        parent_status: 'confirmed',
        payment_status: 'unpaid',
        currency: 'USD',
        customer_name: 'Sokha Dara',
        email: 'sokha@example.test',
        phone: '+855 12 345 678',
        shipping_address: null,
        placed_at: '2026-11-27T08:00:00Z',
        subtotal: 60,
        discount_amount: 0,
        tax_amount: 6,
        shipping_amount: 5,
        total: 71,
        items_count: 1,
        shop: { id: 2, name: 'Angkor Apparel', slug: 'angkor-apparel', logo: null },
        shipment: null,
        tracking_events: [],
        allowed_transitions: [],
        items: [
          {
            id: 2,
            product_id: 20,
            product_variant_id: 200,
            shop_id: 2,
            shop: { id: 2, name: 'Angkor Apparel', slug: 'angkor-apparel' },
            product_name: 'Cotton Oxford Shirt',
            variant_label: 'Size: M · Color: Navy',
            sku: 'SHIRT-200',
            image_path: null,
            unit_price: 60,
            quantity: 1,
            line_total: 60,
          },
        ],
      },
    ],
    ...overrides,
  }
}

async function mockOrderRoute(page: Page, order: ApiOrder) {
  await page.route('**/api/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()

    if (path === '/api/auth/me') return route.fulfill({ json: { data: buyer } })
    if (path === `/api/orders/${order.order_number}`) return route.fulfill({ json: { data: order } })
    if (path === '/api/cart') {
      return route.fulfill({
        json: { data: { id: 1, items: [], totals: { subtotal: 0, discount_amount: 0, tax_amount: 0, total: 0, items_count: 0 } } },
      })
    }
    return route.fulfill({ json: { data: [] } })
  })
}

test('confirmed COD multi-shop order separates order vs payment status, groups shops, supports copy/actions, sharp corners, dark/light, and Khmer', async ({
  page,
}, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')

  const order = buildOrder()
  await page.addInitScript(({ user }) => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'dark')
    localStorage.setItem('access_token', 'order-success-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, { user: buyer })

  await mockOrderRoute(page, order)
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto(`/order/success/${order.order_number}`)

  await expect(page.getByRole('heading', { name: 'Order Confirmed!' })).toBeVisible()
  await expect(page.getByText('Pending cash collection').first()).toBeVisible()
  await expect(page.getByText('Cash on Delivery', { exact: true }).first()).toBeVisible()
  await expect(page.getByText('Phnom Penh Tech', { exact: true })).toBeVisible()
  await expect(page.getByText('Angkor Apparel', { exact: true })).toBeVisible()
  await expect(page.getByText('J&T Express · JT-882910')).toBeVisible()
  await expect(page.getByText('Tracking details will appear once the seller dispatches your shipment.')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Track My Order', exact: true })).toHaveAttribute('href', `/order/tracking/${order.order_number}`)
  await expect(page.getByRole('button', { name: 'Print Invoice / Receipt', exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Continue Shopping', exact: true })).toHaveAttribute('href', '/shop')

  // Verify sharp corners and no horizontal overflow
  const layoutCheck = await page.evaluate(() => {
    const els = Array.from(document.querySelectorAll('main .card, main button, main a, main [data-image-state]'))
    return {
      overflow: document.documentElement.scrollWidth > window.innerWidth,
      radii: els.map((el) => getComputedStyle(el).borderRadius),
    }
  })
  expect(layoutCheck.overflow).toBe(false)
  for (const r of layoutCheck.radii) expect(r).toBe('0px')

  await page.screenshot({ path: 'test-results/order-success-cod-dark-desktop.png', fullPage: true })

  // Light mode screenshot
  const themeToggle = page.getByTestId('storefront-header-utilities').getByRole('button', { name: /Switch theme/ })
  await themeToggle.click()
  await expect(page.locator('html')).not.toHaveClass(/dark/)
  await page.screenshot({ path: 'test-results/order-success-cod-light-desktop.png', fullPage: true })

  // Mobile widths + Khmer
  await themeToggle.click()
  const languageToggle = page.getByTestId('storefront-header-utilities').locator('button:has(img)')
  await languageToggle.click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'km')

  for (const width of [320, 375, 390, 430, 768]) {
    await page.setViewportSize({ width, height: 844 })
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
  }

  await page.setViewportSize({ width: 390, height: 844 })
  await page.screenshot({ path: 'test-results/order-success-cod-dark-mobile-km.png', fullPage: true })
})

test('pending Bakong order does not falsely display payment success or Order Confirmed!', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')

  const pendingBakong = buildOrder({
    order_number: 'ORD-KHQR-PENDING',
    status: 'pending',
    payment_status: 'unpaid',
    payment: { id: 2, method: 'bakong', status: 'pending', transaction_id: null, amount: 162, paid_at: null },
  })
  await page.addInitScript(({ user }) => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'dark')
    localStorage.setItem('access_token', 'order-success-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, { user: buyer })

  await mockOrderRoute(page, pendingBakong)
  await page.goto(`/order/success/${pendingBakong.order_number}`)

  await expect(page.getByRole('heading', { name: 'Order Received' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Order Confirmed!' })).toHaveCount(0)
  await expect(page.getByText('Paid (Verified)')).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Complete Bakong / KHQR Payment' })).toHaveAttribute(
    'href',
    `/checkout/${pendingBakong.order_number}/payment`
  )
})

test('verified paid Bakong order displays Order Confirmed! and Paid (Verified)', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')

  const paidBakong = buildOrder({
    order_number: 'ORD-KHQR-PAID',
    status: 'confirmed',
    payment_status: 'paid',
    payment: {
      id: 3,
      method: 'bakong',
      status: 'paid',
      transaction_id: 'KHQR-TX-99',
      amount: 162,
      paid_at: '2026-11-27T08:05:00Z',
    },
  })
  await page.addInitScript(({ user }) => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'light')
    localStorage.setItem('access_token', 'order-success-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, { user: buyer })

  await mockOrderRoute(page, paidBakong)
  await page.goto(`/order/success/${paidBakong.order_number}`)

  await expect(page.getByRole('heading', { name: 'Order Confirmed!' })).toBeVisible()
  await expect(page.getByText('Paid (Verified)').first()).toBeVisible()
})
