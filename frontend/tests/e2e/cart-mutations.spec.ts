import { expect, test, type Page } from '@playwright/test'
import type { AxiosInstance } from 'axios'
import type { Router } from 'vue-router'
import type { ApiCartItem } from '../../src/api/cart'
import type { ProductDetail } from '../../src/api/catalog'
import { quoteFixture } from './quote-fixture'

const browserErrors = new WeakMap<Page, string[]>()
test.beforeEach(({ page }) => {
  const errors: string[] = []
  browserErrors.set(page, errors)
  page.on('pageerror', error => errors.push(error.message))
  page.on('console', message => {
    if (/unhandled|uncaught|error in (?:event|v-on)/i.test(message.text())) errors.push(message.text())
  })
})
test.afterEach(({ page }) => { expect(browserErrors.get(page)).toEqual([]) })

function item(id: number, quantity = 1): ApiCartItem {
  return { id, quantity, available: true, available_quantity: 10, unavailable_reason: null,
    variant: { id, name: 'Blue', sku: `ITEM-${id}`, price: 50, compare_at_price: null, in_stock: true, attributes: [],
      product: { id, name: `Item ${id}`, slug: `item-${id}`, cover_image: null, shop_id: id, shop_name: `Shop ${id}` } } }
}
function detail(id = 2): ProductDetail {
  return { id, slug: `item-${id}`, name: `Item ${id}`, price: 50, compare_at_price: null, short_description: null,
    rating_avg: 4.8, rating_count: 2, is_featured: false, is_active: true, in_stock: true, cover_image: null,
    brand: null, category: null, description: 'Product details', sku: `ITEM-${id}`, weight: null, meta_title: null, meta_description: null, gallery: [],
    attributes: [{ id: 1, name: 'Color', slug: 'color', type: 'color', values: [{ id: 1, value: 'Blue', swatch_color: '#123456' }, { id: 2, value: 'Black', swatch_color: '#000000' }] }],
    variants: [id, id + 1].map((variantId, index) => ({ id: variantId, name: index ? 'Black' : 'Blue', sku: `ITEM-${variantId}`,
      price: 50, compare_at_price: null, is_default: !index, is_active: true, available_quantity: 10, in_stock: true,
      attributes: [{ attribute_slug: 'color', name: 'Color', value: index ? 'Black' : 'Blue' }] })),
  }
}
async function setup(page: Page, initialItems = [item(1)]) {
  await page.addInitScript(() => localStorage.setItem('app_locale', 'en'))
  const state = {
    items: initialItems, product: detail(), status: 0, message: '', network: false, quoteFailure: false,
    hold: false, release: (() => {}) as () => void,
    requests: [] as { method: string; path: string; payload: { product_variant_id?: number; quantity?: number } }[],
    sessions: [] as (string | undefined)[],
  }
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    const method = route.request().method()
    let json: unknown = { data: [] }
    if (path === '/api/cart' || path.startsWith('/api/cart/items/')) {
      if (method !== 'GET') {
        state.requests.push({ path, method, payload: route.request().postData() ? route.request().postDataJSON() : {} })
        state.sessions.push(route.request().headers()['x-session-id'])
        if (state.hold) await new Promise<void>(resolve => { state.release = resolve })
        if (state.network) return route.abort('failed')
        if (state.status) return route.fulfill({ status: state.status, json: { message: 'The given data was invalid.', errors: { quantity: [state.message] } } })
        const payload = state.requests.at(-1)!.payload
        const id = Number(path.split('/').at(-1))
        if (method === 'POST') {
          const existing = state.items.find(row => row.variant?.id === payload.product_variant_id)
          if (existing) existing.quantity += payload.quantity ?? 1
          else state.items.push(item(payload.product_variant_id!, payload.quantity))
        } else if (method === 'PUT') {
          const existing = state.items.find(row => row.id === id)
          if (existing) existing.quantity = payload.quantity!
        } else if (method === 'DELETE') state.items = path === '/api/cart' ? [] : state.items.filter(row => row.id !== id)
      }
      const subtotal = state.items.reduce((sum, row) => sum + (row.available ? (row.variant?.price ?? 0) * row.quantity : 0), 0)
      json = { data: { id: 1, items: state.items, totals: { subtotal, discount_amount: 0, tax_amount: subtotal / 10, total: subtotal * 1.1,
        items_count: state.items.reduce((sum, row) => sum + row.quantity, 0) } } }
    } else if (path === '/api/checkout/quote') {
      if (state.quoteFailure) return route.fulfill({ status: 503, json: { message: 'Internal quote failure' } })
      json = { data: quoteFixture(state.items).quote }
    } else if (path === '/api/catalog/products' || path === '/api/catalog/featured' || path === '/api/shops/tech/products') {
      json = { data: [detail(1), detail(2)], meta: { current_page: 1, last_page: 1, total: 2, per_page: 12, price_range: { min: 50, max: 50 } } }
    } else if (path === '/api/catalog/facets') json = { data: { colors: [], sizes: [], categories: [], brands: [] } }
    else if (/^\/api\/catalog\/products\//.test(path)) json = { data: state.product }
    else if (path === '/api/shops/tech') json = { data: { id: 1, slug: 'tech', name: 'Tech Store', products_count: 2, rating_avg: 0, rating_count: 0 } }
    else if (path === '/api/shipping-methods') json = { data: [] }
    await route.fulfill({ json })
  })
  return state
}
const buyNow = (page: Page) => page.getByRole('button', { name: 'Buy Now', exact: true })
const addToCart = (page: Page) => page.getByRole('button', { name: 'Add to Cart', exact: true })

