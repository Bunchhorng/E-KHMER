import { test, expect, type Page } from '@playwright/test'

const shop = { id: 1, name: 'E-KHMER Official Store', slug: 'e-khmer', status: 'active', logo: null }
const user = { id: 1, name: 'Shop Owner', email: 'owner@example.test', role: 'customer', managed_shops: [{ ...shop, role: 'owner' }] }
const metrics = { revenue: 600, cogs: 240, profit: 360, expense: 0, loss: 0, orders: 6, average_order_value: 100, refunds: 0, missing_cost_items: 0, cost_coverage: 100 }
async function setup(page: Page, admin = false) {
  const account = { ...user, role: admin ? 'super_admin' : 'customer' }
  const state = { mode: 'normal', saveFails: false, expenses: [] as { id: number; shop_id: number; shop_name: string; description: string; category: string; amount: number; currency: string; incurred_on: string }[], deleted: [] as number[], requests: [] as Record<string, string | number>[] }
  await page.addInitScript(({ account }) => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'light')
    localStorage.setItem('access_token', 'analytics-test-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(account))
  }, { account })
  await page.route('**/api/**', async route => {
    const request = route.request()
    const url = new URL(request.url())
    const path = url.pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (path === '/api/auth/me') return route.fulfill({ json: { data: account } })
    if (path === '/api/seller/shops' || path === '/api/admin/shops') return route.fulfill({ json: { data: [shop], meta: { total: 1, current_page: 1, last_page: 1 } } })
    if (path.includes('/forecasts')) {
      const filters = request.method() === 'POST' ? request.postDataJSON() : Object.fromEntries(url.searchParams)
      state.requests.push(filters)
      if (state.mode === 'error') return route.fulfill({ status: 500, json: { message: 'Analytics are temporarily unavailable.' } })
      const selected = filters.metric ?? 'profit'
      const expense = state.expenses.reduce((sum, item) => sum + item.amount, 0)
      const summary = { ...metrics, expense, profit: metrics.profit - expense }
      if (state.mode === 'missing') Object.assign(summary, { cogs: null, profit: null, loss: null, missing_cost_items: 2, cost_coverage: 0 })
      const history = state.mode === 'empty' ? [] : Array.from({ length: 7 }, (_, index) => ({
        period: `2026-${String(index + 4).padStart(2, '0')}`, start: `2026-${String(index + 4).padStart(2, '0')}-01`, end: `2026-${String(index + 4).padStart(2, '0')}-28`,
        type: index === 6 ? 'current' : 'actual', partial: index === 6, value: state.mode === 'missing' ? null : 50 + index * 10,
        metrics: summary
      }))
      const forecast = state.mode === 'normal' ? Array.from({ length: Number(filters.horizon ?? 3) }, (_, index) => ({ period: `2027-${String(index + 1).padStart(2, '0')}`, start: '2027-01-01', end: '2027-01-31', type: 'predicted', value: 120 + index * 10, lower_bound: 100 + index * 10, upper_bound: 140 + index * 10 })) : []
      return route.fulfill({ json: { data: {
        scope: { shop_id: admin ? Number(filters.shop_id) || null : 1, shop_name: shop.name, kind: admin && !filters.shop_id ? 'marketplace' : 'shop' },
        currency: filters.currency ?? 'USD', metric: selected, period: filters.period ?? 'monthly', from: filters.from, to: filters.to,
        summary, history, forecast, status: state.mode === 'normal' ? 'available' : state.mode === 'missing' ? 'missing_costs' : 'insufficient_history',
        model: state.mode === 'normal' ? 'linear_regression' : null, confidence: state.mode === 'normal' ? 'low' : 'unavailable', confidence_score: 25, data_points: history.length ? 6 : 0, backtest_mae: 5,
        growth_percentage: 10, growth_speed: 'slowing', trend: 'growing', forecast_total: forecast.length ? forecast.reduce((sum, point) => sum + point.value, 0) : null,
        warnings: ['recorded_expenses_only', 'partial_periods_excluded', ...(state.mode === 'missing' ? ['missing_cost_snapshots'] : [])], generated_at: '2026-10-26T12:00:00Z'
      } } })
    }
    if (path.includes('/expenses')) {
      const id = Number(path.split('/').at(-1))
      if (request.method() === 'DELETE') { state.deleted.push(id); state.expenses = state.expenses.filter(item => item.id !== id); return route.fulfill({ status: 204 }) }
      if (request.method() === 'POST' || request.method() === 'PUT') {
        if (state.saveFails) return route.fulfill({ status: 422, json: { message: 'Check this expense before saving.' } })
        const item = { ...request.postDataJSON(), id: Number.isFinite(id) ? id : 1, shop_id: 1, shop_name: shop.name }
        state.expenses = [item]
        return route.fulfill({ status: request.method() === 'POST' ? 201 : 200, json: { data: item } })
      }
      return route.fulfill({ json: { data: state.expenses, meta: { current_page: 1, last_page: 1, total: state.expenses.length } } })
    }
    return route.fulfill({ json: { data: [] } })
  })
  return state
}

