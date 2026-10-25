import { test, expect, type Page } from '@playwright/test'

const shop = { id: 1, name: 'E-KHMER Official Store', slug: 'e-khmer', status: 'active', logo: null }
const user = { id: 1, name: 'Super Admin', email: 'seller@example.test', role: 'customer', managed_shops: [{ ...shop, role: 'owner' }] }
const categories = [{ id: 1, name: 'Electronics' }, { id: 2, name: 'Beverages' }, { id: 3, name: 'Books' }]
const initialProducts = [
  { id: 1, name: 'MENTOS', price: 4, barcode: '6921211102762', category: categories[0], available_quantity: 120 },
  { id: 2, name: 'CAMBODIA PREMIUM WATER', price: 20, barcode: '806214310207', category: categories[1], available_quantity: 200 },
  { id: 3, name: 'Paper and Co Adventure Novel', price: 19.99, barcode: null, category: categories[2], available_quantity: 50 },
  { id: 4, name: 'Aurora Wireless Headphones', price: 49.99, barcode: null, category: categories[0], available_quantity: 5 }
].map(product => ({ ...product, slug: `product-${product.id}`, sku: `DEMO-00${product.id}-01`, cover_image: null, is_active: true, in_stock: true, is_low_stock: product.id === 4, short_description: 'A product in your shop.', variants: [], brand: null }))

async function setup(page: Page) {
  let products = structuredClone(initialProducts)
  const requests: URL[] = []
  const mutations: { id: number; active: boolean }[] = []
  const deleted: number[] = []
  const state = { failId: 0, listingError: false }
  await page.addInitScript(({ user }) => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'light')
    localStorage.setItem('access_token', 'browser-test-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, { user })
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url())
    if (!url.pathname.startsWith('/api/')) return route.continue()
    if (url.pathname === '/api/auth/me') return route.fulfill({ json: { data: user } })
    if (url.pathname === '/api/seller/shops') return route.fulfill({ json: { data: [shop] } })
    const productMatch = url.pathname.match(/^\/api\/seller\/shops\/1\/products\/(\d+)$/)
    if (productMatch) {
      const id = Number(productMatch[1])
      if (route.request().method() === 'DELETE') { deleted.push(id); products = products.filter(product => product.id !== id) }
      if (route.request().method() === 'PUT') {
        if (state.failId === id) return route.fulfill({ status: 422, json: { message: 'Could not update this product.' } })
        const active = route.request().postDataJSON().is_active as boolean
        mutations.push({ id, active })
        products = products.map(product => product.id === id ? { ...product, is_active: active } : product)
      }
      return route.fulfill({ json: { data: products.find(product => product.id === id) } })
    }
    if (url.pathname === '/api/seller/shops/1/products') {
      requests.push(url)
      if (state.listingError) return route.fulfill({ status: 500, json: { message: 'Could not load products.' } })
      const filters = url.searchParams
      const q = filters.get('q')?.toLowerCase() ?? ''
      let rows = products.filter(product => [product.name, product.sku, product.barcode ?? ''].some(value => value.toLowerCase().includes(q)))
      if (filters.get('category_id')) rows = rows.filter(product => product.category.id === Number(filters.get('category_id')))
      if (filters.get('status')) rows = rows.filter(product => product.is_active === (filters.get('status') === 'live'))
      if (filters.get('stock_status') === 'low') rows = rows.filter(product => product.is_low_stock)
      if (filters.get('stock_status') === 'out') rows = []
      if (filters.get('sort') === 'price_desc') rows.sort((a, b) => b.price - a.price)
      const perPage = Number(filters.get('per_page') || 10)
      const current = Number(filters.get('page') || 1)
      return route.fulfill({ json: {
        data: rows.slice((current - 1) * perPage, current * perPage),
        meta: { current_page: current, last_page: Math.max(1, Math.ceil(rows.length / perPage)), per_page: perPage, total: rows.length },
        summary: { total_products: products.length, live_products: products.filter(product => product.is_active).length, hidden_products: products.filter(product => !product.is_active).length, low_stock_products: products.filter(product => product.is_active && product.is_low_stock).length }, categories
      } })
    }
    return route.fulfill({ json: { data: [] } })
  })
  await page.goto('/seller/shops/1/products')
  await expect(page.locator('.products-table tbody tr')).toHaveCount(4)
  return { requests, mutations, deleted, state }
}