for (const failure of [
  { name: 'validation', status: 422, message: 'Only 2 items are currently available.' },
  { name: 'out of stock', status: 422, message: 'This product is now out of stock.' },
  { name: 'inactive product', status: 422, message: 'This product is no longer available.' },
  { name: 'inactive shop', status: 422, message: 'Products from this shop are temporarily unavailable.' },
  { name: 'server', status: 500, message: 'SQLSTATE internal stack trace', expected: 'Could not add this item. Please try again.' },
  { name: 'session expiry', status: 419, message: 'CSRF token mismatch', expected: 'Your session expired. Please refresh and try again.' },
  { name: 'guest unauthorized', status: 401, message: 'Unauthenticated', expected: 'Your session expired. Please refresh and try again.' },
  { name: 'network', status: 0, message: '', expected: 'Cannot reach the server. Please check your connection.' },
]) {
  test(`Buy Now ${failure.name} failure stays on detail preserves cart and permits retry`, async ({ page }) => {
    const state = await setup(page)
    state.status = failure.status; state.message = failure.message; state.network = failure.name === 'network'
    await page.goto('/product/item-2')
    await buyNow(page).click()
    await expect(page.getByRole('alert')).toContainText(failure.expected ?? failure.message)
    await expect(page).toHaveURL(/\/product\/item-2$/)
    await expect(buyNow(page)).toBeEnabled()
    expect(state.requests).toHaveLength(1)
    expect(state.items.map(row => [row.id, row.quantity])).toEqual([[1, 1]])
    await page.getByRole('button', { name: 'Open cart', exact: true }).click()
    await expect(page.locator('aside.panel [data-cart-item]')).toHaveCount(1)
    await expect(page.locator('aside.panel [data-amount="subtotal"]')).toContainText('$50.00')
    await page.getByRole('button', { name: 'Close cart', exact: true }).click()
    state.status = 0; state.network = false
    await buyNow(page).click()
    await expect(page).toHaveURL(/\/checkout$/)
    expect(state.items).toHaveLength(2)
    expect(state.sessions.every(Boolean)).toBe(true)
  })
}

test('slow Buy Now blocks duplicates and options then navigates only after the confirmed add', async ({ page }) => {
  const state = await setup(page)
  state.hold = true
  await page.goto('/product/item-2')
  await buyNow(page).click()
  const pending = page.getByRole('button', { name: 'Preparing checkout…', exact: true })
  await expect(pending).toBeDisabled()
  await expect(pending).toHaveAttribute('aria-busy', 'true')
  await expect(addToCart(page)).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Increase quantity' })).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Black', exact: true })).toBeDisabled()
  await pending.evaluate((button: HTMLButtonElement) => { button.click(); button.click() })
  await page.waitForTimeout(2000)
  expect(state.requests).toHaveLength(1)
  expect(state.requests[0]?.payload).toEqual({ product_variant_id: 2, quantity: 1 })
  await expect(page).toHaveURL(/\/product\/item-2$/)
  state.hold = false; state.release()
  await expect(page).toHaveURL(/\/checkout$/)
  expect(state.requests).toHaveLength(1)
  await expect(page.locator('[data-amount="subtotal"]').first()).toContainText('$100.00')
})

