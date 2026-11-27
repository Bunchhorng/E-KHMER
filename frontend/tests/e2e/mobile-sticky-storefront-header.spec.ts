import { expect, test } from '@playwright/test'

test('the compact mobile storefront header keeps search, language, and theme controls while the promotion scrolls away', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile')

  await page.addInitScript(() => localStorage.setItem('app_locale', 'en'))
  const categories = Array.from({ length: 9 }, (_, index) => ({
    id: index + 1, name: `Category ${index + 1}`, slug: `category-${index + 1}`,
    description: null, image: null, sort_order: index, is_active: true, parent_id: null,
  }))
  const products = Array.from({ length: 12 }, (_, index) => ({
    id: index + 1, name: `Product ${index + 1}`, slug: `product-${index + 1}`, short_description: null,
    price: 20 + index, compare_at_price: null, rating_avg: 4, rating_count: 1, is_featured: false,
    is_active: true, in_stock: true, cover_image: null,
    variants: [{ id: index + 1, price: 20 + index, name: 'Default', sku: `SKU-${index + 1}`, in_stock: true, attributes: [] }],
    category: { id: 1, slug: 'category-1', name: 'Category 1' }, brand: { id: 1, slug: 'ekhmer', name: 'E-KHMER' },
  }))

  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()

    if (path === '/api/categories') return route.fulfill({ json: { data: categories } })
    if (path === '/api/catalog/facets') return route.fulfill({ json: { data: { categories, brands: [], colors: [], sizes: [] } } })
    if (path === '/api/catalog/products') return route.fulfill({ json: { data: products, meta: { current_page: 1, last_page: 1, total: products.length, per_page: 12, price_range: { min: 20, max: 31 } } } })
    if (path === '/api/cart') return route.fulfill({ json: { data: { id: 1, items: [], totals: { subtotal: 0, tax_amount: 0, discount_amount: 0, total: 0, items_count: 0 } } } })
    return route.fulfill({ json: { data: [] } })
  })

  await page.goto('/shop')
  const promotion = page.getByTestId('storefront-promotion')
  const sticky = page.getByTestId('mobile-storefront-sticky')
  const header = page.getByTestId('storefront-main-header')
  const headerRow = header.locator('> div > .container-app')
  const utilities = page.getByTestId('storefront-header-utilities')
  const languageToggle = utilities.locator('button:has(img)')
  const themeToggle = utilities.getByRole('button', { name: /Switch theme/ })
  const categoryNav = page.getByTestId('storefront-category-nav')

  await expect(promotion).toBeVisible()
  await expect(header.getByRole('searchbox')).toBeVisible()
  await expect(categoryNav).toBeVisible()
  await expect(page.getByRole('navigation', { name: 'Mobile navigation' })).toBeVisible()
  await expect(languageToggle).toBeVisible()
  await expect(themeToggle).toBeVisible()
  await languageToggle.click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'km')
  await expect(languageToggle).toBeVisible()
  await languageToggle.click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')

  await themeToggle.click()
  await expect(page.locator('html')).toHaveClass(/dark/)
  await expect(themeToggle).toBeVisible()
  await themeToggle.click()
  await expect(page.locator('html')).not.toHaveClass(/dark/)

  for (const width of [320, 375, 390, 430, 768]) {
    await page.setViewportSize({ width, height: 915 })
    await page.evaluate(() => window.scrollTo(0, 500))
    await expect.poll(() => sticky.evaluate(element => Math.round(element.getBoundingClientRect().top))).toBe(0)
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await expect.poll(() => headerRow.evaluate(row => row.scrollWidth <= row.clientWidth)).toBe(true)
    await expect.poll(() => categoryNav.locator('ul').evaluate(rail => rail.scrollWidth > rail.clientWidth)).toBe(true)
    await expect.poll(() => page.getByTestId('product-grid').evaluate(grid => grid.scrollWidth <= grid.clientWidth)).toBe(true)
    if (width < 768) await expect.poll(() => page.getByTestId('product-grid').evaluate(grid => getComputedStyle(grid).gridTemplateColumns.split(' ').length)).toBe(2)
    await expect(languageToggle).toBeVisible()
    await expect(themeToggle).toBeVisible()
    await page.evaluate(() => window.scrollTo(0, 200))
    await expect.poll(() => sticky.evaluate(element => Math.round(element.getBoundingClientRect().top))).toBe(0)
  }

  await page.evaluate(() => window.scrollTo(0, 500))
  await expect.poll(() => sticky.evaluate(element => Math.round(element.getBoundingClientRect().top))).toBe(0)
  await expect.poll(() => promotion.evaluate(element => element.getBoundingClientRect().bottom < 0)).toBe(true)
  await expect(header.getByRole('searchbox')).toBeVisible()
  await expect(categoryNav).toBeVisible()
  await expect(page.getByRole('navigation', { name: 'Mobile navigation' })).toBeVisible()
})

test('desktop category navigation scrolls without overflowing the page', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')
  await page.addInitScript(() => localStorage.setItem('app_locale', 'en'))

  const categories = Array.from({ length: 18 }, (_, index) => ({
    id: index + 1, name: `Long Category Label ${index + 1}`, slug: `category-${index + 1}`,
    description: null, image: null, sort_order: index, is_active: true, parent_id: null,
  }))

  await page.route('**/api/categories', route => route.fulfill({ json: { data: categories } }))
  await page.route('**/api/cart', route => route.fulfill({ json: { data: { id: 1, items: [], totals: { subtotal: 0, tax_amount: 0, discount_amount: 0, total: 0, items_count: 0 } } } }))
  await page.goto('/shop')

  const categoryNav = page.getByTestId('storefront-category-nav')
  const categoryRail = categoryNav.locator('ul')
  const forward = categoryNav.getByRole('button', { name: /next page/i })
  const sticky = page.getByTestId('mobile-storefront-sticky')

  for (const width of [1024, 1440]) {
    await page.setViewportSize({ width, height: 915 })
    await expect(categoryNav).toBeVisible()
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await expect.poll(() => categoryRail.evaluate(rail => rail.scrollWidth > rail.clientWidth)).toBe(true)
    await page.evaluate(() => window.scrollTo(0, 500))
    await expect.poll(() => sticky.evaluate(element => Math.round(element.getBoundingClientRect().top))).toBe(0)
    await page.evaluate(() => window.scrollTo(0, 200))
    await expect.poll(() => sticky.evaluate(element => Math.round(element.getBoundingClientRect().top))).toBe(0)
  }

  await forward.click()
  await expect.poll(() => categoryRail.evaluate(rail => rail.scrollLeft > 0)).toBe(true)
})
