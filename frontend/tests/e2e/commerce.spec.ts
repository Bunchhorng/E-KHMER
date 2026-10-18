import { test, expect, type Page } from '@playwright/test'
import { installBarcodeCamera, type BarcodeCameraWindow } from './barcode-camera'
const shop = { id: 1, name: 'Test Shop', slug: 'test-shop', status: 'active', logo: null, banner: null, code: 'TEST', description: 'Our test catalog', email: 'seller@example.com', phone: '', address_line: '', city: 'Phnom Penh', province: '', postal_code: '', country: 'KH', bakong_account_id: 'seller@bank', bakong_merchant_name: 'Seller', bakong_merchant_city: 'Phnom Penh', rejection_reason: null, products_count: 1, rating_avg: 4.5, rating_count: 2 }
const user = { id: 1, name: 'Seller', email: 'seller@example.com', role: 'customer', managed_shops: [{ id: 1, name: shop.name, slug: shop.slug, role: 'owner' }] }
const meta = { current_page: 1, last_page: 1, per_page: 12, total: 1 }
const product = { id: 2, name: 'Blue Shirt', slug: 'blue-shirt', short_description: 'Cotton shirt', price: 10, compare_at_price: null, rating_avg: 4, rating_count: 1, is_featured: false, is_active: true, in_stock: true, cover_image: null, variants: [{ id: 3, sku: 'BLUE', name: 'Blue', price: 10, in_stock: true, attributes: [] }], brand: null, category: null }
async function setup(page: Page, authenticated = false) {
  await page.addInitScript(({ user, authenticated }) => {
    localStorage.setItem('app_locale', 'en')
    if (authenticated) { localStorage.setItem('access_token', 'browser-test-token'); localStorage.setItem('ekhmer_user', JSON.stringify(user)) }
  }, { user, authenticated })
  await page.route('**/api/**', async route => {
    const url = new URL(route.request().url()); const path = url.pathname
    if (!path.startsWith('/api/')) return route.continue()
    let data: unknown = { data: [] }
    if (path === '/api/auth/me') data = { data: user }
    else if (path === '/api/seller/shops') data = { data: [shop] }
    else if (path === '/api/seller/shops/1/settings') data = { data: shop }
    else if (path === '/api/shops') data = { data: [shop], meta }
    else if (path === '/api/shops/test-shop') data = { data: shop }
    else if (path === '/api/shops/test-shop/products') data = { data: url.searchParams.get('q') === 'missing' ? [] : [product], meta }
    else if (path === '/api/seller/shops/1/products') data = { data: [], meta: { ...meta, total: 0 } }
    else if (path === '/api/cart') data = { data: { id: 1, items: [], totals: { subtotal: 0, discount_amount: 0, tax_amount: 0, total: 0, items_count: 0 } } }
    await route.fulfill({ json: data })
  })
}
for (const area of [
  { name: 'store', paths: ['/shops', '/support', '/help/about', '/cart'] },
  { name: 'seller', paths: ['/seller/shops/1/products', '/seller/shops/1/products/new', '/seller/shops/1/settings', '/seller/application'] },
  { name: 'account', paths: ['/account/profile', '/account/password', '/account/returns', '/account/support'] },
  { name: 'admin', paths: ['/admin/products', '/admin/products/new', '/admin/shops', '/admin/shops/new', '/admin/shop-owners/new'] }
]) {
  test(`${area.name} pages keep the same content width`, async ({ page }, testInfo) => {
    await setup(page, area.name !== 'store')
    if (area.name === 'admin') {
      await page.route('**/api/auth/me', route => route.fulfill({ json: { data: { ...user, role: 'super_admin' } } }))
    }
    // Cover both capped desktop content and narrow mobile layouts.
    if (testInfo.project.name === 'desktop') await page.setViewportSize({ width: 2240, height: 1000 })
    let previous: { x: number; width: number } | undefined
    for (const path of area.paths) {
      await page.goto(path)
      await expect(page).toHaveURL(new RegExp(`${path}$`))
      const content = page.locator('main > .container-app > :first-child, main.container-app > :last-child').first()
      await expect(content).toBeVisible()
      if (area.name === 'seller') await expect(page.locator('#seller-content > .animate-pulse')).toHaveCount(0)
      const bounds = await content.boundingBox()
      expect(bounds, path).not.toBeNull()
      if (previous) {
        expect(Math.abs(bounds!.x - previous.x), `${path} left edge`).toBeLessThan(1)
        expect(Math.abs(bounds!.width - previous.width), `${path} content width`).toBeLessThan(1)
      }
      previous = bounds!
      expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth), `${path} horizontal overflow`).toBeLessThanOrEqual(1)
      if (area.name === 'store') {
        const header = await page.locator('header .container-app').last().boundingBox()
        const padding = await page.locator('header .container-app').last().evaluate(element => parseFloat(getComputedStyle(element).paddingLeft))
        expect(Math.abs(bounds!.x - header!.x - padding), `${path} header alignment`).toBeLessThan(1)
        expect(Math.abs(bounds!.width - header!.width + padding * 2), `${path} header width`).toBeLessThan(1)
      }
    }
  })
}

