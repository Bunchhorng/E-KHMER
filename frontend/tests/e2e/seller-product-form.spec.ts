import { test, expect, type Page } from '@playwright/test'

const shop = { id: 1, name: 'E-KHMER Official Store', slug: 'e-khmer', status: 'active', logo: null }
const user = { id: 1, name: 'Super Admin', email: 'seller@example.test', role: 'customer', managed_shops: [{ ...shop, role: 'owner' }] }
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jB9kAAAAASUVORK5CYII=', 'base64')

async function setup(page: Page, shipping = false) {
  const submissions: string[] = []
  const state = { failSave: false }
  await page.addInitScript(({ user }) => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'light')
    localStorage.setItem('access_token', 'browser-test-token')
    localStorage.setItem('ekhmer_user', JSON.stringify(user))
  }, { user })
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    let data: unknown = { data: [] }
    if (path === '/api/auth/me') data = { data: user }
    if (path === '/api/seller/shops') data = { data: [shop] }
    if (path === '/api/categories') data = { data: [{ id: 1, name: 'Clothing', children: [{ id: 2, name: 'T-shirts', children: [] }] }] }
    if (path === '/api/brands') data = { data: [{ id: 1, name: 'E-KHMER' }] }
    if (path === '/api/seller/shops/1/shipping-methods') data = { data: shipping ? [{ id: 1, name: 'Standard delivery', is_active: true, price: 2 }] : [] }
    if (path === '/api/seller/shops/1/products') {
      if (route.request().method() === 'POST') {
        submissions.push(route.request().postData() ?? '')
        if (state.failSave) return route.fulfill({ status: 422, json: { message: 'Please check the product name.', errors: { name: ['This product name is unavailable.'] } } })
        return route.fulfill({ status: 201, json: { data: { id: 10 } } })
      }
      data = { data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }
    }
    if (path === '/api/seller/shops/1/products/10') data = { data: { id: 10, name: 'Existing product', sku: 'EXISTING', barcode: '', price: 9, cost_price: null, compare_at_price: null, short_description: '', description: '<p>Safe <strong>description</strong></p><img src="x" onerror="window.editorUnsafe=true"><script>window.editorUnsafe=true</script>', rating_avg: 0, rating_count: 0, is_active: false, gallery: [], variants: [{ id: 1, name: 'Default', sku: 'EXISTING', price: 9, quantity: 5, reserved_quantity: 1, is_active: true, attributes: [], image_id: null }], brand: null, category: null, weight: null } }
    await route.fulfill({ json: data })
  })
  await page.goto('/seller/shops/1/products/new')
  await expect(page.getByRole('heading', { name: 'Create product', exact: true })).toBeVisible()
  return { submissions, state }
}

