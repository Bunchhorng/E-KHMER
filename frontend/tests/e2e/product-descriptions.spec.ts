import { expect, test, type Page, type Locator } from '@playwright/test'
import type { ProductDetail } from '../../src/api/catalog'

const formatted = '<h3>Features</h3><p><strong>Premium Headphones</strong> with <em>clear sound</em> and <u>warranty</u>.</p><ul><li>Bluetooth 5.3</li><li>40-hour battery</li></ul><ol><li>Charge</li><li>Play</li></ol><blockquote>Made for everyday listening.</blockquote>'
const shop = { id: 1, name: 'Test Shop', slug: 'test-shop', status: 'active', logo: null }
function product(description: string | null): ProductDetail {
  const variants: (ProductDetail['variants'][number] & { quantity: number; reserved_quantity: number })[] = [
    { id: 10, name: 'Default', sku: 'RICH-10', price: 50, compare_at_price: null, is_active: true, is_default: true,
      in_stock: true, available_quantity: 10, attributes: [], quantity: 10, reserved_quantity: 0 },
  ]
  return { id: 10, slug: 'rich-product', name: 'Rich product', sku: 'RICH-10', price: 50, compare_at_price: null,
    description, short_description: 'Plain summary', cover_image: null, gallery: [], attributes: [],
    meta_title: null, meta_description: null, weight: null, rating_avg: 4.8, rating_count: 2,
    is_active: true, is_featured: false, in_stock: true,
    brand: { id: 1, slug: 'acme', name: 'Acme' }, category: { id: 1, slug: 'audio', name: 'Audio' },
    variants,
  }
}

const failures = new WeakMap<Page, string[]>()
test.beforeEach(({ page }) => {
  const errors: string[] = []
  failures.set(page, errors)
  page.on('pageerror', error => errors.push(error.message))
  page.on('dialog', async dialog => { errors.push(`Unexpected dialog: ${dialog.message()}`); await dialog.dismiss() })
})
test.afterEach(async ({ page }) => {
  expect(failures.get(page)).toEqual([])
  expect(await page.evaluate(() => Reflect.get(window, 'descriptionXss'))).not.toBe(true)
})

async function setup(page: Page, description: string | null = formatted, options: { role?: 'seller' | 'admin'; dark?: boolean } = {}) {
  const user = { id: 1, name: 'Owner', email: 'owner@example.test', role: options.role === 'admin' ? 'super_admin' : 'customer', managed_shops: [{ ...shop, role: 'owner' }] }
  await page.addInitScript(({ user, authenticated, dark }) => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', dark ? 'dark' : 'light')
    if (authenticated) {
      localStorage.setItem('access_token', 'description-test-token')
      localStorage.setItem('ekhmer_user', JSON.stringify(user))
    }
  }, { user, authenticated: Boolean(options.role), dark: options.dark })
  const state = { product: product(description), submissions: [] as string[], remoteRequests: [] as string[] }
  await page.route('https://evil.example/**', async route => { state.remoteRequests.push(route.request().url()); await route.abort() })
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    const method = route.request().method()
    let json: unknown = { data: [] }
    if (path === '/api/auth/me') json = { data: user }
    else if (path === '/api/cart') json = { data: { id: 1, items: [], totals: { subtotal: 0, tax_amount: 0, discount_amount: 0, total: 0, items_count: 0 } } }
    else if (path === '/api/seller/shops') json = { data: [shop] }
    else if (path === '/api/categories' || path === '/api/admin/categories') json = { data: [{ id: 1, slug: 'audio', name: 'Audio', children: [] }] }
    else if (path === '/api/brands' || path === '/api/admin/brands') json = { data: [{ id: 1, slug: 'acme', name: 'Acme' }] }
    else if (path === '/api/catalog/facets') json = { data: { categories: [], brands: [], colors: [], sizes: [] } }
    else if (path === '/api/catalog/products/rich-product' || path === '/api/admin/products/10') json = { data: state.product }
    else if (/^\/api\/seller\/shops\/1\/products(?:\/10)?$/.test(path)) {
      if (method !== 'GET') {
        const body = route.request().postData() ?? ''
        const description = body.match(/name="description"\r?\n\r?\n([\s\S]*?)\r?\n--/)?.[1] ?? ''
        state.submissions.push(description)
        state.product.description = description
        return route.fulfill({ status: path.endsWith('/10') ? 200 : 201, json: { data: state.product } })
      }
      json = path.endsWith('/10') ? { data: state.product } : { data: [state.product], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }
    } else if (path === '/api/catalog/products') json = { data: [state.product], meta: { current_page: 1, last_page: 1, per_page: 12, total: 1, price_range: { min: 50, max: 50 } } }
    await route.fulfill({ json })
  })
  return state
}
const description = (page: Page) => page.locator('main [data-product-description]')
async function assertFormatting(content: Locator) {
  await expect(content.getByRole('heading', { name: 'Features', exact: true })).toBeVisible()
  await expect(content.locator('strong')).toHaveText('Premium Headphones')
  await expect(content.locator('ul > li')).toHaveText(['Bluetooth 5.3', '40-hour battery'])
  await expect(content.locator('ol > li')).toHaveText(['Charge', 'Play'])
  await expect(content.locator('ul')).toHaveCSS('list-style-type', 'disc')
  await expect(content.locator('ol')).toHaveCSS('list-style-type', 'decimal')
}