test('customers can discover a shop and search its own catalog', async ({ page }) => {
  await setup(page); await page.goto('/shops')
  await page.getByRole('link', { name: /Test Shop/ }).click()
  await expect(page).toHaveURL(/\/shops\/test-shop$/)
  await expect(page.getByRole('heading', { name: 'Test Shop' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Blue Shirt', exact: true })).toBeVisible()
  await page.getByRole('textbox', { name: 'Search this shop' }).fill('missing')
  await page.getByRole('textbox', { name: 'Search this shop' }).press('Enter')
  await expect(page.getByRole('heading', { name: /No products/ })).toBeVisible()
})
test('owners can save their public settings and Bakong recipient', async ({ page }) => {
  await setup(page, true); let submitted = ''
  await page.route('**/api/seller/shops/1/settings', async route => {
    if (route.request().method() === 'POST') submitted = route.request().postData() ?? ''
    await route.fulfill({ json: { data: shop } })
  })
  await page.goto('/seller/shops/1/settings')
  await page.getByLabel('Shop name *', { exact: true }).fill('Test Shop')
  await page.getByLabel('QR merchant name (English)').fill('Seller')
  await page.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(page.getByRole('status')).toContainText('Shop settings saved')
  expect(submitted).toContain('bakong_account_id')
  expect(submitted).toContain('seller@bank')
})
test('owners can create product options without losing their stock fields', async ({ page }) => {
  await setup(page, true); let payload = ''
  await page.route('**/api/seller/shops/1/products', async route => {
    if (route.request().method() === 'POST') { payload = route.request().postData() ?? ''; await route.fulfill({ status: 201, json: { data: { id: 2 } } }) }
    else await route.fulfill({ json: { data: [], meta: { ...meta, total: 0 } } })
  })
  await page.goto('/seller/shops/1/products/new')
  await page.getByLabel('Product name *').fill('Variant shirt')
  await page.getByRole('checkbox', { name: 'This product has options such as color or size' }).check()
  await page.getByRole('button', { name: 'Add variant', exact: true }).click()
  const options = page.locator('fieldset')
  await options.nth(0).getByLabel('SKU / product code').fill('BLUE-L')
  await options.nth(0).getByLabel('Option value').nth(0).fill('Blue')
  await options.nth(0).getByLabel('Option value').nth(1).fill('L')
  await options.nth(1).getByLabel('SKU / product code').fill('RED-S')
  await options.nth(1).getByLabel('Option value').nth(0).fill('Red')
  await options.nth(1).getByLabel('Option value').nth(1).fill('S')
  await page.getByRole('button', { name: 'Save product', exact: true }).first().click()
  await expect(page).toHaveURL(/\/seller\/shops\/1\/products$/)
  expect(payload).toContain('BLUE-L'); expect(payload).toContain('RED-S'); expect(payload).toContain('quantity')
})

test('owners can enter a printed barcode with purchase and selling prices', async ({ page }) => {
  await setup(page, true)
  let submitted = ''
  await page.route('**/api/seller/shops/1/products', async route => {
    if (route.request().method() === 'POST') {
      submitted = route.request().postData() ?? ''
      await route.fulfill({ status: 201, json: { data: { id: 2 } } })
    } else await route.fulfill({ json: { data: [], meta: { ...meta, total: 0 } } })
  })
  await page.goto('/seller/shops/1/products/new')
  await page.getByLabel('Product name *').fill('Barcoded item')
  const barcode = page.getByLabel('Product barcode', { exact: true })
  await barcode.fill('0012345678905')
  await barcode.press('Enter')
  await expect(page).toHaveURL(/\/products\/new$/)
  expect(submitted).toBe('')
  await page.getByLabel('Purchase price (USD)', { exact: true }).fill('4.25')
  await page.getByLabel('Selling price (USD) *', { exact: true }).fill('7.50')
  await page.getByRole('button', { name: 'Save product', exact: true }).first().click()
  await expect(page).toHaveURL(/\/seller\/shops\/1\/products$/)
  expect(submitted).toContain('0012345678905')
  expect(submitted).toContain('name="cost_price"')
  expect(submitted).toContain('4.25')
  expect(submitted).toContain('7.5')
})

test('owners can reload and edit existing barcodes and purchase prices', async ({ page }) => {
  await setup(page, true)
  let submitted = ''
  await page.route('**/api/seller/shops/1/products/2', async route => {
    if (route.request().method() === 'POST') submitted = route.request().postData() ?? ''
    await route.fulfill({ json: { data: { ...product, barcode: '00001234', cost_price: 3.5, sku: 'BLUE', gallery: [],
      variants: [{ ...product.variants[0], barcode: '00001234', cost_price: 3.5, is_default: true, is_active: true, quantity: 5, reserved_quantity: 0, image_id: null }] } } })
  })
  await page.goto('/seller/shops/1/products/2/edit')
  await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('00001234')
  await expect(page.getByLabel('Purchase price (USD)', { exact: true })).toHaveValue('3.5')
  await page.getByLabel('Product barcode', { exact: true }).fill('00005678')
  await page.getByLabel('Purchase price (USD)', { exact: true }).fill('3.75')
  await page.getByRole('button', { name: 'Save product', exact: true }).first().click()
  await expect(page).toHaveURL(/\/seller\/shops\/1\/products$/)
  expect(submitted).toContain('00005678')
  expect(submitted).toContain('3.75')
})

test('product variants have independent barcode and purchase price fields', async ({ page }) => {
  await setup(page, true)
  let submitted = ''
  await page.route('**/api/seller/shops/1/products', async route => {
    if (route.request().method() === 'POST') {
      submitted = route.request().postData() ?? ''
      await route.fulfill({ status: 201, json: { data: { id: 2 } } })
    } else await route.fulfill({ json: { data: [], meta: { ...meta, total: 0 } } })
  })
  await page.goto('/seller/shops/1/products/new')
  await page.getByLabel('Product name *').fill('Variant prices')
  await page.getByRole('checkbox', { name: 'This product has options such as color or size' }).check()
  await page.getByRole('button', { name: 'Add variant', exact: true }).click()
  const rows = page.locator('fieldset')
  for (const [index, barcode, purchase, selling] of [[0, '00001111', '3.25', '8'], [1, '00002222', '4.5', '10']] as const) {
    await rows.nth(index).getByLabel('Product barcode', { exact: true }).fill(barcode)
    await rows.nth(index).getByLabel('Purchase price (USD)', { exact: true }).fill(purchase)
    await rows.nth(index).getByLabel('Selling price (USD)', { exact: true }).fill(selling)
  }
  await page.getByRole('button', { name: 'Save product', exact: true }).first().click()
  await expect(page).toHaveURL(/\/seller\/shops\/1\/products$/)
  expect(submitted).toContain('"barcode":"00001111"')
  expect(submitted).toContain('"barcode":"00002222"')
  expect(submitted).toContain('"cost_price":3.25')
  expect(submitted).toContain('"cost_price":4.5')
})
test('camera barcode scanning decodes real EAN-8 bars and saves only after review', async ({ page }) => {
  await setup(page, true)
  await installBarcodeCamera(page)
  let submitted = ''
  await page.route('**/api/seller/shops/1/products', async route => {
    if (route.request().method() === 'POST') {
      submitted = route.request().postData() ?? ''
      await route.fulfill({ status: 201, json: { data: { id: 2 } } })
    } else await route.fulfill({ json: { data: [], meta: { ...meta, total: 0 } } })
  })
  await page.goto('/seller/shops/1/products/new')
  await page.getByLabel('Product name *').fill('Camera-scanned item')
  await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
  await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('00001236', { timeout: 15000 })
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByRole('status')).toContainText('Barcode scanned: 00001236')
  await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.stops)).toBe(1)
  const workers = await page.evaluate(() => ({ started: (window as BarcodeCameraWindow).barcodeCamera.workerStarts, stopped: (window as BarcodeCameraWindow).barcodeCamera.workerStops }))
  expect(workers).toEqual({ started: 1, stopped: 1 })
  expect(await page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.workerResults)).toContain('00001236')
  expect(submitted).toBe('')
  await page.getByRole('button', { name: 'Save product', exact: true }).first().click()
  await expect(page).toHaveURL(/\/seller\/shops\/1\/products$/)
  expect(submitted).toContain('00001236')
})

