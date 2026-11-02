import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test'
import type { CatalogProduct, FacetValue } from '../../src/api/catalog'

const canonicalColors = ['Light Blue', 'Dark Green', 'Rose Gold', 'Midnight Blue', 'Space Gray', 'Black', 'White', 'Red', 'Blue', 'Black/White', 'Blue & Gray', 'Off-White']
const colors: FacetValue[] = [...canonicalColors, 'Patterned', 'Custom Finish', 'Legacy Color'].map(value => ({
  slug: value.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
  ...(value === 'Legacy Color' ? {} : { value }),
  name: value, count: 1,
  swatch_color: value === 'Light Blue' ? '#ADD8E6' : value === 'Custom Finish' ? 'not-a-color' : null,
}))

function product(color: string, index: number): CatalogProduct & { color: string } {
  const id = index + 1
  return {
    id, color, name: index === 0 ? 'Product A' : color === 'Black' ? 'Product B' : `Product ${color}`,
    slug: `product-${id}`, short_description: null, price: 200, compare_at_price: null,
    rating_avg: 4.8, rating_count: 2, is_featured: false, is_active: true, in_stock: true, cover_image: null,
    variants: [{ id, price: 200, name: color, sku: `SKU-${id}`, in_stock: true, attributes: [] }],
    category: { id: 1, slug: 'clothing', name: 'Clothing' },
    brand: { id: 1, slug: 'example-brand', name: 'Example Brand' },
  }
}

async function setup(page: Page) {
  await page.addInitScript(() => localStorage.setItem('app_locale', 'en'))
  const state = { requests: [] as URLSearchParams[], paginate: false }
  const products = colors.map((color, index) => product(color.value ?? color.name, index))
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url())
    if (!url.pathname.startsWith('/api/')) return route.continue()
    let json: unknown = { data: [] }
    if (url.pathname === '/api/cart') json = { data: { id: 1, items: [], totals: { subtotal: 0, tax_amount: 0, discount_amount: 0, total: 0, items_count: 0 } } }
    else if (url.pathname === '/api/catalog/facets') json = { data: {
      categories: [{ slug: 'clothing', name: 'Clothing', count: products.length }],
      brands: [{ slug: 'example-brand', name: 'Example Brand', count: products.length }], colors, sizes: [],
    } }
    else if (url.pathname === '/api/shops/tech') json = { data: {
      id: 1, slug: 'tech', name: 'Tech Store', description: null, logo: null, banner: null, phone: null, email: null,
      city: null, province: null, address_line: null, country: 'KH', products_count: products.length, rating_count: 0, rating_avg: 0,
    } }
    else if (url.pathname === '/api/catalog/products' || url.pathname === '/api/shops/tech/products') {
      state.requests.push(url.searchParams)
      const selected = url.searchParams.getAll('colors[]').map(value => value.toLowerCase())
      let items = products.filter(item => !selected.length || selected.includes(item.color.toLowerCase()))
      const rating = Number(url.searchParams.get('rating'))
      items = items.filter(item => (!rating || item.rating_avg >= rating)
        && (!url.searchParams.has('category') || item.category?.slug === url.searchParams.get('category'))
        && (!url.searchParams.has('brand') || item.brand?.slug === url.searchParams.get('brand')))
      const price_range = items.length ? { min: 200, max: 200 } : { min: null, max: null }
      items = items.filter(item => (!url.searchParams.has('min') || item.price >= Number(url.searchParams.get('min')))
        && (!url.searchParams.has('max') || item.price <= Number(url.searchParams.get('max'))))
      json = { data: items, meta: { current_page: Number(url.searchParams.get('page') ?? 1), last_page: state.paginate ? 8 : 1,
        per_page: 12, total: state.paginate ? 90 : items.length, price_range } }
    }
    await route.fulfill({ json })
  })
  return state
}

