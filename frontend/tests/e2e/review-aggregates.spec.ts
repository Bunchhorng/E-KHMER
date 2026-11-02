import { expect, test, type Page } from '@playwright/test'
import type { AdminReview } from '../../src/api/admin'
import type { ProductDetail } from '../../src/api/catalog'

const user = { id: 1, name: 'Moderator', email: 'moderator@example.test', role: 'super_admin', managed_shops: [] }
function review(id: number, rating: number, status = 'approved'): AdminReview {
  return { id, rating, status, title: `Review ${id}`, body: 'Customer experience', verified: true, helpful_count: 0,
    created_at: '2026-11-01T00:00:00Z', user: { id: id + 10, name: `Customer ${id}` }, product: { id: 10, name: 'Rated product', slug: 'rated-product' } }
}
function product(reviews: AdminReview[]): ProductDetail {
  const approved = reviews.filter(r => r.status === 'approved')
  return { id: 10, slug: 'rated-product', name: 'Rated product', price: 50, sku: 'RATING-10',
    short_description: 'Review fixture', description: '<p>Product description</p>', compare_at_price: null,
    rating_count: approved.length, rating_avg: approved.length ? Math.round(approved.reduce((sum, r) => sum + r.rating, 0) / approved.length * 100) / 100 : 0,
    is_featured: false, is_active: true, in_stock: true, cover_image: null, weight: null,
    meta_title: null, meta_description: null, gallery: [], attributes: [],
    brand: { id: 1, slug: 'acme', name: 'Acme' }, category: { id: 1, slug: 'audio', name: 'Audio' },
    variants: [{ id: 10, name: 'Default', sku: 'RATING-10', price: 50, compare_at_price: null, is_active: true,
      is_default: true, available_quantity: 10, in_stock: true, attributes: [] }] }
}
async function setup(page: Page, reviews: AdminReview[]) {
  await page.addInitScript(user => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('access_token', 'isolated-review-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, user)
  const state = { reviews, failIds: [] as number[], requests: [] as string[] }
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url())
    const path = url.pathname
    if (!path.startsWith('/api/')) return route.continue()
    let json: unknown = { data: [] }
    const mutation = path.match(/^\/api\/admin\/reviews\/(\d+)(?:\/(approve|reject))?$/)
    if (mutation && route.request().method() !== 'GET') {
      state.requests.push(path)
      const id = Number(mutation[1])
      if (state.failIds.includes(id)) return route.fulfill({ status: 503, json: { message: 'Could not update this review. Please try again.' } })
      const current = state.reviews.find(r => r.id === id)
      if (!current) return route.fulfill({ status: 404, json: { message: 'Review not found.' } })
      if (route.request().method() === 'DELETE') {
        state.reviews = state.reviews.filter(r => r.id !== id)
        json = { data: { message: 'Review deleted successfully.' } }
      } else {
        current.status = mutation[2] === 'approve' ? 'approved' : 'rejected'
        json = { data: current }
      }
    } else if (path === '/api/auth/me') json = { data: user }
    else if (path === '/api/cart') json = { data: { id: 1, items: [], totals: { subtotal: 0, tax_amount: 0, discount_amount: 0, total: 0, items_count: 0 } } }
    else if (path === '/api/admin/reviews') {
      const data = state.reviews.filter(r => !url.searchParams.get('status') || r.status === url.searchParams.get('status'))
      json = { data, meta: { current_page: 1, last_page: 1, per_page: 15, total: data.length } }
    } else if (path === '/api/catalog/products/rated-product') json = { data: product(state.reviews) }
    else if (path === '/api/products/10/reviews') json = { data: state.reviews.filter(r => r.status === 'approved') }
    else if (path === '/api/catalog/facets') json = { data: { categories: [], brands: [], colors: [], sizes: [] } }
    else if (path === '/api/catalog/products') {
      const item = product(state.reviews)
      const rating = Number(url.searchParams.get('rating'))
      const data = rating && (!item.rating_count || item.rating_avg < rating) ? [] : [item]
      json = { data, meta: { current_page: 1, last_page: 1, per_page: 12, total: data.length, price_range: data.length ? { min: 50, max: 50 } : { min: null, max: null } } }
    }
    await route.fulfill({ json })
  })
  return state
}
const row = (page: Page, id: number) => page.getByRole('row').filter({ has: page.getByText(`Review ${id}`, { exact: true }) })
async function rowAction(page: Page, id: number, action: 'Reject' | 'Delete' | 'Approved') {
  await row(page, id).getByRole('button').last().click()
  await page.getByRole('button', { name: action, exact: true }).click()
}
async function assertDetail(page: Page, average: number, count: number) {
  await page.goto('/product/rated-product')
  await expect(page.getByRole('main').getByText(`${average.toFixed(1)}/5`, { exact: true })).toBeVisible()
  await expect(page.getByText(`(${count} reviews)`, { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Reviews', exact: true }).click()
  await expect(page.getByText(`Based on ${count} reviews`, { exact: true })).toBeVisible()
}

test('rejecting the only published review refreshes detail, cards and rejected status without stale stars', async ({ page }) => {
  await setup(page, [review(1, 5)])
  await assertDetail(page, 5, 1)
  await page.goto('/admin/reviews')
  await page.getByRole('button', { name: 'Approved (1)', exact: true }).click()
  await rowAction(page, 1, 'Reject')
  await expect(row(page, 1)).toHaveCount(0)
  await page.getByRole('button', { name: 'Rejected (1)', exact: true }).click()
  await expect(row(page, 1)).toContainText('Rejected')
  await assertDetail(page, 0, 0)
  await expect(page.getByRole('heading', { name: 'No reviews yet', exact: true })).toBeVisible()
  await page.goto('/shop')
  const card = page.locator('main .card').filter({ has: page.getByRole('link', { name: 'Rated product', exact: true }) })
  await expect(card.getByText('(0)', { exact: true })).toBeVisible()
  await expect(card.locator('svg.fill-current')).toHaveCount(0)
})

test('admin deleting a five star review leaves the four star review in public detail and cards', async ({ page }) => {
  await setup(page, [review(1, 5), review(2, 4)])
  await assertDetail(page, 4.5, 2)
  await page.goto('/admin/reviews')
  await page.getByRole('button', { name: 'Approved (2)', exact: true }).click()
  await rowAction(page, 1, 'Delete')
  await expect(row(page, 1)).toHaveCount(0)
  await expect(row(page, 2)).toBeVisible()
  await assertDetail(page, 4, 1)
  await expect(page.getByText('Customer 1', { exact: true })).toHaveCount(0)
  await page.goto('/shop')
  const card = page.locator('main .card').filter({ has: page.getByRole('link', { name: 'Rated product', exact: true }) })
  await expect(card.getByText('(1)', { exact: true })).toBeVisible()
  await expect(card.locator('svg.fill-current')).toHaveCount(4)
})

test('pending approval and rejected reapproval update the correct status tabs', async ({ page }) => {
  await setup(page, [review(1, 4), review(2, 5, 'pending')])
  await page.goto('/admin/reviews')
  await rowAction(page, 2, 'Approved')
  await page.getByRole('button', { name: 'Approved (2)', exact: true }).click()
  await expect(row(page, 2)).toContainText('Approved')
  await rowAction(page, 2, 'Reject')
  await page.getByRole('button', { name: 'Rejected (1)', exact: true }).click()
  await rowAction(page, 2, 'Approved')
  await expect(page.getByRole('button', { name: 'Approved (2)', exact: true })).toBeVisible()
  await assertDetail(page, 4.5, 2)
})

for (const action of ['Reject', 'Delete', 'Approved'] as const) {
  test(`failed ${action.toLowerCase()} keeps the original row and displays an error without success`, async ({ page }) => {
    const state = await setup(page, [review(1, 5, action === 'Approved' ? 'pending' : 'approved')])
    state.failIds.push(1)
    await page.goto('/admin/reviews')
    if (action !== 'Approved') await page.getByRole('button', { name: 'Approved (1)', exact: true }).click()
    await rowAction(page, 1, action)
    await expect(page.getByRole('alert')).toContainText(`Could not ${action === 'Approved' ? 'approve' : action.toLowerCase()} review`)
    await expect(row(page, 1)).toBeVisible()
    await expect(page.getByRole('status').filter({ hasText: /Approved review by|Rejected review by|Review deleted/ })).toHaveCount(0)
  })
}

for (const action of ['approve', 'delete'] as const) {
  test(`partial bulk ${action} failure never reports all selected reviews as successful`, async ({ page }) => {
    const state = await setup(page, [review(1, 5, 'pending'), review(2, 4, 'pending')])
    state.failIds.push(2)
    await page.goto('/admin/reviews')
    await expect(row(page, 1)).toBeVisible()
    await page.getByRole('columnheader').first().getByRole('checkbox').check()
    await page.getByRole('combobox').selectOption(action)
    await page.getByRole('button', { name: 'Apply', exact: true }).click()
    await expect(page.getByRole('alert')).toContainText(`Could not ${action} review`)
    await expect(row(page, 2)).toBeVisible()
    await expect(row(page, 1)).toHaveCount(0)
    await expect(page.getByText(action === 'approve' ? 'Approved 2 reviews' : 'Deleted 2 reviews', { exact: true })).toHaveCount(0)
    expect(state.requests).toHaveLength(2)
  })
}

test('moderation drops product below 4.5 filter after catalog refresh', async ({ page }) => {
  await setup(page, [review(1, 5), review(2, 4)])
  await page.goto('/shop?rating=4.5')
  await expect(page.getByRole('link', { name: 'Rated product', exact: true })).toBeVisible()
  await page.goto('/admin/reviews')
  await page.getByRole('button', { name: 'Approved (2)', exact: true }).click()
  await rowAction(page, 1, 'Reject')
  await expect(page.getByRole('button', { name: 'Approved (1)', exact: true })).toBeVisible()
  await page.goto('/shop?rating=4.5')
  await expect(page.getByRole('link', { name: 'Rated product', exact: true })).toHaveCount(0)
  await page.goto('/shop')
  await expect(page.getByRole('link', { name: 'Rated product', exact: true })).toBeVisible()
})
