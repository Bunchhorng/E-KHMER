import { expect, test, type Page } from '@playwright/test'
import type { AdminCoupon } from '../../src/api/admin'
import km from '../../src/locales/km.json' with { type: 'json' }

const user = { id: 1, name: 'Coupon Admin', email: 'coupon-admin@example.test', role: 'super_admin', managed_shops: [] }
const duplicateMessage = 'The coupon code has already been taken.'
const coupon: AdminCoupon = { id: 20, shop_id: 1, code: 'SAVE20', type: 'percentage', value: 20, min_order_amount: 100,
  max_discount_amount: 25, usage_limit: 50, per_user_limit: 2, starts_at: '2026-01-01T00:00:00.000000Z',
  expires_at: '2027-12-31T18:30:00.000000Z', is_active: true }

async function setup(page: Page, locale = 'en') {
  const state = { coupons: [structuredClone(coupon)], requests: [] as { method: string; id: number | null; payload: Record<string, unknown> }[],
    statuses: [] as number[], writeGate: null as Promise<void> | null, hideFromList: false, duplicateMessage }
  await page.addInitScript(({ user, locale }) => {
    localStorage.setItem('app_locale', locale)
    localStorage.setItem('ekhmer_theme', 'light')
    localStorage.setItem('access_token', 'coupon-browser-test')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, { user, locale })
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (path === '/api/auth/me') return route.fulfill({ json: { data: user } })
    const match = path.match(/^\/api\/admin\/coupons(?:\/(\d+))?$/)
    if (!match) return route.fulfill({ json: { data: [], meta: { total: 0, unread_count: 0 } } })
    const id = match[1] ? Number(match[1]) : null
    if (route.request().method() === 'GET') {
      if (id) return route.fulfill({ json: { data: state.coupons.find(item => item.id === id) } })
      return route.fulfill({ json: { data: state.hideFromList ? [] : state.coupons,
        meta: { current_page: 1, last_page: 1, per_page: 15, total: state.coupons.length } } })
    }
    const payload = route.request().postDataJSON() as Record<string, unknown>
    state.requests.push({ method: route.request().method(), id, payload })
    if (state.writeGate) await state.writeGate
    const code = String(payload.code).trim().toUpperCase()
    if (state.coupons.some(item => item.code.toUpperCase() === code && item.id !== id)) {
      state.statuses.push(422)
      return route.fulfill({ status: 422, json: { message: 'The given data was invalid.', errors: { code: [state.duplicateMessage] } } })
    }
    const previous = state.coupons.find(item => item.id === id)
    const saved: AdminCoupon = { ...coupon, ...previous, id: id ?? state.coupons.length + 30, code,
      type: payload.type === 'fixed' ? 'fixed' : 'percentage', value: Number(payload.value),
      min_order_amount: payload.min_order_amount === null ? null : Number(payload.min_order_amount),
      max_discount_amount: payload.max_discount_amount === null ? null : Number(payload.max_discount_amount),
      usage_limit: payload.usage_limit === null ? null : Number(payload.usage_limit),
      per_user_limit: payload.per_user_limit === null ? null : Number(payload.per_user_limit),
      expires_at: typeof payload.expires_at === 'string' ? payload.expires_at : null, is_active: Boolean(payload.is_active) }
    if (previous) state.coupons = state.coupons.map(item => item.id === id ? saved : item)
    else state.coupons.push(saved)
    const status = id ? 200 : 201
    state.statuses.push(status)
    return route.fulfill({ status, json: { data: saved } })
  })
  return state
}

test('duplicate create displays the code error, preserves all inputs, and permits a corrected retry', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/admin/coupons/new')
  await page.locator('#cp-code').fill('SAVE20')
  await page.locator('#cp-type').selectOption('fixed')
  await page.locator('#cp-value').fill('12.5')
  await page.locator('#cp-min').fill('80')
  await page.locator('#cp-max').fill('15')
  await page.locator('#cp-limit').fill('120')
  await page.locator('#cp-per-customer').fill('3')
  await page.locator('#cp-expires').fill('2027-06-01')
  await page.locator('#cp-status').selectOption('Draft')
  const response = page.waitForResponse(response => response.url().endsWith('/api/admin/coupons') && response.request().method() === 'POST')
  await page.getByRole('button', { name: 'Save Coupon', exact: true }).first().click()
  expect((await response).status()).toBe(422)
  await expect(page.locator('#cp-code-error')).toHaveText(duplicateMessage)
  await expect(page.locator('#cp-code')).toHaveAttribute('aria-invalid', 'true')
  await expect(page).toHaveURL(/\/admin\/coupons\/new$/)
  for (const [id, value] of [['cp-code', 'SAVE20'], ['cp-type', 'fixed'], ['cp-value', '12.5'], ['cp-min', '80'],
    ['cp-max', '15'], ['cp-limit', '120'], ['cp-per-customer', '3'], ['cp-expires', '2027-06-01'], ['cp-status', 'Draft']]) {
    await expect(page.locator(`#${id}`)).toHaveValue(value!)
  }
  await expect(page.getByRole('button', { name: 'Save Coupon', exact: true }).first()).toBeEnabled()
  expect(state.coupons).toHaveLength(1)
  await expect(page.locator('body')).not.toContainText('SQLSTATE')
  await page.locator('#cp-code').fill('FRESH20')
  await page.getByRole('button', { name: 'Save Coupon', exact: true }).first().click()
  await expect(page).toHaveURL(/\/admin\/coupons$/)
  expect(state.coupons).toHaveLength(2)
  expect(state.coupons.find(item => item.code === 'FRESH20')?.max_discount_amount).toBe(15)
  expect(state.statuses).toEqual([422, 201])
})

