import { expect, test, type Page, type TestInfo, type Locator } from '@playwright/test'
import type { CatalogProduct } from '../../src/api/catalog'

function product(id: number, name: string, price: number, rating: number, category = 'electronics'): CatalogProduct {
  return { id, name, slug: `product-${id}`, short_description: null, price, compare_at_price: null, rating_avg: rating, rating_count: rating ? 2 : 0,
    is_featured: false, is_active: true, in_stock: true, cover_image: null,
    variants: [{ id, price, name: 'Default', sku: `SKU-${id}`, in_stock: true, attributes: [] }],
    category: { id: category === 'books' ? 2 : 1, slug: category, name: category === 'books' ? 'Books' : 'Electronics' },
    brand: { id: 1, slug: 'acme', name: 'Acme' } }
}
async function setup(page: Page) {
  await page.addInitScript(() => localStorage.setItem('app_locale', 'en'))
  const state = {
    products: [product(1, 'Product A', 800, 4.8), product(2, 'Product B', 300, 4.2), product(3, 'Product C', 100, 3.5), product(4, 'Book', 4, 0, 'books'), product(5, 'Premium Book', 1299.99, 0, 'books')],
    requests: [] as URLSearchParams[], fail: false, omitMetadata: false, paginate: false,
    delayedRating: '', release: (() => {}) as () => void,
  }
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url())
    if (!url.pathname.startsWith('/api/')) return route.continue()
    let json: unknown = { data: [] }
    if (url.pathname === '/api/cart') json = { data: { id: 1, items: [], totals: { subtotal: 0, tax_amount: 0, discount_amount: 0, total: 0, items_count: 0 } } }
    else if (url.pathname === '/api/catalog/facets') json = { data: { categories: [{ slug: 'electronics', name: 'Electronics', count: 3 }, { slug: 'books', name: 'Books', count: 2 }], brands: [{ slug: 'acme', name: 'Acme', count: 5 }], colors: [], sizes: [] } }
    else if (url.pathname === '/api/shops/tech') json = { data: { id: 1, slug: 'tech', name: 'Tech Store', description: null, logo: null, banner: null, phone: null, email: null, city: null, province: null, address_line: null, country: 'KH', products_count: 3, rating_count: 0, rating_avg: 0 } }
    else if (url.pathname === '/api/catalog/products' || url.pathname === '/api/shops/tech/products') {
      state.requests.push(url.searchParams)
      if (state.fail) return route.fulfill({ status: 503, json: { message: 'Catalog temporarily unavailable.' } })
      let items = state.products.filter(p => url.pathname === '/api/catalog/products' || p.category?.slug === 'electronics')
      const rating = Number(url.searchParams.get('rating'))
      if (rating) items = items.filter(p => p.rating_count > 0 && p.rating_avg >= rating)
      const category = url.searchParams.get('category')
      if (category) items = items.filter(p => p.category?.slug === category)
      const term = url.searchParams.get('q')?.toLowerCase()
      if (term) items = items.filter(p => p.name.toLowerCase().includes(term))
      const range = items.length ? { min: Math.min(...items.map(p => p.price)), max: Math.max(...items.map(p => p.price)) } : { min: null, max: null }
      if (url.searchParams.has('min')) items = items.filter(p => p.price >= Number(url.searchParams.get('min')))
      if (url.searchParams.has('max')) items = items.filter(p => p.price <= Number(url.searchParams.get('max')))
      if (url.searchParams.get('rating') === state.delayedRating && state.delayedRating) await new Promise<void>(resolve => { state.release = resolve })
      json = { data: items, meta: { current_page: Number(url.searchParams.get('page') ?? 1), last_page: state.paginate ? 8 : 1, per_page: 12, total: state.paginate ? 90 : items.length, ...(state.omitMetadata ? {} : { price_range: range }) } }
    }
    await route.fulfill({ json })
  })
  return state
}
async function controls(page: Page, info: TestInfo): Promise<Locator> {
  if (info.project.name === 'mobile') {
    const existing = page.locator('[data-price-filter="catalog-mobile"]')
    if (!await existing.isVisible()) await page.getByRole('button', { name: 'Filters', exact: true }).click()
    return existing
  }
  return page.locator('[data-price-filter="catalog-desktop"]')
}
async function closeFilters(page: Page, info: TestInfo) {
  if (info.project.name === 'mobile') await page.getByRole('button', { name: 'Apply', exact: true }).click()
}
const productLinks = (page: Page) => page.locator('main a[href^="/product/"]').filter({ hasText: /Product|Book/ })

