import { expect, test } from '@playwright/test'

test('the mobile storefront header keeps navigation, search and categories together while the promotion scrolls away', async ({ page }, testInfo) => {
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
  const categoriesRow = page.getByTestId('storefront-category-nav').locator('ul')

  await expect(promotion).toBeVisible()
  await expect(page.getByTestId('storefront-main-header').getByRole('searchbox')).toBeVisible()
  await expect(page.getByTestId('storefront-category-nav')).toBeVisible()
  await expect.poll(() => categoriesRow.evaluate(row => row.scrollWidth > row.clientWidth)).toBe(true)

  for (const width of [320, 360, 375, 390, 412, 430]) {
    await page.setViewportSize({ width, height: 915 })
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
  }

  await page.evaluate(() => window.scrollTo(0, 500))
  await expect.poll(() => sticky.evaluate(element => Math.round(element.getBoundingClientRect().top))).toBe(0)
  await expect.poll(() => promotion.evaluate(element => element.getBoundingClientRect().bottom < 0)).toBe(true)
  await expect(page.getByTestId('storefront-main-header').getByRole('searchbox')).toBeVisible()
  await expect(page.getByTestId('storefront-category-nav')).toBeVisible()
})