async function controls(page: Page, info: TestInfo): Promise<Locator> {
  if (info.project.name === 'mobile') {
    const dialog = page.getByRole('dialog', { name: 'Filters', exact: true })
    if (!await dialog.isVisible()) await page.getByRole('main').getByRole('button', { name: 'Filters', exact: true }).click()
    return dialog.locator('[data-color-filter]')
  }
  return page.locator('aside [data-color-filter]')
}
async function apply(page: Page, info: TestInfo) {
  if (info.project.name === 'mobile') await page.getByRole('button', { name: 'Apply', exact: true }).click()
}
const productLinks = (page: Page) => page.locator('main a[href^="/product/"]').filter({ hasText: /^Product/ })

test('Light Blue submits the canonical value and API swatch with identical desktop and mobile results', async ({ page }, info) => {
  const state = await setup(page)
  await page.goto('/shop')
  const control = await controls(page, info)
  const blue = control.getByRole('button', { name: 'Light Blue', exact: true })
  await expect(blue.locator('[data-color-swatch]')).toHaveCSS('background-color', 'rgb(173, 216, 230)')
  await blue.click()
  await expect(blue).toHaveAttribute('aria-pressed', 'true')
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual(['Light Blue'])
  expect(new URL(page.url()).searchParams.getAll('colors')).toEqual(['Light Blue'])
  await apply(page, info)
  await expect(page.getByRole('link', { name: 'Product A', exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Product B', exact: true })).toHaveCount(0)
  await expect(productLinks(page)).toHaveCount(1)
})

test('multiple colors retain OR results and chips remove exactly the canonical value', async ({ page }, info) => {
  const state = await setup(page)
  await page.goto('/shop')
  const control = await controls(page, info)
  await control.getByRole('button', { name: 'Light Blue', exact: true }).click()
  await control.getByRole('button', { name: 'Black', exact: true }).click()
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual(['Light Blue', 'Black'])
  await apply(page, info)
  await expect(productLinks(page)).toHaveCount(2)
  await expect(page.getByRole('link', { name: 'Product A', exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Product B', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Remove Light Blue filter', exact: true }).click()
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual(['Black'])
  await expect(productLinks(page)).toHaveCount(1)
  const restored = await controls(page, info)
  await expect(restored.getByRole('button', { name: 'Light Blue', exact: true })).toHaveAttribute('aria-pressed', 'false')
  await expect(restored.getByRole('button', { name: 'Black', exact: true })).toHaveAttribute('aria-pressed', 'true')
  await restored.getByRole('button', { name: 'Black', exact: true }).click()
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual([])
})

test('multi-word single-word and special-character colors survive URL and API encoding', async ({ page }, info) => {
  const state = await setup(page)
  await page.goto('/shop')
  const control = await controls(page, info)
  for (const value of canonicalColors) {
    const option = control.getByRole('button', { name: value, exact: true })
    await option.click()
    await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual([value])
    expect(new URL(page.url()).searchParams.getAll('colors')).toEqual([value])
    await expect(productLinks(page)).toHaveCount(1)
    await option.click()
    await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual([])
  }
})

test('reload and back restore canonical selections and clear all removes both URL and checked state', async ({ page }, info) => {
  const state = await setup(page)
  await page.goto('/shop?colors=Light%20Blue&colors=Black')
  await page.reload()
  const control = await controls(page, info)
  for (const value of ['Light Blue', 'Black']) await expect(control.getByRole('button', { name: value, exact: true })).toHaveAttribute('aria-pressed', 'true')
  await apply(page, info)
  await page.getByRole('button', { name: 'Remove Light Blue filter', exact: true }).click()
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual(['Black'])
  await page.goBack()
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual(['Light Blue', 'Black'])
  await controls(page, info)
  await page.getByRole('button', { name: 'Clear all', exact: true }).click()
  await expect(page).toHaveURL(/\/shop$/)
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual([])
  const cleared = await controls(page, info)
  for (const value of ['Light Blue', 'Black']) await expect(cleared.getByRole('button', { name: value, exact: true })).toHaveAttribute('aria-pressed', 'false')
})

test('color composes with category brand rating price and stock while resetting pagination', async ({ page }, info) => {
  const state = await setup(page)
  state.paginate = true
  await page.goto('/shop?category=clothing&brand=example-brand&rating=4.5&min=100&max=500&stock=1&sort=price-asc&page=8')
  const control = await controls(page, info)
  await control.getByRole('button', { name: 'Light Blue', exact: true }).click()
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual(['Light Blue'])
  for (const [key, value] of Object.entries({ category: 'clothing', brand: 'example-brand', rating: '4.5', min: '100', max: '500', stock: '1', sort: 'price-asc', page: '1' })) expect(state.requests.at(-1)?.get(key)).toBe(value)
  expect(new URL(page.url()).searchParams.has('page')).toBe(false)
  await apply(page, info)
  await expect(productLinks(page)).toHaveCount(1)
  await expect(page.getByRole('link', { name: 'Product A', exact: true })).toBeVisible()
})

test('missing or invalid swatches remain labeled selectable and use the safe fallback', async ({ page }, info) => {
  const state = await setup(page)
  await page.goto('/shop')
  const control = await controls(page, info)
  for (const value of ['Patterned', 'Custom Finish', 'Legacy Color']) {
    const option = control.getByRole('button', { name: value, exact: true })
    await expect(option).toBeVisible()
    await expect(option.locator('[data-color-swatch]')).toHaveCSS('background-color', 'rgb(203, 213, 225)')
    await option.click()
    await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual([value])
    await option.click()
    await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual([])
  }
})

test('closing the immediate-apply mobile sheet and resizing preserve the shared canonical selection', async ({ page }) => {
  const state = await setup(page)
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/shop')
  await page.getByRole('main').getByRole('button', { name: 'Filters', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Filters', exact: true })
  await dialog.getByRole('button', { name: 'Light Blue', exact: true }).click()
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual(['Light Blue'])
  await dialog.getByRole('button', { name: 'Close', exact: true }).click()
  await expect(dialog).toHaveCount(0)
  await page.setViewportSize({ width: 1440, height: 900 })
  await expect(page.locator('aside [data-color-filter]').getByRole('button', { name: 'Light Blue', exact: true })).toHaveAttribute('aria-pressed', 'true')
  await expect(productLinks(page)).toHaveCount(1)
  await page.setViewportSize({ width: 390, height: 844 })
  await page.getByRole('main').getByRole('button', { name: 'Filters', exact: true }).click()
  await expect(page.getByRole('dialog').getByRole('button', { name: 'Light Blue', exact: true })).toHaveAttribute('aria-pressed', 'true')
})

test('public shop forwards canonical color arrays and preserves them across rating and price changes', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/shops/tech?colors=Light%20Blue&colors=Black&sizes=L&rating=4.5&min=100&max=500&category=clothing&brand=example-brand&page=2')
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual(['Light Blue', 'Black'])
  expect(state.requests.at(-1)?.getAll('sizes[]')).toEqual(['L'])
  await expect(productLinks(page)).toHaveCount(2)
  await page.getByRole('radio', { name: '3.5 & up', exact: true }).check()
  await expect.poll(() => state.requests.at(-1)?.get('rating')).toBe('3.5')
  const price = page.locator('[data-price-filter="storefront"]')
  await price.getByLabel('Maximum price', { exact: true }).fill('400')
  await expect.poll(() => state.requests.at(-1)?.get('max')).toBe('400')
  expect(state.requests.at(-1)?.getAll('colors[]')).toEqual(['Light Blue', 'Black'])
  expect(state.requests.at(-1)?.getAll('sizes[]')).toEqual(['L'])
  expect(state.requests.at(-1)?.get('page')).toBe('1')
  await page.getByRole('button', { name: 'Clear filters', exact: true }).click()
  await expect.poll(() => state.requests.at(-1)?.getAll('colors[]')).toEqual([])
})