test('edit loads a coupon directly and saves a new maximum discount with the same code', async ({ page }) => {
  const state = await setup(page)
  state.hideFromList = true
  await page.goto('/admin/coupons/20/edit')
  await expect(page.locator('#cp-code')).toHaveValue('SAVE20')
  await expect(page.locator('#cp-max')).toHaveValue('25')
  await expect(page.locator('#cp-limit')).toHaveValue('50')
  await expect(page.locator('#cp-status')).toHaveValue('Active')
  await expect(page.locator('#cp-expires')).toHaveValue('2027-12-31')
  await page.locator('#cp-max').fill('30')
  await page.getByRole('button', { name: 'Save Coupon', exact: true }).first().click()
  await expect(page).toHaveURL(/\/admin\/coupons$/)
  expect(state.requests).toHaveLength(1)
  expect(state.requests[0]).toMatchObject({ method: 'PUT', id: 20, payload: { code: 'SAVE20', max_discount_amount: 30,
    min_order_amount: 100, usage_limit: 50, per_user_limit: 2, is_active: true, expires_at: '2027-12-31T18:30:00.000000Z' } })
  expect(state.coupons[0]).toMatchObject({ shop_id: 1, starts_at: coupon.starts_at, code: 'SAVE20', max_discount_amount: 30 })
})

test('updating to another coupon code keeps the editor open and does not overwrite either coupon', async ({ page }) => {
  const state = await setup(page)
  state.coupons.push({ ...coupon, id: 10, code: 'SAVE10', value: 10 })
  await page.goto('/admin/coupons/10/edit')
  await expect(page.locator('#cp-code')).toHaveValue('SAVE10')
  await page.locator('#cp-code').fill('SAVE20')
  await page.locator('#cp-max').fill('45')
  await page.getByRole('button', { name: 'Save Coupon', exact: true }).first().click()
  await expect(page.locator('#cp-code-error')).toHaveText(duplicateMessage)
  await expect(page).toHaveURL(/\/admin\/coupons\/10\/edit$/)
  await expect(page.locator('#cp-max')).toHaveValue('45')
  expect(state.coupons.find(item => item.id === 10)?.code).toBe('SAVE10')
  expect(state.coupons).toHaveLength(2)
  await page.locator('#cp-code').fill('SAVE15')
  await page.getByRole('button', { name: 'Save Coupon', exact: true }).first().click()
  await expect(page).toHaveURL(/\/admin\/coupons$/)
  expect(state.statuses).toEqual([422, 200])
})

test('pending saves block both buttons and programmatic duplicate submissions', async ({ page }) => {
  const state = await setup(page)
  let release: (() => void) | undefined
  state.writeGate = new Promise<void>(resolve => { release = resolve })
  await page.goto('/admin/coupons/new')
  await page.locator('#cp-code').fill('FRESH20')
  await page.locator('#cp-value').fill('20')
  await page.getByRole('button', { name: 'Save Coupon', exact: true }).first().click()
  await expect.poll(() => state.requests.length).toBe(1)
  await expect(page.locator('button[type="submit"]').first()).toBeDisabled()
  await expect(page.locator('button[type="submit"]').last()).toBeDisabled()
  await expect(page.locator('#cp-code')).toBeDisabled()
  await page.locator('form').evaluate((form: HTMLFormElement) => { form.requestSubmit(); form.requestSubmit() })
  expect(state.requests).toHaveLength(1)
  release?.()
  await expect(page).toHaveURL(/\/admin\/coupons$/)
  expect(state.statuses).toEqual([201])
  expect(state.coupons).toHaveLength(2)
})

test('Khmer coupon labels and translated server field errors use the same validation flow', async ({ page }) => {
  const state = await setup(page, 'km')
  state.duplicateMessage = 'លេខកូដគូប៉ុងនេះត្រូវបានប្រើរួចហើយ។'
  await page.goto('/admin/coupons/new')
  await expect(page.getByLabel(km.admin.coupons.max_discount_label, { exact: true })).toBeVisible()
  await page.locator('#cp-code').fill('SAVE20')
  await page.locator('#cp-value').fill('20')
  await page.getByRole('button', { name: km.admin.coupons.save_coupon, exact: true }).first().click()
  await expect(page.locator('#cp-code-error')).toHaveText(state.duplicateMessage)
  await expect(page.locator('#cp-code')).toHaveValue('SAVE20')
  await expect(page.getByRole('button', { name: km.admin.coupons.save_coupon, exact: true }).first()).toBeEnabled()
  expect(state.coupons).toHaveLength(1)
})
