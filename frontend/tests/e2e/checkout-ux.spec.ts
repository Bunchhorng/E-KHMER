import { expect, test, type Page } from '@playwright/test'
import type { ApiCartItem } from '../../src/api/cart'
import { quoteFixture } from './quote-fixture'

const buyer = { id: 1, name: 'Sokha Dara', email: 'sokha@example.test', role: 'customer', managed_shops: [] }

function makeItem(id: number, shopId: number, shopName: string, price: number): ApiCartItem {
  return {
    id,
    quantity: id === 1 ? 2 : 1,
    available: true,
    available_quantity: 10,
    unavailable_reason: null,
    variant: {
      id,
      name: 'M / Matte',
      sku: `SKU-${id}`,
      price,
      compare_at_price: null,
      in_stock: true,
      attributes: [
        { name: 'Size', value: 'M' },
        { name: 'Finish', value: 'Matte' },
      ],
      product: {
        id,
        name: `Product ${id}`,
        slug: `product-${id}`,
        cover_image: null,
        shop_id: shopId,
        shop_name: shopName,
      },
    },
  }
}

async function setupAuthenticatedCheckout(page: Page) {
  const items = [makeItem(1, 1, 'Phnom Penh Tech', 45), makeItem(2, 2, 'Angkor Apparel', 60)]
  const deliveries = [
    { id: 11, shop_id: 1, name: 'Express Phnom Penh', description: 'Same-day courier', price: 3, estimated_days_min: 1, estimated_days_max: 2, is_active: true },
    { id: 21, shop_id: 2, name: 'Standard Provincial', description: 'Tracked parcel', price: 5, estimated_days_min: null, estimated_days_max: null, is_active: true },
  ]
  const addresses = [
    { id: 1, label: 'Home', full_name: 'Sokha Dara', phone: '+855 12 345 678', address_line1: 'No. 12, Street 271', address_line2: 'Sangkat Boeung Tumpun', city: 'Phnom Penh', state: 'Phnom Penh', postal_code: '12351', country: 'KH', is_default: true },
    { id: 2, label: 'Office', full_name: 'Sokha Dara', phone: '+855 98 765 432', address_line1: 'No. 88, Monivong Blvd', address_line2: null, city: 'Phnom Penh', state: 'Phnom Penh', postal_code: '12202', country: 'KH', is_default: false },
  ]

  await page.route('**/api/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()

    if (path === '/api/auth/me') return route.fulfill({ json: { data: buyer } })
    if (path === '/api/addresses') return route.fulfill({ json: { data: addresses } })
    if (path === '/api/shipping-methods') return route.fulfill({ json: { data: deliveries } })
    if (path === '/api/cart') {
      return route.fulfill({
        json: {
          data: {
            id: 1,
            items,
            totals: { subtotal: 150, discount_amount: 0, tax_amount: 15, total: 165, items_count: 3 },
          },
        },
      })
    }
    if (path === '/api/checkout/quote') {
      const payload = route.request().postDataJSON()
      return route.fulfill({
        json: {
          data: quoteFixture(items, {
            delivery: deliveries.map((d) => ({ id: d.id, shop_id: d.shop_id, name: d.name, price: d.price })),
            selections: payload?.shipping_methods,
          }).quote,
        },
      })
    }
    return route.fulfill({ json: { data: [] } })
  })
}

test('checkout delivers stepper navigation, compact addresses, backend-only delivery estimates, payment cards, sticky summary, sharp corners, dark/light, and mobile Khmer', async ({
  page,
}, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')

  await page.addInitScript(({ user }) => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'dark')
    localStorage.setItem('access_token', 'checkout-ux-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, { user: buyer })

  await setupAuthenticatedCheckout(page)
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/checkout')

  // Step 1: Compact addresses, Add New Address button, stepper state, sharp corners
  const stepper = page.getByRole('navigation', { name: 'Checkout progress' })
  await expect(stepper).toBeVisible()
  await expect(stepper.getByRole('button', { name: /Delivery/ })).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Add New Address', exact: true })).toBeVisible()
  await expect(page.getByText('Estimated Total', { exact: true })).toBeVisible()
  await expect(page.getByText('Size: M · Finish: Matte').first()).toBeVisible()

  const step1Check = await page.evaluate(() => {
    const elements = Array.from(document.querySelectorAll('main .card, main nav ol, main nav button, main nav span, main button, main input:not(.sr-only)'))
    const grid = document.querySelector('main .grid.lg\\:grid-cols-\\[minmax\\(0\\,7fr\\)_minmax\\(20rem\\,3fr\\)\\]')!
    return {
      overflow: document.documentElement.scrollWidth > window.innerWidth,
      summarySticky: getComputedStyle(grid.children[1]).position === 'sticky',
      radii: elements.map((el) => ({ tag: el.tagName, cls: el.className, radius: getComputedStyle(el).borderRadius })),
    }
  })
  expect(step1Check.overflow).toBe(false)
  expect(step1Check.summarySticky).toBe(true)
  for (const item of step1Check.radii) expect(item.radius, `${item.tag}.${item.cls}`).toBe('0px')

  // Select second address & advance to Step 2
  await page.getByText('Office', { exact: true }).click()
  await page.screenshot({ path: 'test-results/checkout-step1-dark-desktop.png', fullPage: true })
  await page.getByRole('button', { name: 'Continue to Delivery' }).click()

  // Step 2: Seller-grouped delivery & estimates shown ONLY when backend provides them
  const shop1 = page.getByRole('group', { name: 'Phnom Penh Tech' })
  const shop2 = page.getByRole('group', { name: 'Angkor Apparel' })
  await expect(shop1).toContainText('1–2 business days')
  await expect(shop2).not.toContainText('business day')

  // Completed Step 1 button in stepper is clickable and preserves selection
  await stepper.getByRole('button', { name: /Contact & Shipping/ }).click()
  await expect(page.getByRole('heading', { name: 'Shipping Address' })).toBeVisible()
  await page.getByRole('button', { name: 'Continue to Delivery' }).click()
  await page.getByRole('button', { name: 'Continue to Payment' }).click()

  // Step 3: Payment cards
  await expect(page.getByRole('heading', { name: 'Payment Method' })).toBeVisible()
  await page.getByRole('main').getByText('Bakong / KHQR', { exact: true }).click()
  await page.getByRole('button', { name: 'Review Order', exact: true }).click()

  // Step 4: Review shows confirmed Total, Office address, Bakong / KHQR
  await expect(page.getByRole('heading', { name: /Review & Place Order/ })).toBeVisible()
  await expect(page.getByText('No. 88, Monivong Blvd').first()).toBeVisible()
  await expect(page.getByText('Bakong / KHQR', { exact: true })).toBeVisible()
  await expect(page.getByText('Total', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Place Order · $173.00' })).toBeVisible()
  await page.screenshot({ path: 'test-results/checkout-review-dark-desktop.png', fullPage: true })

  // Light mode check
  const themeToggle = page.getByTestId('storefront-header-utilities').getByRole('button', { name: /Switch theme/ })
  await themeToggle.click()
  await expect(page.locator('html')).not.toHaveClass(/dark/)
  await page.screenshot({ path: 'test-results/checkout-review-light-desktop.png', fullPage: true })

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
  await page.screenshot({ path: 'test-results/checkout-review-dark-mobile-km.png', fullPage: true })
})