test('Add to Cart failure keeps the drawer closed and success opens it with server-confirmed state', async ({ page }) => {
  const state = await setup(page)
  state.status = 422; state.message = 'Only 2 items are currently available.'
  await page.goto('/product/item-2')
  await addToCart(page).click()
  await expect(page.getByRole('alert')).toContainText(state.message)
  await expect(page.locator('aside.panel')).toHaveCount(0)
  await expect(addToCart(page)).toBeEnabled()
  state.status = 0; state.hold = true
  await addToCart(page).click()
  await expect(page.getByRole('button', { name: 'Adding…', exact: true })).toBeDisabled()
  await expect(page.locator('aside.panel')).toHaveCount(0)
  await expect.poll(() => state.requests.length).toBe(2)
  state.hold = false; state.release()
  await expect(page.locator('aside.panel [data-cart-item]')).toHaveCount(2)
  await expect(page.locator('aside.panel [data-amount="subtotal"]')).toContainText('$100.00')
  await expect(page).toHaveURL(/\/product\/item-2$/)
})

test('Buy Now follows existing same-item merging without replacing other cart items', async ({ page }) => {
  const state = await setup(page, [item(1), item(2)])
  await page.goto('/product/item-2')
  await buyNow(page).click()
  await expect(page).toHaveURL(/\/checkout$/)
  expect(state.items.map(row => [row.id, row.quantity])).toEqual([[1, 1], [2, 2]])
  await expect(page.locator('[data-amount="subtotal"]').first()).toContainText('$150.00')
})

test('a stale cart row survives a valid Buy Now and checkout offers recovery without crashing', async ({ page }) => {
  const stale: ApiCartItem = { id: 9, quantity: 1, available: false, available_quantity: 0, unavailable_reason: 'variant_deleted', variant: null }
  const state = await setup(page, [item(1), stale])
  await page.goto('/product/item-2')
  await buyNow(page).click()
  await expect(page).toHaveURL(/\/checkout$/)
  await expect(page.getByRole('link', { name: 'Back to Cart', exact: true })).toBeVisible()
  expect(state.items).toHaveLength(3)
})

for (const path of ['/shop', '/shops/tech', '/']) {
  test(`listing Add to Cart on ${path} handles failure pending duplicates and confirmed success`, async ({ page }) => {
    const state = await setup(page, [])
    state.status = 422; state.message = 'This product is now out of stock.'
    await page.goto(path)
    const button = addToCart(page).first()
    await button.click()
    await expect(page.getByRole('alert')).toContainText(state.message)
    await expect(button).toBeEnabled()
    expect(state.items).toHaveLength(0)
    await expect(page.getByText('Added to cart.', { exact: true })).toHaveCount(0)
    state.status = 0; state.hold = true
    await button.click()
    const pending = page.getByRole('button', { name: 'Adding…', exact: true }).first()
    await expect(pending).toHaveAttribute('aria-busy', 'true')
    await pending.evaluate((element: HTMLButtonElement) => { element.click(); element.click() })
    await expect.poll(() => state.requests.length).toBe(2)
    state.hold = false; state.release()
    await expect(page.getByText('Added to cart.', { exact: true }).first()).toBeVisible()
    expect(state.requests).toHaveLength(2)
    expect(state.items).toHaveLength(1)
  })
}

test('drawer quantity rejection preserves the confirmed value and pending disables the affected row', async ({ page }) => {
  const state = await setup(page, [item(1, 2), item(2)])
  state.status = 422; state.message = 'Only 2 items are currently available.'; state.hold = true
  await page.goto('/cart')
  await page.getByRole('button', { name: 'Open cart', exact: true }).click()
  const row = page.locator('aside.panel [data-cart-item="1"]')
  await row.getByRole('button', { name: 'Increase quantity' }).click()
  await expect(row.getByRole('button', { name: 'Increase quantity' })).toBeDisabled()
  await expect(row.getByRole('button', { name: 'Remove item' })).toBeDisabled()
  await expect(page.locator('aside.panel [data-cart-item="2"]').getByRole('button', { name: 'Remove item' })).toBeEnabled()
  await expect.poll(() => state.requests.length).toBe(1)
  state.hold = false; state.release()
  await expect(page.getByRole('alert')).toContainText(state.message)
  await expect(row.getByRole('button', { name: 'Increase quantity' })).toBeEnabled()
  expect(state.items[0]?.quantity).toBe(2)
  await expect(row.locator('.tabular-nums')).toHaveText('2')
})