test('product setup progress and preview follow the entered details and cover photo', async ({ page }) => {
  await setup(page, true)
  await expect(page.getByRole('button', { name: 'Fill in product details: 0/4' })).toBeVisible()
  await page.getByLabel('Product name *').fill('Cotton T-shirt')
  await page.getByLabel('Category', { exact: true }).selectOption('2')
  await page.getByLabel('Short description', { exact: true }).fill('Soft everyday cotton')
  const description = page.getByRole('textbox', { name: 'Full description', exact: true })
  await description.fill('Soft cotton for everyday wear.')
  await description.press('ControlOrMeta+A')
  await page.getByRole('button', { name: 'Bold', exact: true }).click()
  await expect(description.locator('b, strong')).toHaveText('Soft cotton for everyday wear.')
  await expect(page.getByRole('button', { name: 'Fill in product details: 4/4' })).toBeVisible()
  await page.getByLabel('Selling price (USD) *', { exact: true }).fill('15.50')
  await page.getByLabel('Purchase price (USD)', { exact: true }).fill('7.25')
  await page.getByLabel('Starting stock *', { exact: true }).fill('12')
  await page.getByLabel('Weight (kg)', { exact: true }).fill('0.25')
  await expect(page.getByRole('button', { name: 'Set price and stock: 3/3' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Configure shipping: 2/2' })).toBeVisible()
  await page.locator('input[type=file][multiple]').setInputFiles({ name: 'cover.png', mimeType: 'image/png', buffer: png })
  await expect(page.getByRole('button', { name: 'Add product photos: 1/1' })).toBeVisible()
  await expect(page.locator('.form-preview')).toContainText('Cotton T-shirt')
  await expect(page.locator('.form-preview')).toContainText('$15.50')
  await expect(page.locator('.form-preview img')).toHaveAttribute('src', /^blob:/)
  await page.getByRole('button', { name: 'Fill in product details: 4/4' }).click()
  await expect(page.getByLabel('Product name *')).toBeFocused()
})

test('saving a draft submits hidden visibility without publishing', async ({ page }) => {
  const { submissions } = await setup(page)
  await page.getByRole('button', { name: 'Save draft', exact: true }).click()
  expect(submissions).toHaveLength(0)
  await page.getByLabel('Product name *').fill('Draft shirt')
  await page.getByRole('button', { name: 'Save draft', exact: true }).click()
  await expect(page).toHaveURL(/\/seller\/shops\/1\/products$/)
  expect(submissions).toHaveLength(1)
  expect(submissions[0]).toMatch(/name="is_active"\r?\n\r?\n0/)
})

test('publish errors preserve the form and allow retry with live visibility', async ({ page }) => {
  const { submissions, state } = await setup(page)
  state.failSave = true
  await page.getByLabel('Product name *').fill('Cotton T-shirt')
  await page.getByRole('button', { name: 'Publish product', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('This product name is unavailable.')
  await expect(page.getByLabel('Product name *')).toHaveValue('Cotton T-shirt')
  await expect(page.getByLabel('Product name *')).toHaveAttribute('aria-invalid', 'true')
  state.failSave = false
  await page.getByLabel('Visibility', { exact: true }).selectOption({ label: 'Hidden' })
  await expect(page.getByRole('button', { name: 'Save product', exact: true })).toBeVisible()
  await expect(page.locator('#product-visibility-help')).toContainText('not visible to customers')
  await page.getByLabel('Visibility', { exact: true }).selectOption({ label: 'Live' })
  await page.getByRole('button', { name: 'Publish product', exact: true }).click()
  await expect(page).toHaveURL(/\/seller\/shops\/1\/products$/)
  expect(submissions).toHaveLength(2)
  expect(submissions[1]).toMatch(/name="is_active"\r?\n\r?\n1/)
})

test('dropping photos uses the same validation as uploading', async ({ page }) => {
  await setup(page)
  const transfer = await page.evaluateHandle(base64 => {
    const bytes = Uint8Array.from(atob(base64), character => character.charCodeAt(0))
    const data = new DataTransfer()
    data.items.add(new File([bytes], 'dropped.png', { type: 'image/png' }))
    return data
  }, png.toString('base64'))
  await page.locator('.photo-dropzone').dispatchEvent('drop', { dataTransfer: transfer })
  await expect(page.getByAltText('Product photo 1')).toBeVisible()
  await page.locator('input[type=file][multiple]').setInputFiles({ name: 'bad.txt', mimeType: 'text/plain', buffer: Buffer.from('not a photo') })
  await expect(page.getByRole('alert')).toContainText('Choose JPG, PNG, WebP or GIF')
  await expect(page.getByAltText('Product photo 2')).toHaveCount(0)
})

test('stored description formatting cannot execute active markup in the editor', async ({ page }) => {
  await setup(page)
  await page.goto('/seller/shops/1/products/10/edit')
  const editor = page.getByRole('textbox', { name: 'Full description', exact: true })
  await expect(editor).toContainText('Safe description')
  await expect(editor.locator('img, script')).toHaveCount(0)
  await expect(editor.locator('strong')).toHaveText('description')
  expect(await page.evaluate(() => (window as Window & { editorUnsafe?: boolean }).editorUnsafe)).toBeUndefined()
})

test('the product form stays within the viewport in both themes and languages', async ({ page }, testInfo) => {
  await setup(page)
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  if (testInfo.project.name === 'desktop') {
    await page.setViewportSize({ width: 1672, height: 941 })
    await page.screenshot({ path: testInfo.outputPath('create-product-desktop.png'), animations: 'disabled' })
    for (const width of [360, 390, 768, 1024, 1280, 1440, 1672]) {
      await page.setViewportSize({ width, height: 941 })
      expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth), `overflow at ${width}`).toBeLessThanOrEqual(1)
    }
  } else {
    await page.screenshot({ path: testInfo.outputPath('create-product-mobile.png'), fullPage: true, animations: 'disabled' })
  }
  await page.getByRole('button', { name: /Switch theme/ }).click()
  await expect.poll(() => page.locator('.form-heading h1').evaluate(element => getComputedStyle(element).color)).toBe('rgb(240, 245, 255)')
  await page.screenshot({ path: testInfo.outputPath('create-product-dark.png'), animations: 'disabled' })
  await page.locator('.seller-language button').click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'km')
  expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1)
  await page.screenshot({ path: testInfo.outputPath('create-product-khmer.png'), animations: 'disabled' })
  expect(errors).toEqual([])
})