for (const position of ['top', 'bottom', 'vertical'] as const) {
  test(`camera barcode scanning reads real bars at the ${position} of the full frame`, async ({ page }) => {
    await setup(page, true)
    await installBarcodeCamera(page, 'barcode', { position })
    await page.goto('/seller/shops/1/products/new')
    await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
    await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('00001236', { timeout: 15000 })
    await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.workerStops)).toBe(1)
  })
}

test('camera barcode native fast path is single-flight and uses bounded frames', async ({ page }, testInfo) => {
  await setup(page, true)
  await installBarcodeCamera(page, 'blank', { native: 'success', highResolution: true })
  await page.goto('/seller/shops/1/products/new')
  await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
  await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('00001236', { timeout: 15000 })
  const state = await page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera)
  expect(state.nativeCalls).toBe(5)
  expect(state.nativeMaxInFlight).toBe(1)
  expect(state.workerStarts).toBe(0)
  expect(state.nativeFormats).not.toContain('qr_code')
  expect(Math.max(...state.nativeSizes[0]!)).toBeLessThanOrEqual(960)
  expect(state.nativeSizes.every(size => Math.max(...size) <= 1280)).toBe(true)
  await testInfo.attach('native-scan-cadence.json', { body: JSON.stringify({ intervalsMs: state.nativeTimes.slice(1).map((time, index) => time - state.nativeTimes[index]!), frameSizes: state.nativeSizes }), contentType: 'application/json' })
})