test('storefront renders approved formatting as DOM instead of literal HTML', async ({ page }) => {
  await setup(page)
  await page.goto('/product/rich-product')
  await assertFormatting(description(page))
  await expect(description(page)).not.toContainText('<h3>')
  await expect(description(page).locator('em')).toHaveCSS('font-style', 'italic')
  await expect(description(page).locator('strong')).toHaveCSS('font-weight', '700')
  await expect(description(page).locator('u')).toHaveCSS('text-decoration-line', 'underline')
  await expect(page.getByRole('main')).not.toContainText('<strong>')
})

for (const [name, payload] of [
  ['script', '<script>window.descriptionXss=true;alert("xss")</script>'],
  ['event handler', '<img src=x onerror="window.descriptionXss=true"><p onclick="window.descriptionXss=true" style="position:fixed" class="overlay">Click</p>'],
  ['JavaScript URL', '<a href="jav&#x61;script&#58;window.descriptionXss=true">Bad link</a>'],
  ['SVG', '<svg onload="window.descriptionXss=true"><a xlink:href="javascript:alert(1)">Bad</a></svg>'],
  ['iframe and object', '<iframe src="https://evil.example/frame" srcdoc="<script>window.descriptionXss=true</script>"></iframe><object data="https://evil.example/object"></object><embed src="https://evil.example/embed">'],
  ['malformed nested markup', '<math><mtext><table><mglyph><style><!--</style><img title="--><img src=x onerror=window.descriptionXss=true>">'],
]) {
  test(`${name} in an unsanitized legacy API fixture cannot execute`, async ({ page }) => {
    const state = await setup(page, '<p><strong>Safe description</strong></p>' + payload)
    await page.goto('/product/rich-product')
    await expect(description(page).locator('strong')).toHaveText('Safe description')
    await expect(description(page).locator('script,img,svg,math,iframe,object,embed,style,form,input,button')).toHaveCount(0)
    await expect(description(page).locator('[onclick],[onerror],[onload],[style],[class],[id],[srcdoc]')).toHaveCount(0)
    if (name === 'JavaScript URL') {
      const link = description(page).getByText('Bad link', { exact: true })
      await expect(link).not.toHaveAttribute('href')
      await link.click()
      await expect(page).toHaveURL(/\/product\/rich-product$/)
    }
    if (name === 'event handler') await description(page).getByText('Click', { exact: true }).click()
    expect(state.remoteRequests).toEqual([])
    await expect(page.getByRole('button', { name: 'Buy Now', exact: true })).toBeEnabled()
  })
}

