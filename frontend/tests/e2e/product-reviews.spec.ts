import { expect, test, type Page } from '@playwright/test'

const user = { id: 1, name: 'Customer', email: 'customer@example.test', role: 'customer', managed_shops: [] }
const product = { id: 10, slug: 'review-product', name: 'Review product', sku: 'R-1', price: 10, compare_at_price: null, rating_avg: 0, rating_count: 0, is_active: true, is_featured: false, in_stock: true, cover_image: null, gallery: [], attributes: [], description: null, short_description: null, weight: null, meta_title: null, meta_description: null, brand: null, category: null, variants: [{ id: 1, sku: 'R-1', name: 'Default', price: 10, compare_at_price: null, is_default: true, is_active: true, available_quantity: 10, in_stock: true, attributes: [] }] }
const review = (id: number, rating: number, verified: boolean) => ({ id, rating, verified, status: 'approved', title: `Review ${id}`, body: `Comment ${id}`, created_at: '2026-01-01T00:00:00Z', user: { id, name: `Customer ${id}` } })

async function setup(page: Page, options: { authenticated?: boolean; reviews?: ReturnType<typeof review>[]; fail?: boolean; delay?: boolean } = {}) {
  const state = { reviews: options.reviews ?? [], fail: options.fail ?? false, submissions: 0 }
  await page.addInitScript(({ authenticated, user }) => {
    localStorage.setItem('app_locale', 'en')
    if (authenticated) { localStorage.setItem('access_token', 'review-test'); localStorage.setItem('ekhmer_user', JSON.stringify(user)) }
  }, { authenticated: options.authenticated, user })
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (path === '/api/catalog/products/review-product') {
      const count = state.reviews.length
      return route.fulfill({ json: { data: { ...product, rating_count: count, rating_avg: count ? state.reviews.reduce((sum, item) => sum + item.rating, 0) / count : 0 } } })
    }
    if (path === '/api/products/10/reviews') {
      if (options.delay) await new Promise(resolve => setTimeout(resolve, 1500))
      return state.fail ? route.fulfill({ status: 503, json: { message: 'Unavailable' } }) : route.fulfill({ json: { data: state.reviews } })
    }
    if (path === '/api/reviews' && route.request().method() === 'POST') {
      state.submissions++
      return route.fulfill({ status: 422, json: { errors: { product_id: ['You can only review products you have purchased and received.'] } } })
    }
    if (path === '/api/auth/me') return route.fulfill({ json: { data: user } })
    if (path === '/api/cart') return route.fulfill({ json: { data: { id: 1, items: [], totals: { subtotal: 0, discount_amount: 0, tax_amount: 0, total: 0, items_count: 0 } } } })
    return route.fulfill({ json: { data: [] } })
  })
  await page.goto('/product/review-product')
  await page.getByRole('button', { name: 'Reviews', exact: true }).click()
  return state
}

test('empty reviews offer one action and send guests through sign-in', async ({ page }) => {
  await setup(page)
  const section = page.getByRole('region', { name: 'Customer Reviews' })
  await expect(section.getByText('No reviews yet', { exact: true })).toHaveCount(1)
  await expect(section.getByRole('button', { name: 'Write a Review' })).toHaveCount(1)
  await expect(section.getByText('0.0', { exact: true })).toHaveCount(0)
  await section.getByRole('button', { name: 'Write a Review' }).click()
  await expect(page).toHaveURL(/\/auth\/login\?redirect=/)
})

for (const count of [1, 2]) {
  test(`${count} approved review(s) show real ratings and verified badges`, async ({ page }) => {
    await setup(page, { reviews: [review(1, 5, true), review(2, 3, false)].slice(0, count) })
    const section = page.getByRole('region', { name: 'Customer Reviews' })
    await expect(section.getByText(`Based on ${count} reviews`)).toBeVisible()
    await expect(section.locator('article')).toHaveCount(count)
    await expect(section.getByText('Verified Buyer')).toHaveCount(1)
    await expect(section.getByLabel('5 stars: 1 reviews')).toBeVisible()
    await expect(section.getByRole('button', { name: 'Write a Review' })).toHaveCount(1)
  })
}

test('review submission displays server eligibility rejection', async ({ page }) => {
  const state = await setup(page, { authenticated: true })
  const section = page.getByRole('region', { name: 'Customer Reviews' })
  await section.getByRole('button', { name: 'Write a Review' }).click()
  await expect(section.getByText(/purchased and received it/)).toBeVisible()
  await section.getByRole('radio', { name: '4 stars' }).check()
  await section.getByRole('button', { name: 'Submit Review' }).click()
  await expect(section.getByRole('alert')).toContainText('purchased and received')
  expect(state.submissions).toBe(1)
})

test('review loading errors can be retried without reloading the product', async ({ page }) => {
  const state = await setup(page, { fail: true })
  const section = page.getByRole('region', { name: 'Customer Reviews' })
  await expect(section.getByRole('alert')).toBeVisible()
  await expect(section.getByText('No reviews yet')).toHaveCount(0)
  state.fail = false
  await section.getByRole('button', { name: 'Retry' }).click()
  await expect(section.getByRole('heading', { name: 'No reviews yet' })).toBeVisible()
})

test('review loading shows a skeleton before the empty state', async ({ page }) => {
  await setup(page, { delay: true })
  const section = page.getByRole('region', { name: 'Customer Reviews' })
  await expect(section.getByRole('status', { name: 'Loading…' })).toBeVisible()
  await expect(section.getByRole('heading', { name: 'No reviews yet' })).toBeVisible()
})