for (const native of ['reject', 'miss'] as const) {
  test(`camera barcode scanning falls back to real worker decoding when native results ${native}`, async ({ page }) => {
    await setup(page, true)
    await installBarcodeCamera(page, 'barcode', { native })
    await page.goto('/seller/shops/1/products/new')
    await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
    await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('00001236', { timeout: 15000 })
    const state = await page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera)
    expect(state.nativeCalls).toBe(native === 'miss' ? 6 : 1)
    expect(state.workerStarts).toBe(1)
    expect(state.workerStops).toBe(1)
  })
}

test('camera barcode scanning still works when workers are blocked', async ({ page }) => {
  await setup(page, true)
  await installBarcodeCamera(page, 'barcode', { workerBlocked: true })
  await page.goto('/seller/shops/1/products/new')
  await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
  await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('00001236', { timeout: 15000 })
  await expect(page.getByRole('dialog')).toHaveCount(0)
})

test('camera barcode scanning ignores a native result delivered after cancel', async ({ page }) => {
  await setup(page, true)
  await installBarcodeCamera(page, 'blank', { native: 'pending' })
  await page.goto('/seller/shops/1/products/new')
  await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
  await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.nativeCalls)).toBe(1)
  await page.getByRole('dialog').getByRole('button', { name: 'Cancel', exact: true }).click()
  await page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.releaseNative?.())
  await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.nativeInFlight)).toBe(0)
  await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('')
  await expect(page.getByRole('dialog')).toHaveCount(0)
})