test('seller product filters use the API and preserve whole-shop summary counts', async ({ page }) => {
  const { requests } = await setup(page)
  await page.getByRole('combobox', { name: 'Category', exact: true }).selectOption('1')
  await expect(page.locator('tbody tr')).toHaveCount(2)
  await page.getByRole('combobox', { name: 'Stock', exact: true }).selectOption('low')
  await expect(page.locator('tbody tr')).toHaveCount(1)
  await expect(page.locator('.product-metric').first().locator('.metric-value')).toHaveText('4')
  await expect(page.locator('tbody')).toContainText('5 in stock')
  expect(requests.at(-1)?.searchParams.get('stock_status')).toBe('low')
  await page.getByRole('button', { name: 'Clear filters', exact: true }).click()
  await expect(page.locator('tbody tr')).toHaveCount(4)
  await page.getByRole('combobox', { name: 'Sort by', exact: true }).selectOption('price_desc')
  await expect(page.locator('tbody tr').first()).toContainText('Aurora Wireless Headphones')
  await page.getByRole('combobox', { name: 'Products per page', exact: true }).selectOption('25')
  await expect.poll(() => requests.at(-1)?.searchParams.get('per_page')).toBe('25')
  await page.getByRole('searchbox').fill('missing')
  await page.getByRole('searchbox').press('Enter')
  await expect(page.getByRole('heading', { name: 'No matching results' })).toBeVisible()
})

test('products support preview, keyboard actions, visibility changes and confirmed deletion', async ({ page }) => {
  const { mutations, deleted } = await setup(page)
  await page.getByRole('button', { name: 'Preview MENTOS', exact: true }).click()
  await expect(page.getByRole('dialog')).toContainText('MENTOS')
  await expect(page.getByRole('dialog')).toContainText('$4.00')
  await page.keyboard.press('Escape')
  const actions = page.getByRole('button', { name: 'Actions for MENTOS', exact: true })
  await actions.click()
  await expect(page.getByRole('menuitem', { name: 'Hide from store: MENTOS' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(actions).toBeFocused()
  await actions.click()
  await page.getByRole('menuitem', { name: 'Hide from store: MENTOS' }).click()
  await expect.poll(() => mutations).toEqual([{ id: 1, active: false }])
  await expect(page.locator('tbody tr').first()).toContainText('Hidden')
  await actions.click()
  await page.getByRole('menuitem', { name: 'Delete', exact: true }).click()
  await expect(page.getByRole('dialog')).toContainText('MENTOS')
  expect(deleted).toEqual([])
  await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click()
  await expect(page.locator('tbody tr')).toHaveCount(3)
  expect(deleted).toEqual([1])
})

test('selected products report partial failures when bulk visibility updates fail', async ({ page }) => {
  const { mutations, state } = await setup(page)
  state.failId = 2
  await page.getByRole('checkbox', { name: 'Select all products on this page' }).check()
  await expect(page.locator('.product-selection')).toContainText('4 selected')
  await page.locator('.product-selection').getByRole('button', { name: 'Hide', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('Could not update 1 products')
  await expect(page.getByRole('status')).toContainText('3 products updated')
  expect(mutations.map(item => item.id).sort()).toEqual([1, 3, 4])
  await expect(page.locator('.product-selection')).toHaveCount(0)
})

test('product listing errors keep retry available', async ({ page }) => {
  const { state } = await setup(page)
  state.listingError = true
  await page.getByRole('searchbox').fill('MENTOS')
  await page.getByRole('searchbox').press('Enter')
  await expect(page.getByRole('alert')).toContainText('Could not load products.')
  state.listingError = false
  await page.getByRole('button', { name: 'Retry', exact: true }).click()
  await expect(page.locator('tbody tr')).toHaveCount(1)
})

test('product layouts fit desktop and mobile in English, Khmer and dark mode', async ({ page }, testInfo) => {
  await setup(page)
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  if (testInfo.project.name === 'desktop') {
    await page.setViewportSize({ width: 1672, height: 941 })
    await page.screenshot({ path: testInfo.outputPath('products-desktop.png'), fullPage: true, animations: 'disabled' })
    for (const width of [360, 390, 768, 1024, 1280, 1440, 1672]) {
      await page.setViewportSize({ width, height: 941 })
      expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth), `overflow at ${width}`).toBeLessThanOrEqual(1)
    }
  } else {
    await page.screenshot({ path: testInfo.outputPath('products-mobile.png'), fullPage: true, animations: 'disabled' })
  }
  await page.getByRole('button', { name: /Switch theme/ }).click()
  await expect.poll(() => page.locator('.products-heading h1').evaluate(element => getComputedStyle(element).color)).toBe('rgb(240, 245, 255)')
  await page.screenshot({ path: testInfo.outputPath('products-dark.png'), fullPage: true, animations: 'disabled' })
  await page.locator('.seller-language button').click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'km')
  expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1)
  await page.screenshot({ path: testInfo.outputPath('products-khmer.png'), fullPage: true, animations: 'disabled' })
  expect(errors).toEqual([])
})