test('fractional rating and a 500–1000 range find the 800 dollar product on desktop and mobile', async ({ page }, info) => {
  const state = await setup(page)
  await page.goto('/shop')
  const control = await controls(page, info)
  await expect(control.getByLabel('Maximum price', { exact: true })).toHaveValue('1299.99')
  await page.getByRole('radio', { name: '4.5 & up', exact: true }).check()
  await expect.poll(() => state.requests.at(-1)?.get('rating')).toBe('4.5')
  await expect(productLinks(page)).toHaveCount(1)
  // Bounds narrowed by rating can still be expanded with precise text inputs.
  await control.getByLabel('Maximum price', { exact: true }).fill('1000')
  await control.getByLabel('Minimum price', { exact: true }).fill('500')
  await expect.poll(() => state.requests.at(-1)?.get('min')).toBe('500')
  await expect.poll(() => state.requests.at(-1)?.get('max')).toBe('1000')
  await closeFilters(page, info)
  await expect(page.getByRole('link', { name: 'Product A', exact: true })).toBeVisible()
  await expect(productLinks(page)).toHaveCount(1)
})

test('3.5 query is preserved and clear filters restores actual catalog bounds', async ({ page }, info) => {
  const state = await setup(page)
  await page.goto('/shop?rating=3.5&min=100&max=900')
  const control = await controls(page, info)
  await expect(page.getByRole('radio', { name: '3.5 & up', exact: true })).toBeChecked()
  await expect(control.getByLabel('Minimum price', { exact: true })).toHaveValue('100')
  expect(state.requests.at(-1)?.get('rating')).toBe('3.5')
  await page.getByRole('button', { name: 'Clear all', exact: true }).click()
  await expect(page).toHaveURL(/\/shop$/)
  await expect(control.getByLabel('Minimum price', { exact: true })).toHaveValue('4')
  await expect(control.getByLabel('Maximum price', { exact: true })).toHaveValue('1299.99')
})

test('price inputs and sliders synchronize, validate order and debounce without silently swapping', async ({ page }, info) => {
  const state = await setup(page)
  await page.goto('/shop')
  const control = await controls(page, info)
  await control.getByLabel('Minimum price', { exact: true }).fill('19.99')
  await expect(control.getByLabel('Minimum price slider', { exact: true })).toHaveValue('19.99')
  await expect.poll(() => state.requests.at(-1)?.get('min')).toBe('19.99')
  await control.getByLabel('Maximum price', { exact: true }).fill('10')
  await expect(control.getByRole('alert')).toContainText('Maximum price must be greater than or equal')
  expect(state.requests.at(-1)?.get('max')).not.toBe('10')
  await control.getByLabel('Maximum price', { exact: true }).fill('49.99')
  await expect.poll(() => state.requests.at(-1)?.get('max')).toBe('49.99')
  await control.getByLabel('Minimum price slider', { exact: true }).fill('20.01')
  await expect(control.getByLabel('Minimum price', { exact: true })).toHaveValue('20.01')
  await expect.poll(() => state.requests.at(-1)?.get('min')).toBe('20.01')
  await closeFilters(page, info)
  await expect(page.getByText('No products match your filters', { exact: true })).toBeVisible()
})

