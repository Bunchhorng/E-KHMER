import { expect, test, type Page } from '@playwright/test'
import type { ApiCartItem } from '../../src/api/cart'
import { quoteFixture } from './quote-fixture'

function makeItem(id: number, overrides: Partial<ApiCartItem> = {}): ApiCartItem {
  const variant = {
    id,
    name: 'Midnight Black / Large',
    sku: `SKU-${id}`,
    price: 49.5,
    compare_at_price: null,
    in_stock: true,
    attributes: [{ name: 'Color', value: 'Black' }],
    product: {
      id,
      name: `Product ${id} with a Very Long Title That Must Wrap Cleanly Without Overflowing`,
      slug: `product-${id}`,
      cover_image: null,
      shop_id: 1,
      shop_name: 'E-KHMER Flagship',
    },
  }
  return {
    id,
    quantity: 1,
    available: true,
    available_quantity: 10,
    unavailable_reason: null,
    variant,
    ...overrides,
  }
}

async function setupCartMock(page: Page, initialItems: ApiCartItem[]) {
  const state = {
    items: initialItems,
    loadError: false,
  }

  await page.route('**/api/**', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname
    if (!path.startsWith('/api/')) return route.continue()

    const method = route.request().method()
    if (path === '/api/cart' || path.startsWith('/api/cart/items/')) {
      if (method === 'GET' && state.loadError) {
        return route.fulfill({ status: 503, json: { message: 'Unavailable' } })
      }
      const id = Number(path.split('/').at(-1))
      if (method === 'PUT') {
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
      return route.fulfill({ json: { data: quoteFixture(state.items).quote } })
    }

    if (path === '/api/categories') return route.fulfill({ json: { data: [] } })
    if (path === '/api/catalog/products') {
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 1, total: 0, per_page: 12, price_range: { min: 0, max: 0 } },
        },
      })
    }
    return route.fulfill({ json: { data: [] } })
  })

  return state
}