test('camera barcode full-height guide and supported light controls work', async ({ page }, testInfo) => {
  await setup(page, true)
  await installBarcodeCamera(page, 'blank', { torch: true })
  await page.goto('/seller/shops/1/products/new')
  await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
  await expect(page.getByRole('dialog').getByRole('status')).toContainText('Looking for a barcode')
  const viewport = await page.getByTestId('barcode-viewport').boundingBox()
  const guide = await page.getByTestId('barcode-guide').boundingBox()
  expect(guide!.height / viewport!.height).toBeGreaterThan(0.85)
  expect(guide!.width / viewport!.width).toBeGreaterThan(0.85)
  await page.getByRole('button', { name: 'Turn light on', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Turn light off', exact: true })).toHaveAttribute('aria-pressed', 'true')
  await page.screenshot({ path: testInfo.outputPath('full-frame-barcode-scanner.png') })
  await page.getByRole('button', { name: 'Turn light off', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Turn light on', exact: true })).toHaveAttribute('aria-pressed', 'false')
  const state = await page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera)
  expect(state.constraints).toHaveLength(3)
  expect(state.constraints.every(constraints => JSON.stringify(constraints.width) === JSON.stringify({ ideal: 1920 }) && JSON.stringify(constraints.frameRate) === JSON.stringify({ ideal: 30 }))).toBe(true)
  expect(state.constraints.map(constraints => constraints.advanced)).toEqual([
    [{ focusMode: 'continuous' }],
    [{ focusMode: 'continuous' }, { torch: true }],
    [{ focusMode: 'continuous' }, { torch: false }]
  ])
  await page.getByRole('dialog').getByRole('button', { name: 'Cancel', exact: true }).click()
  await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.workerStops)).toBe(1)
})

test('camera barcode scanning fills only the selected variant', async ({ page }) => {
  await setup(page, true)
  await installBarcodeCamera(page)
  await page.goto('/seller/shops/1/products/new')
  await page.getByRole('checkbox', { name: 'This product has options such as color or size' }).check()
  await page.getByRole('button', { name: 'Add variant', exact: true }).click()
  const rows = page.locator('fieldset')
  await rows.nth(0).getByLabel('Product barcode', { exact: true }).fill('KEEP-FIRST')
  await rows.nth(1).getByRole('button', { name: 'Scan barcode', exact: true }).click()
  await expect(rows.nth(1).getByLabel('Product barcode', { exact: true })).toHaveValue('00001236', { timeout: 15000 })
  await expect(rows.nth(0).getByLabel('Product barcode', { exact: true })).toHaveValue('KEEP-FIRST')
  await expect(page.getByLabel('Product barcode', { exact: true }).last()).toHaveValue('')
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.stops)).toBe(1)
})

test('camera barcode scanning explains permission denial and supports retry or manual input', async ({ page }, testInfo) => {
  await setup(page, true)
  await installBarcodeCamera(page, 'denied')
  await page.goto('/seller/shops/1/products/new')
  const barcode = page.getByLabel('Product barcode', { exact: true })
  await barcode.fill('KEEP-ORIGINAL')
  await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('Camera access was blocked')
  await page.evaluate(() => { (window as BarcodeCameraWindow).barcodeCamera.denied = false })
  await page.getByRole('button', { name: 'Retry', exact: true }).click()
  await expect(page.getByRole('dialog').getByRole('status')).toContainText('Looking for a barcode', { timeout: 15000 })
  await page.screenshot({ path: testInfo.outputPath('barcode-scanner.png') })
  await page.getByRole('dialog').getByRole('button', { name: 'Cancel', exact: true }).click()
  await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.stops)).toBe(1)
  await expect(barcode).toHaveValue('KEEP-ORIGINAL')
  await barcode.fill('00005678')
  await expect(barcode).toHaveValue('00005678')
})

test('closing the barcode scanner stops a late camera permission response', async ({ page }) => {
  await setup(page, true)
  await installBarcodeCamera(page, 'pending')
  await page.goto('/seller/shops/1/products/new')
  await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
  await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.calls)).toBe(1)
  await page.getByRole('dialog').getByRole('button', { name: 'Close', exact: true }).click()
  await page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.release?.())
  await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.stops)).toBe(1)
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('')
})

test('unmounting a variant barcode scanner stops its camera', async ({ page }) => {
  await setup(page, true)
  await installBarcodeCamera(page, 'blank')
  await page.goto('/seller/shops/1/products/new')
  await page.getByRole('checkbox', { name: 'This product has options such as color or size' }).check()
  await page.locator('fieldset').getByRole('button', { name: 'Scan barcode', exact: true }).click()
  await expect(page.getByRole('dialog').getByRole('status')).toContainText('Looking for a barcode', { timeout: 15000 })
  // Simulate a parent state change while the teleported scanner dialog is open.
  await page.getByRole('checkbox', { name: 'This product has options such as color or size' }).evaluate(element => (element as HTMLInputElement).click())
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect.poll(() => page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.stops)).toBe(1)
})