test('rating preserves search category brand stock sort and resets page; reload and back restore URL filters', async ({ page }, info) => {
  const state = await setup(page)
  state.paginate = true
  await page.goto('/shop?q=Product&category=electronics&brand=acme&stock=1&sort=price-desc&page=8')
  await controls(page, info)
  await page.getByRole('radio', { name: '4.5 & up', exact: true }).check()
  await expect.poll(() => state.requests.at(-1)?.get('rating')).toBe('4.5')
  const query = state.requests.at(-1)!
  for (const [key, value] of Object.entries({ q: 'Product', category: 'electronics', brand: 'acme', stock: '1', sort: 'price-desc', page: '1' })) expect(query.get(key)).toBe(value)
  await page.reload()
  await controls(page, info)
  await expect(page.getByRole('radio', { name: '4.5 & up', exact: true })).toBeChecked()
  await page.goBack()
  await expect(page).toHaveURL(/page=8/)
  await expect.poll(() => state.requests.at(-1)?.get('rating')).toBeNull()
})

test('category changes price metadata without applying the current price window to those bounds', async ({ page }, info) => {
  await setup(page)
  await page.goto('/shop?category=electronics&min=500&max=1000')
  const control = await controls(page, info)
  await expect(control.getByText('$100.00 – $800.00', { exact: true })).toBeVisible()
  await page.getByRole('radio', { name: /Books/ }).check()
  await expect(control.getByText('$4.00 – $1,299.99', { exact: true })).toBeVisible()
  await expect(control.getByLabel('Minimum price', { exact: true })).toHaveValue('500')
  await expect(control.getByLabel('Maximum price', { exact: true })).toHaveValue('1000')
})

test('an older catalog response cannot overwrite a newer rating or price range', async ({ page }, info) => {
  const state = await setup(page)
  await page.goto('/shop')
  await controls(page, info)
  state.delayedRating = '3.5'
  await page.getByRole('radio', { name: '3.5 & up', exact: true }).check()
  await expect.poll(() => state.requests.at(-1)?.get('rating')).toBe('3.5')
  await page.getByRole('radio', { name: '4.5 & up', exact: true }).check()
  await expect(productLinks(page)).toHaveCount(1)
  const oldResponse = page.waitForResponse(response => response.url().includes('/catalog/products') && new URL(response.url()).searchParams.get('rating') === '3.5')
  state.release()
  await oldResponse
  await expect(productLinks(page)).toHaveCount(1)
  await closeFilters(page, info)
  await expect(page.getByRole('link', { name: 'Product A', exact: true })).toBeVisible()
})

test('catalog errors show retry and missing metadata never invents price bounds', async ({ page }, info) => {
  const state = await setup(page)
  state.fail = true
  await page.goto('/shop')
  await expect(page.getByText('Catalog temporarily unavailable.', { exact: true })).toBeVisible()
  state.fail = false
  await page.getByRole('button', { name: 'Retry products', exact: true }).click()
  await expect(productLinks(page)).toHaveCount(5)
  state.omitMetadata = true
  await page.reload()
  const control = await controls(page, info)
  await expect(control.getByRole('alert')).toContainText('Price range unavailable.')
  await expect(control.getByRole('slider')).toHaveCount(0)
  state.omitMetadata = false
  await control.getByRole('button', { name: 'Retry', exact: true }).click()
  await expect(control.getByLabel('Maximum price', { exact: true })).toHaveValue('1299.99')
})

test('public storefront shares the rating and price contract with bounds scoped to its shop', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/shops/tech')
  const control = page.locator('[data-price-filter="storefront"]')
  await expect(control.getByLabel('Maximum price', { exact: true })).toHaveValue('800')
  await page.getByRole('radio', { name: '4.5 & up', exact: true }).check()
  await expect.poll(() => state.requests.at(-1)?.get('rating')).toBe('4.5')
  await expect(productLinks(page)).toHaveCount(1)
  await control.getByLabel('Maximum price', { exact: true }).fill('1000')
  await expect.poll(() => state.requests.at(-1)?.get('max')).toBe('1000')
  await expect(control.getByLabel('Maximum price', { exact: true })).toHaveValue('1000')
})