test('cart drawer meets layout, sharp-corner, accessibility, dark/light, Khmer, and responsive requirements', async ({
  page,
}, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')

  await page.addInitScript(() => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'dark')
  })

  const manyItems = Array.from({ length: 6 }, (_, index) => makeItem(index + 1, { quantity: index === 0 ? 2 : 1 }))
  await setupCartMock(page, manyItems)

  // Desktop 1440px in dark mode
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/')
  const openCartBtn = page.getByRole('button', { name: 'Open cart', exact: true })
  await openCartBtn.click()

  const drawer = page.getByRole('dialog', { name: /Your Cart/ })
  await expect(drawer).toBeVisible()
  await expect(drawer).toHaveAttribute('aria-modal', 'true')
  await expect(drawer).toContainText('Estimated Total')
  await expect(drawer).toContainText('Final shipping and tax amounts are confirmed at checkout.')

  const desktopMetrics = await drawer.evaluate((el) => {
    const rect = el.getBoundingClientRect()
    const header = el.querySelector('header')!
    const body = el.querySelector('header + div')!
    const footer = el.querySelector('footer')!
    const closeBtn = el.querySelector('header button')!
    const badge = el.querySelector('header h2 span:last-child')!
    const img = el.querySelector('[data-cart-item] [data-image-state]')!
    const qtyCounter = el.querySelector('[data-cart-item] .tabular-nums')!.parentElement!
    const checkoutBtn = el.querySelector('footer a[href="/checkout"]')!
    const viewCartBtn = el.querySelector('footer a[href="/cart"]')!

    return {
      width: rect.width,
      headerTopGap: header.getBoundingClientRect().top - rect.top,
      bodyScrollable: body.scrollHeight > body.clientHeight,
      footerBottomInsideViewport: footer.getBoundingClientRect().bottom <= window.innerHeight + 1,
      hasHorizontalOverflow: el.scrollWidth > el.clientWidth,
      radii: [el, closeBtn, badge, img, qtyCounter, checkoutBtn, viewCartBtn].map(
        (node) => getComputedStyle(node).borderRadius
      ),
    }
  })

  expect(desktopMetrics.width).toBeGreaterThanOrEqual(420)
  expect(desktopMetrics.width).toBeLessThanOrEqual(480)
  expect(desktopMetrics.headerTopGap).toBe(0)
  expect(desktopMetrics.bodyScrollable).toBe(true)
  expect(desktopMetrics.footerBottomInsideViewport).toBe(true)
  expect(desktopMetrics.hasHorizontalOverflow).toBe(false)
  for (const radius of desktopMetrics.radii) {
    expect(radius).toBe('0px')
  }

  await drawer.screenshot({ path: 'test-results/cart-drawer-dark-desktop.png' })

  // Switch to light mode and capture
  await page.keyboard.press('Escape')
  await expect(drawer).toHaveCount(0)
  await expect(openCartBtn).toBeFocused()

  const themeToggle = page.getByTestId('storefront-header-utilities').getByRole('button', { name: /Switch theme/ })
  await themeToggle.click()
  await expect(page.locator('html')).not.toHaveClass(/dark/)
  await openCartBtn.click()
  await expect(drawer).toBeVisible()
  await drawer.screenshot({ path: 'test-results/cart-drawer-light-desktop.png' })

  // Test clickable product title closes drawer & navigates
  await drawer.locator('[data-cart-item="1"] a').nth(1).click()
  await expect(page).toHaveURL(/\/product\/product-1$/)
  await expect(drawer).toHaveCount(0)

  // Switch to dark mode + Khmer on mobile viewports (320px, 375px, 390px, 430px, 768px)
  await page.goto('/')
  await themeToggle.click()
  const languageToggle = page.getByTestId('storefront-header-utilities').locator('button:has(img)')
  await languageToggle.click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'km')

  const openCartKmBtn = page.getByRole('button', { name: /Open cart|បើក Cart/ })
  const kmDrawer = page.getByRole('dialog', { name: /Your Cart|Cart របស់អ្នក/ })
  await page.setViewportSize({ width: 1440, height: 900 })
  await openCartKmBtn.click()
  await expect(kmDrawer).toBeVisible()

  for (const width of [320, 375, 390, 430, 768]) {
    await page.setViewportSize({ width, height: 844 })
    const mobileMetrics = await kmDrawer.evaluate((el) => {
      const rect = el.getBoundingClientRect()
      const decBtn = el.querySelector('button[aria-label="Decrease quantity"]')!.getBoundingClientRect()
      const incBtn = el.querySelector('button[aria-label="Increase quantity"]')!.getBoundingClientRect()
      const removeBtn = el.querySelector('[data-cart-item] button[aria-label]')!.getBoundingClientRect()
      return {
        width: Math.round(rect.width),
        viewportWidth: window.innerWidth,
        hasHorizontalOverflow: el.scrollWidth > el.clientWidth,
        decHeight: decBtn.height,
        decWidth: decBtn.width,
        incHeight: incBtn.height,
        incWidth: incBtn.width,
        removeHeight: removeBtn.height,
        removeWidth: removeBtn.width,
      }
    })
    if (width < 640) {
      expect(mobileMetrics.width).toBe(mobileMetrics.viewportWidth)
      expect(mobileMetrics.decHeight).toBeGreaterThanOrEqual(44)
      expect(mobileMetrics.decWidth).toBeGreaterThanOrEqual(44)
      expect(mobileMetrics.incHeight).toBeGreaterThanOrEqual(44)
      expect(mobileMetrics.incWidth).toBeGreaterThanOrEqual(44)
      expect(mobileMetrics.removeHeight).toBeGreaterThanOrEqual(44)
      expect(mobileMetrics.removeWidth).toBeGreaterThanOrEqual(44)
    } else {
      expect(mobileMetrics.width).toBeGreaterThanOrEqual(420)
      expect(mobileMetrics.width).toBeLessThanOrEqual(480)
    }
    expect(mobileMetrics.hasHorizontalOverflow).toBe(false)
  }

  await page.setViewportSize({ width: 390, height: 844 })
  await kmDrawer.screenshot({ path: 'test-results/cart-drawer-dark-mobile-km.png' })

  // Backdrop click dismisses
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.mouse.click(100, 100)
  await expect(kmDrawer).toHaveCount(0)
})

test('cart drawer shows retry on load error and Continue Shopping when empty', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')
  await page.addInitScript(() => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'dark')
  })

  const state = await setupCartMock(page, [])
  state.loadError = true
  await page.goto('/')
  await page.getByRole('button', { name: 'Open cart', exact: true }).click()

  const drawer = page.getByRole('dialog', { name: /Your Cart/ })
  await expect(drawer.getByRole('alert')).toContainText("We couldn't load your cart")
  state.loadError = false
  await drawer.getByRole('button', { name: 'Retry', exact: true }).click()

  await expect(drawer).toContainText('Your cart is empty')
  const continueBtn = drawer.getByRole('button', { name: 'Continue Shopping', exact: true })
  await expect(continueBtn).toBeVisible()
  await continueBtn.click()
  await expect(page).toHaveURL(/\/shop$/)
  await expect(drawer).toHaveCount(0)
})