test('safe links retain their destinations and attributes are restricted to the link policy', async ({ page }) => {
  await setup(page, '<p><a href="https://example.com/info" target="_blank" title="Details" rel="opener">Manufacturer</a> <a href="http://example.com">HTTP</a> <a href="mailto:help@example.com">Email</a> <a href="/shop?brand=acme">Catalog</a> <a href="data:text/html,bad">Data</a> <a href="vbscript:bad">VB</a> <a href="ftp://example.com">FTP</a></p>')
  await page.goto('/product/rich-product')
  const content = description(page)
  await expect(content.getByText('Manufacturer', { exact: true })).toHaveAttribute('href', 'https://example.com/info')
  await expect(content.getByText('Manufacturer', { exact: true })).toHaveAttribute('title', 'Details')
  await expect(content.locator('[target]')).toHaveCount(0)
  for (const link of await content.locator('a').all()) await expect(link).toHaveAttribute('rel', 'noopener noreferrer')
  await expect(content.getByText('Email', { exact: true })).toHaveAttribute('href', 'mailto:help@example.com')
  for (const label of ['Data', 'VB', 'FTP']) await expect(content.getByText(label, { exact: true })).not.toHaveAttribute('href')
  await content.getByText('Catalog', { exact: true }).click()
  await expect(page).toHaveURL(/\/shop\?brand=acme$/)
})

for (const empty of [null, '', '<p></p>', '<p><br>&nbsp;</p>']) {
  test(`empty description ${JSON.stringify(empty)} has no literal null or empty rich block`, async ({ page }) => {
    await setup(page, empty)
    await page.goto('/product/rich-product')
    await expect(page.getByRole('button', { name: 'Buy Now', exact: true })).toBeVisible()
    await expect(description(page)).toHaveCount(0)
    await expect(page.getByRole('heading', { name: 'Key Features', exact: true })).toHaveCount(0)
    await expect(page.getByRole('main')).not.toContainText('undefined')
  })
}

test('Khmer mixed text entities and old plain-text descriptions remain readable', async ({ page }) => {
  const state = await setup(page, '<p><strong>ផលិតផលគុណភាពខ្ពស់</strong> Tom &amp; Jerry 123 ©</p>')
  await page.goto('/product/rich-product')
  await expect(description(page).locator('strong')).toHaveText('ផលិតផលគុណភាពខ្ពស់')
  await expect(description(page)).toContainText('Tom & Jerry 123 ©')
  await expect(description(page)).not.toContainText('&amp;')
  state.product.description = 'Legacy plain text & useful details'
  await page.reload()
  await expect(description(page)).toHaveText('Legacy plain text & useful details')
})

test('long descriptions and links wrap on desktop tablet and mobile without page overflow', async ({ page }) => {
  const long = 'longword'.repeat(350)
  await setup(page, `<h3>Features</h3><p>${long}</p><ul><li><a href="https://example.com/${long}">${long}</a></li></ul>`)
  await page.goto('/product/rich-product')
  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 })
    await expect(description(page)).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true)
  }
})

test('dark mode typography is theme-owned and removes seller font and color styles', async ({ page }) => {
  await setup(page, formatted + '<p style="color:black;font-family:Comic Sans MS">Readable</p><a href="https://example.com">Manufacturer</a>', { dark: true })
  await page.goto('/product/rich-product')
  await expect(page.locator('html')).toHaveClass(/dark/)
  await assertFormatting(description(page))
  await expect(description(page).locator('[style]')).toHaveCount(0)
  await expect(description(page).getByText('Manufacturer', { exact: true })).toHaveCSS('color', 'rgb(147, 197, 253)')
  const color = await description(page).getByText('Readable', { exact: true }).evaluate(element => getComputedStyle(element).color)
  expect(color).not.toBe('rgb(0, 0, 0)')
})