for (const [mode, message] of [['missing', 'No camera was found'], ['insecure', 'Camera scanning requires HTTPS']] as const) {
  test(`camera barcode scanning handles a ${mode} camera context`, async ({ page }) => {
    await setup(page, true)
    await installBarcodeCamera(page, mode)
    await page.goto('/seller/shops/1/products/new')
    await page.getByRole('button', { name: 'Scan barcode', exact: true }).click()
    await expect(page.getByRole('alert')).toContainText(message)
    await page.getByRole('dialog').getByRole('button', { name: 'Cancel', exact: true }).click()
    await page.getByLabel('Product barcode', { exact: true }).fill('MANUAL')
    await expect(page.getByLabel('Product barcode', { exact: true })).toHaveValue('MANUAL')
    if (mode === 'insecure') expect(await page.evaluate(() => (window as BarcodeCameraWindow).barcodeCamera.calls)).toBe(0)
  })
}

test('product photos support upload, cover choice, and removal', async ({ page }) => {
  await setup(page, true); await page.goto('/seller/shops/1/products/new')
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jB9kAAAAASUVORK5CYII=', 'base64')
  await page.locator('input[type=file][multiple]').setInputFiles([{ name: 'one.png', mimeType: 'image/png', buffer: png }, { name: 'two.png', mimeType: 'image/png', buffer: png }])
  await expect(page.getByAltText('Product photo 1')).toBeVisible()
  await page.getByRole('button', { name: 'Make cover' }).click()
  await page.getByRole('button', { name: 'Remove photo 2' }).click()
  await expect(page.getByAltText('Product photo 2')).toHaveCount(0)
})
test('camera capture adds a product photo', async ({ page }) => {
  await setup(page, true); await page.goto('/seller/shops/1/products/new')
  await page.getByRole('button', { name: 'Take a photo', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Capture photo', exact: true })).toBeEnabled({ timeout: 15000 })
  await page.getByRole('button', { name: 'Capture photo', exact: true }).click()
  await expect(page.getByAltText('Product photo 1')).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(0)
})
test('support success is shown only after the ticket is saved', async ({ page }) => {
  await setup(page)
  await page.route('**/api/support', route => route.fulfill({ status: 201, json: { data: { id: 42, status: 'open' } } }))
  await page.goto('/support'); await page.getByLabel('Email', { exact: true }).fill('buyer@example.com')
  await page.getByLabel('Subject').fill('Order question')
  await page.getByLabel('Message').fill('Where can I find my order?')
  await page.getByRole('button', { name: 'Send request' }).click()
  await expect(page.getByRole('status')).toContainText('#42')
})
test('Bakong checkout displays independent seller payment status', async ({ page }) => {
  await setup(page)
  let paid = false
  await page.route('**/api/checkout/ORDER-1/bakong', route => route.fulfill({ json: { data: [
    { id: 1, shop_name: 'First Seller', amount: 10, currency: 'USD', status: paid ? 'paid' : 'pending', qr_payload: 'first-qr', expires_at: new Date(Date.now() + 600000).toISOString() },
    { id: 2, shop_name: 'Second Seller', amount: 20, currency: 'USD', status: 'pending', qr_payload: 'second-qr', expires_at: new Date(Date.now() + 600000).toISOString() }
  ] } }))
  await page.route('**/api/checkout/ORDER-1/bakong/verify', async route => { paid = true; await route.fulfill({ json: { data: { order_number: 'ORDER-1', status: 'pending', payment_status: 'partial' } } }) })
  await page.goto('/checkout/ORDER-1/payment')
  await expect(page.getByAltText('Bakong payment QR for First Seller')).toBeVisible()
  await page.getByRole('button', { name: 'Check payment', exact: true }).click()
  await expect(page.getByText('Payment verified', { exact: true })).toBeVisible()
  await expect(page.getByAltText('Bakong payment QR for Second Seller')).toBeVisible()
  await expect(page).toHaveURL(/\/payment$/)
})
test('customer sessions cannot open admin screens', async ({ page }) => {
  await setup(page, true); await page.goto('/admin/support'); await expect(page).toHaveURL(/\/$/)
})

test('sellers see the order-bound bank refund memo and verify the transfer', async ({ page }) => {
  await setup(page, true)
  let refunded = false
  await page.route(/\/api\/seller\/shops\/1\/payments(?:\?.*)?$/, route => route.fulfill({ json: { data: [{ id: 1, order_number: 'ORDER-1', amount: 11, refunded_amount: refunded ? 11 : 0, currency: 'USD', status: refunded ? 'refunded' : 'refund_required', transaction_hash: 'a'.repeat(64), refund_memo: 'REFUND-ORDER-1-CANCEL', paid_at: null }], meta } }))
  await page.route('**/api/seller/shops/1/payments/1/refund', async route => {
    expect(route.request().postDataJSON()).toEqual({ reference: 'c'.repeat(64) })
    refunded = true
    await route.fulfill({ json: { data: { message: 'Refund verified.' } } })
  })
  await page.goto('/seller/shops/1/payments')
  await expect(page.getByText(/copy this exact bank transfer memo: REFUND-ORDER-1-CANCEL/)).toBeVisible()
  await page.getByLabel('Refund transaction hash / cash receipt reference').fill('c'.repeat(64))
  await page.getByRole('button', { name: 'Verify completed refund' }).click()
  await expect(page.getByText(/· Refund verified$/)).toBeVisible()
})

test('checkout keeps delivery scoped to each shop and safely retries an expired attempt', async ({ page }) => {
  await setup(page, true)
  await page.route('**/api/cart', route => route.fulfill({ json: { data: { id: 1,
    items: [1, 2].map(id => ({ id, quantity: 1, variant: { id, sku: `ITEM-${id}`, name: 'Default', price: 10, attributes: [], product: { id, name: `Item ${id}`, slug: `item-${id}`, cover_image: null, shop_id: id, shop_name: id === 1 ? 'First Seller' : 'Second Seller' } } })),
    totals: { subtotal: 20, tax_amount: 2, discount_amount: 0, total: 22, items_count: 2 } } } }))
  await page.route('**/api/addresses', route => route.fulfill({ json: { data: [{ id: 1, label: 'Home', full_name: 'Buyer', phone: '012345678', address_line1: 'Street 1', city: 'Phnom Penh', state: 'Phnom Penh', postal_code: '12000', country: 'KH', is_default: true }] } }))
  await page.route('**/api/shipping-methods', route => route.fulfill({ json: { data: [
    { id: 11, shop_id: 1, name: 'First delivery', price: 2, estimated_days_min: 1, estimated_days_max: 2 },
    { id: 22, shop_id: 2, name: 'Second delivery', price: 5, estimated_days_min: 1, estimated_days_max: 2 }
  ] } }))
  const keys: Array<string | null> = []
  await page.route('**/api/checkout', async route => {
    expect(route.request().postDataJSON()).toMatchObject({ payment_method: 'bakong', shipping_methods: { 1: 11, 2: 22 } })
    keys.push(await route.request().headerValue('idempotency-key'))
    await route.fulfill({ status: 201, json: { data: { order_number: 'NEW-ORDER', status: keys.length === 1 ? 'cancelled' : 'pending', payment_status: 'unpaid' } } })
  })
  await page.route('**/api/checkout/NEW-ORDER/bakong', route => route.fulfill({ json: { data: [{ id: 1, shop_name: 'First Seller', amount: 13, currency: 'USD', status: 'pending', qr_payload: 'first-qr', expires_at: new Date(Date.now() + 600000).toISOString() }] } }))
  await page.goto('/checkout')
  await page.getByRole('button', { name: 'Continue to Delivery' }).click()
  const firstShop = page.getByRole('group', { name: 'First Seller' })
  await expect(firstShop.getByText('First delivery')).toBeVisible()
  await expect(firstShop.getByText('Second delivery')).toHaveCount(0)
  await page.getByRole('button', { name: 'Continue to Payment' }).click()
  await page.getByRole('main').getByText('Bakong / KHQR', { exact: true }).click()
  await page.getByRole('button', { name: 'Review Order' }).click()
  await page.getByRole('button', { name: /Place Order/ }).click()
  await expect(page.getByRole('alert')).toContainText('expired or was cancelled')
  await expect(page).toHaveURL(/\/checkout$/)
  await page.getByRole('button', { name: /Place Order/ }).click()
  await expect(page).toHaveURL(/\/checkout\/NEW-ORDER\/payment$/)
  await expect(page.getByAltText('Bakong payment QR for First Seller')).toBeVisible()
  expect(keys[0]).toBeTruthy()
  expect(keys[1]).toBeTruthy()
  expect(keys[0]).not.toEqual(keys[1])
})

