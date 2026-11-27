import { expect, test, type Page } from '@playwright/test'
import type { ApiCartItem } from '../../src/api/cart'
import { quoteFixture } from './quote-fixture'

function shopItem(
  id: number,
  shopId: number,
  shopName: string,
  price = 45.5,
  quantity = 1,
  attributes = [
    { name: 'Size', value: 'M' },
    { name: 'Finish', value: 'Matte' },
  ]
): ApiCartItem {
  return {
    id,
    quantity,
    available: true,
    available_quantity: 8,
    unavailable_reason: null,
    variant: {
      id,
      name: attributes.map((a) => a.value).join(' / '),
      sku: `SKU-${id}`,
      price,
      compare_at_price: null,
      in_stock: true,
      attributes,
      product: {
        id,
        name: `Product ${id} Extra Long Name Designed To Wrap Cleanly Across Narrow Screens`,
        slug: `product-${id}`,
        cover_image: null,
        shop_id: shopId,
        shop_name: shopName,
      },
    },
  }
}

async function setupCartPage(page: Page, initialItems: ApiCartItem[]) {
  const state = {
    items: initialItems,
    loadError: false,
  }

  await page.route('**/api/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()

    const method = route.request().method()
    if (path === '/api/cart' || path.startsWith('/api/cart/items/')) {
      if (method === 'GET' && state.loadError) {
        return route.fulfill({ status: 503, json: { message: 'Unavailable' } })
      }
      const id = Number(path.split('/').at(-1))
      if (method === 'POST') {
        const payload = route.request().postDataJSON()
        const existing = state.items.find((row) => row.variant?.id === payload.product_variant_id)
        if (existing) existing.quantity += payload.quantity ?? 1
        else state.items.push(shopItem(payload.product_variant_id, 1, 'Phnom Penh Tech', 45.5, payload.quantity ?? 1))
      } else if (method === 'PUT') {
        const target = state.items.find((row) => row.id === id)
        if (target) target.quantity = route.request().postDataJSON().quantity
      } else if (method === 'DELETE') {
        state.items = path === '/api/cart' ? [] : state.items.filter((row) => row.id !== id)
      }
      const subtotal = state.items.reduce(
        (sum, row) => sum + (row.available ? (row.variant?.price ?? 0) * row.quantity : 0),
        0
      )
      return route.fulfill({
        json: {
          data: {
            id: 1,
            items: state.items,
            totals: {
              subtotal,
              discount_amount: 0,
              tax_amount: subtotal * 0.1,
              total: subtotal * 1.1,
              items_count: state.items.reduce((sum, row) => sum + row.quantity, 0),
            },
          },
        },
      })
    }

    if (path === '/api/checkout/quote') {
      const payload = route.request().postDataJSON()
      const coupon =
        payload?.coupon_code === 'SAVE10'
          ? { code: 'SAVE10', shop_id: null, type: 'fixed' as const, value: 10, min_order_amount: 20 }
          : null
      return route.fulfill({ json: { data: quoteFixture(state.items, { coupon }).quote } })
    }

    if (path === '/api/categories') return route.fulfill({ json: { data: [] } })
    return route.fulfill({ json: { data: [] } })
  })

  return state
}