test('seller editor and live preview protect legacy input and repeated edit-save does not escape formatting', async ({ page }) => {
  const state = await setup(page, formatted + '<p>Tom &amp; Jerry ផលិតផល</p><img src=x onerror="window.descriptionXss=true">', { role: 'seller' })
  for (let edit = 0; edit < 2; edit++) {
    await page.goto('/seller/shops/1/products/10/edit')
    const editor = page.getByRole('textbox', { name: 'Full description', exact: true })
    await expect(editor.locator('img')).toHaveCount(0)
    await assertFormatting(page.locator('.form-preview [data-product-description]'))
    await editor.evaluate(element => element.dispatchEvent(new InputEvent('input', { bubbles: true })))
    await page.getByRole('button', { name: 'Save product', exact: true }).click()
    await expect(page).toHaveURL(/\/seller\/shops\/1\/products$/)
    expect(state.submissions[edit]).toContain('<strong>Premium Headphones</strong>')
    expect(state.submissions[edit]).toContain('Tom &amp; Jerry')
    expect(state.submissions[edit]).not.toMatch(/<img|onerror|&amp;amp;|&lt;p&gt;/)
  }
  await page.goto('/product/rich-product')
  await assertFormatting(description(page))
  await expect(description(page)).toContainText('Tom & Jerry ផលិតផល')
})

test('seller creation previews and submits the same formatting shown on the storefront', async ({ page }) => {
  const state = await setup(page, null, { role: 'seller' })
  await page.goto('/seller/shops/1/products/new')
  await page.getByLabel('Product name *', { exact: true }).fill('Rich product')
  await page.getByLabel('Selling price (USD) *', { exact: true }).fill('50')
  const editor = page.getByRole('textbox', { name: 'Full description', exact: true })
  await editor.evaluate((element, html) => { element.innerHTML = html; element.dispatchEvent(new InputEvent('input', { bubbles: true })) }, formatted)
  await assertFormatting(page.locator('.form-preview [data-product-description]'))
  await page.getByRole('button', { name: 'Publish product', exact: true }).click()
  await expect(page).toHaveURL(/\/seller\/shops\/1\/products$/)
  expect(state.submissions).toHaveLength(1)
  await page.goto('/product/rich-product')
  await assertFormatting(description(page))
})

test('the existing heading bold underline and list toolbar controls retain their markup', async ({ page }) => {
  await setup(page, null, { role: 'seller' })
  await page.goto('/seller/shops/1/products/new')
  const editor = page.getByRole('textbox', { name: 'Full description', exact: true })
  await editor.fill('Features')
  await editor.press('ControlOrMeta+A')
  await page.getByLabel('Text style', { exact: true }).selectOption('h3')
  await expect(editor.locator('h3')).toHaveText('Features')
  // Headings are already bold by theme; exercise the bold toggle on a paragraph.
  await page.getByLabel('Text style', { exact: true }).selectOption('p')
  await editor.click(); await editor.press('ControlOrMeta+A')
  await page.getByRole('button', { name: 'Bold', exact: true }).click()
  await expect(editor.locator('b,strong')).toHaveText('Features')
  await page.getByRole('button', { name: 'Underline', exact: true }).click()
  await expect(editor.locator('u')).toHaveText('Features')
  await page.getByRole('button', { name: 'Bulleted list', exact: true }).click()
  await expect(editor.locator('ul li')).toHaveText('Features')
})

test('super-admin description editing uses the same sanitizer without a trusted-HTML bypass', async ({ page }) => {
  await setup(page, '<p><strong>Admin-safe description</strong></p><svg onload="window.descriptionXss=true"></svg><img src=x onerror="window.descriptionXss=true">', { role: 'admin' })
  await page.goto('/admin/products/10/edit')
  const editor = page.getByRole('textbox', { name: 'Description', exact: true })
  await expect(editor.locator('strong')).toHaveText('Admin-safe description')
  await expect(editor.locator('svg,img,script')).toHaveCount(0)
})