test('distinct cart mutations queue safely and retain both confirmed results', async ({ page }) => {
  const state = await setup(page, [item(1), item(2)])
  state.hold = true
  await page.goto('/cart')
  await page.locator('main [data-cart-item="1"]').getByRole('button', { name: 'Increase quantity' }).click()
  await expect.poll(() => state.requests.length).toBe(1)
  await page.locator('main [data-cart-item="2"]').getByRole('button', { name: 'Increase quantity' }).click()
  expect(state.requests).toHaveLength(1)
  state.hold = false; state.release()
  await expect.poll(() => state.requests.length).toBe(2)
  await expect(page.locator('main [data-cart-item="1"] .tabular-nums')).toHaveText('2')
  await expect(page.locator('main [data-cart-item="2"] .tabular-nums')).toHaveText('2')
  await expect(page.locator('[data-amount="subtotal"]')).toContainText('$200.00')
})

test('cart success survives a blocked checkout navigation with an Open Cart recovery link', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/product/item-2')
  await page.evaluate(async () => {
    const modulePath = performance.getEntriesByType('resource').find(entry => new URL(entry.name).pathname === '/src/router/index.ts')!.name
    const { default: router } = await import(modulePath) as { default: Router }
    router.beforeEach(to => to.path === '/checkout' ? false : undefined)
  })
  await buyNow(page).click()
  await expect(page.getByRole('main').getByRole('alert')).toContainText('Item added to cart. Unable to open checkout.')
  await expect(buyNow(page)).toBeEnabled()
  expect(state.items).toHaveLength(2)
  await page.getByRole('main').getByRole('link', { name: 'View Cart', exact: true }).click()
  await expect(page).toHaveURL(/\/cart$/)
  await expect(page.locator('main [data-cart-item]')).toHaveCount(2)
})

test('leaving detail while an add is pending does not navigate back to checkout on late success', async ({ page }) => {
  const state = await setup(page)
  state.hold = true
  await page.goto('/product/item-2')
  await buyNow(page).click()
  await expect.poll(() => state.requests.length).toBe(1)
  await page.getByRole('main').getByRole('link', { name: 'Home', exact: true }).click()
  await expect(page).toHaveURL(/\/$/)
  state.hold = false; state.release()
  await expect.poll(() => state.items.length).toBe(2)
  await page.getByRole('button', { name: 'Open cart', exact: true }).click()
  await expect(page.locator('aside.panel [data-cart-item]')).toHaveCount(2)
  await expect(page).toHaveURL(/\/$/)
})

test('intentional Axios cancellation is silent and restores the Buy Now button', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/product/item-2')
  await page.evaluate(async () => {
    const modulePath = performance.getEntriesByType('resource').find(entry => new URL(entry.name).pathname === '/src/api/client.ts')!.name
    const { default: client } = await import(modulePath) as { default: AxiosInstance }
    client.interceptors.request.use(config => {
      if (config.url === '/cart' && config.method === 'post') config.signal = AbortSignal.abort()
      return config
    })
  })
  await buyNow(page).click()
  await expect(buyNow(page)).toBeEnabled()
  await expect(page.getByRole('alert')).toHaveCount(0)
  expect(state.requests).toHaveLength(0)
  await expect(page).toHaveURL(/\/product\/item-2$/)
})

test('no resolved variant prevents invalid cart requests', async ({ page }) => {
  const state = await setup(page)
  state.product.variants = []
  await page.goto('/product/item-2')
  await expect(buyNow(page)).toBeDisabled()
  await expect(addToCart(page)).toBeDisabled()
  expect(state.requests).toHaveLength(0)
})

test('a timed out add shows a safe retry message and never starts checkout', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/product/item-2')
  await page.evaluate(async () => {
    const modulePath = performance.getEntriesByType('resource').find(entry => new URL(entry.name).pathname === '/src/api/client.ts')!.name
    const { default: client } = await import(modulePath) as { default: AxiosInstance }
    client.interceptors.request.use(config => {
      if (config.url === '/cart' && config.method === 'post') config.timeout = 50
      return config
    })
  })
  await page.route('**/api/cart', async route => {
    if (route.request().method() !== 'POST') return route.fallback()
    await new Promise(resolve => setTimeout(resolve, 200))
    await route.abort('timedout')
  })
  await buyNow(page).click()
  await expect(page.getByRole('alert')).toContainText('The server took too long to respond. Please try again.')
  await expect(buyNow(page)).toBeEnabled()
  await expect(page).toHaveURL(/\/product\/item-2$/)
  expect(state.items).toHaveLength(1)
})