test('cart page delivers desktop 70/30 layout, seller grouping, variant labels, undo removal, coupons, mobile sticky clearance, sharp corners, dark/light, and Khmer', async ({
  page,
}, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')

  await page.addInitScript(() => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'dark')
  })

  await setupCartPage(page, [
    shopItem(1, 1, 'Phnom Penh Tech', 45.5, 2),
    shopItem(2, 2, 'Angkor Apparel', 120.0, 1),
  ])

  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/cart')

  await expect(page.getByRole('heading', { name: 'Shopping Cart', exact: true })).toBeVisible()
  await expect(page.getByText('Phnom Penh Tech', { exact: true })).toBeVisible()
  await expect(page.getByText('Angkor Apparel', { exact: true })).toBeVisible()
  await expect(page.getByText('Size: M').first()).toBeVisible()
  await expect(page.getByText('Finish: Matte').first()).toBeVisible()
  const orderSummary = page.getByRole('region', { name: 'Order Summary' })
  await expect(orderSummary.getByText('Estimated Total', { exact: true })).toBeVisible()
  await expect(orderSummary.getByText('Final shipping and tax amounts are confirmed at checkout.')).toBeVisible()

  // Check ~70/30 split, sticky summary, sharp corners, no horizontal overflow
  const desktopCheck = await page.evaluate(() => {
    const grid = document.querySelector('main .grid')!
    const left = grid.children[0].getBoundingClientRect()
    const right = grid.children[1].getBoundingClientRect()
    const totalW = left.width + right.width
    const summaryEl = grid.children[1]
    const cards = Array.from(document.querySelectorAll('main .card, main input, main button, main a'))
    return {
      leftRatio: left.width / totalW,
      rightRatio: right.width / totalW,
      summaryPosition: getComputedStyle(summaryEl).position,
      overflow: document.documentElement.scrollWidth > window.innerWidth,
      radii: cards.map((el) => getComputedStyle(el).borderRadius),
    }
  })

  expect(desktopCheck.leftRatio).toBeGreaterThanOrEqual(0.65)
  expect(desktopCheck.leftRatio).toBeLessThanOrEqual(0.75)
  expect(desktopCheck.rightRatio).toBeGreaterThanOrEqual(0.25)
  expect(desktopCheck.rightRatio).toBeLessThanOrEqual(0.35)
  expect(desktopCheck.summaryPosition).toBe('sticky')
  expect(desktopCheck.overflow).toBe(false)
  for (const radius of desktopCheck.radii) {
    expect(radius).toBe('0px')
  }

  // Apply coupon
  await page.locator('#cart-coupon-input').fill('SAVE10')
  await page.getByRole('button', { name: 'Apply Coupon', exact: true }).click()
  await expect(page.locator('[data-amount="discount"]')).toContainText('−$10.00')
  await expect(page.getByText('SAVE10', { exact: true })).toBeVisible()

  await page.screenshot({ path: 'test-results/cart-page-dark-desktop.png', fullPage: true })

  // Remove first item and test Undo restoration
  await page.locator('[data-cart-item="1"]').getByRole('button', { name: 'Remove item' }).click()
  await expect(page.locator('[data-cart-item="1"]')).toHaveCount(0)
  const undoBtn = page.getByRole('button', { name: 'Undo', exact: true })
  await expect(undoBtn).toBeVisible()
  await undoBtn.click()
  await expect(page.locator('[data-cart-item]')).toHaveCount(2)

  // Light mode screenshot
  const themeToggle = page.getByTestId('storefront-header-utilities').getByRole('button', { name: /Switch theme/ })
  await themeToggle.click()
  await expect(page.locator('html')).not.toHaveClass(/dark/)
  await page.screenshot({ path: 'test-results/cart-page-light-desktop.png', fullPage: true })

  // Mobile widths + Khmer + sticky bar non-overlap with MobileBottomNavigation
  await themeToggle.click()
  const languageToggle = page.getByTestId('storefront-header-utilities').locator('button:has(img)')
  await languageToggle.click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'km')

  for (const width of [320, 375, 390, 430, 768]) {
    await page.setViewportSize({ width, height: 844 })
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    const stickyCheck = await page.evaluate(() => {
      const stickyBar = document.querySelector('[data-testid="mobile-cart-sticky-bar"]')
      const bottomNav = document.querySelector('nav[aria-label="Mobile navigation"]')
      if (!stickyBar || !bottomNav) return { visible: false, overlaps: true }
      const barRect = stickyBar.getBoundingClientRect()
      const navRect = bottomNav.getBoundingClientRect()
      return { visible: true, overlaps: barRect.bottom > navRect.top + 1 }
    })
    expect(stickyCheck.visible).toBe(true)
    expect(stickyCheck.overlaps).toBe(false)
  }

  await page.setViewportSize({ width: 390, height: 844 })
  await page.screenshot({ path: 'test-results/cart-page-dark-mobile-km.png', fullPage: true })
})