test('analytics distinguishes actual, current and predicted data and applies forecast controls', async ({ page }, testInfo) => {
  const state = await setup(page)
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  await page.goto('/seller/shops/1/analytics')
  await expect(page.getByRole('heading', { name: 'Business Analytics', exact: true })).toBeVisible()
  await expect(page.getByRole('img', { name: 'Actual performance and forecast' })).toBeVisible()
  const table = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Historical comparison', exact: true }) })
  await expect(table.getByText('Actual', { exact: true })).toHaveCount(6)
  await expect(table.getByText('Current', { exact: true })).toHaveCount(1)
  await expect(table.getByText('Predicted', { exact: true })).toHaveCount(3)
  await page.getByRole('combobox', { name: 'Metric', exact: true }).selectOption('revenue')
  await page.getByRole('combobox', { name: 'Period', exact: true }).selectOption('yearly')
  await page.getByRole('combobox', { name: 'Forecast horizon', exact: true }).selectOption('1')
  await page.getByRole('button', { name: 'Apply filters', exact: true }).click()
  await expect(table.getByText('Predicted', { exact: true })).toHaveCount(1)
  expect(state.requests.at(-1)).toMatchObject({ metric: 'revenue', period: 'yearly', horizon: '1' })
  if (testInfo.project.name === 'desktop') {
    await page.setViewportSize({ width: 1918, height: 1025 })
    await page.screenshot({ path: testInfo.outputPath('business-analytics.png'), fullPage: true, animations: 'disabled' })
    for (const width of [360, 768, 1024, 1440, 1918]) {
      await page.setViewportSize({ width, height: 1025 })
      await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - innerWidth), { message: `overflow at ${width}` }).toBeLessThanOrEqual(1)
    }
  }
  await page.getByRole('button', { name: /Switch theme/ }).click()
  await page.locator('.dashboard-language button').click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'km')
  await expect(page.getByText('analytics.', { exact: false })).toHaveCount(0)
  expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1)
  await page.screenshot({ path: testInfo.outputPath('business-analytics-khmer-dark.png'), fullPage: true, animations: 'disabled' })
  expect(errors).toEqual([])
})

test('empty and unknown-cost history never shows invented profit estimates', async ({ page }) => {
  const state = await setup(page)
  state.mode = 'empty'
  await page.goto('/seller/shops/1/analytics')
  await expect(page.getByText('No financial history yet').first()).toBeVisible()
  await expect(page.getByText('A forecast needs at least three consecutive, complete periods with usable data.')).toBeVisible()
  state.mode = 'missing'
  await page.getByRole('button', { name: 'Refresh forecast', exact: true }).click()
  await expect(page.getByText(/Profit prediction is unavailable because historical item costs are missing/)).toBeVisible()
  await expect(page.locator('.dashboard-metric').nth(1)).toContainText('Unavailable')
  await expect(page.getByText('Predicted', { exact: true })).toHaveCount(0)
})

test('expense validation keeps the form open and saved edits refresh the financial totals', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/seller/shops/1/analytics')
  await expect(page.locator('.dashboard-metric')).toHaveCount(4)
  await page.getByRole('button', { name: 'Add expense', exact: true }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByLabel('Description', { exact: true }).fill('Courier cost <script>unsafe</script>')
  await dialog.getByLabel('Amount', { exact: true }).fill('12.50')
  state.saveFails = true
  await dialog.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText('Check this expense before saving.')
  state.saveFails = false
  await dialog.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(dialog).toHaveCount(0)
  await expect(page.locator('.dashboard-metric').nth(2)).toContainText('$12.50')
  await expect(page.locator('tbody script')).toHaveCount(0)
  await page.getByRole('button', { name: 'Edit expense: Courier cost <script>unsafe</script>', exact: true }).click()
  await dialog.getByLabel('Amount', { exact: true }).fill('25')
  await dialog.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(page.locator('.dashboard-metric').nth(2)).toContainText('$25.00')
  await page.getByRole('button', { name: 'Delete expense: Courier cost <script>unsafe</script>', exact: true }).click()
  expect(state.deleted).toEqual([])
  await dialog.getByRole('button', { name: 'Delete', exact: true }).click()
  await expect.poll(() => state.deleted).toEqual([1])
  await expect(page.locator('.dashboard-metric').nth(2)).toContainText('$0.00')
})

test('marketplace admins can select a shop and API failures offer a working retry', async ({ page }) => {
  const state = await setup(page, true)
  state.mode = 'error'
  await page.goto('/admin/analytics')
  await expect(page.getByRole('alert')).toContainText('Analytics are temporarily unavailable.')
  state.mode = 'normal'
  await page.getByRole('button', { name: 'Retry', exact: true }).click()
  await expect(page.locator('.dashboard-metric')).toHaveCount(4)
  await expect(page.getByRole('button', { name: 'Add expense', exact: true })).toBeDisabled()
  await page.getByRole('combobox', { name: 'Shop', exact: true }).selectOption('1')
  await page.getByRole('button', { name: 'Apply filters', exact: true }).click()
  await expect.poll(() => state.requests.at(-1)?.shop_id).toBe('1')
  await expect(page.getByRole('button', { name: 'Add expense', exact: true })).toBeEnabled()
})
