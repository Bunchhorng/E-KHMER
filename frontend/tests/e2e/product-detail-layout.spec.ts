import { expect, test } from '@playwright/test'

test('product detail keeps the gallery and purchase controls usable across screen sizes', async ({ page }, info) => {
  await page.addInitScript(() => localStorage.setItem('app_locale', 'en'))
  const product = {
    id: 10, slug: 'detail', name: 'A detailed product with a long name', sku: 'SKU-10',
    price: 123456789, compare_at_price: 200000000, rating_avg: 0, rating_count: 0,
    is_featured: false, is_active: true, in_stock: true, cover_image: '/missing-cover.png',
    description: null, short_description: null, weight: null, meta_title: null, meta_description: null,
    brand: { id: 1, slug: 'brand', name: 'Brand' }, category: null,
    shop: { id: 1, name: 'Seller', slug: 'seller', code: 'S', logo: null },
    gallery: [{ id: 1, image_path: '/missing-cover.png', alt_text: 'Cover' }, { id: 2, image_path: '/missing-second.png', alt_text: 'Second' }],
    attributes: [{ id: 1, name: 'Size', slug: 'size', type: 'text', values: [{ id: 1, value: 'M', swatch_color: null }, { id: 2, value: 'L', swatch_color: null }] }],
    variants: [
      { id: 1, sku: 'M', name: 'M', price: 123456789, compare_at_price: 200000000, is_default: true, is_active: true, in_stock: true, available_quantity: 3, attributes: [{ name: 'Size', value: 'M', attribute_slug: 'size' }] },
      { id: 2, sku: 'L', name: 'L', price: 123456789, compare_at_price: null, is_default: false, is_active: true, in_stock: false, available_quantity: 0, attributes: [{ name: 'Size', value: 'L', attribute_slug: 'size' }] },
    ],
  }
  await page.route('**/api/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()
    if (path === '/api/catalog/products/detail') return route.fulfill({ json: { data: product } })
    if (path === '/api/cart') return route.fulfill({ json: { data: { id: 1, items: [], totals: { subtotal: 0, discount_amount: 0, tax_amount: 0, total: 0, items_count: 0 } } } })
    return route.fulfill({ json: { data: [] } })
  })
  await page.goto('/product/detail')
  await expect(page.getByRole('heading', { name: product.name })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Customer Reviews' })).toBeVisible()
  await expect(page.getByRole('button', { name: /^View image:/ })).toHaveCount(2)
  await expect(page.locator('[data-image-state="fallback"]').first()).toBeVisible()
  await page.getByRole('button', { name: 'Zoom image' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog')).toBeHidden()

  for (const width of info.project.name === 'mobile' ? [320, 375, 390, 430, 768] : [1024, 1440]) {
    await page.setViewportSize({ width, height: 900 })
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
    const galleryHeight = await page.getByRole('button', { name: 'Zoom image' }).evaluate(el => el.getBoundingClientRect().height)
    expect(galleryHeight).toBeLessThanOrEqual(544)
    if (width < 1024) {
      await expect(page.getByTestId('mobile-purchase-bar')).toBeVisible()
      await expect(page.getByRole('navigation', { name: 'Mobile navigation' })).toBeVisible()
    }
  }
  await page.getByRole('button', { name: 'L', exact: true }).click()
  await expect(page.getByText('Out of Stock', { exact: true }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Add to Cart', exact: true })).toBeDisabled()
  await expect(page.getByText('You save', { exact: false })).toHaveCount(0)
  await page.getByRole('button', { name: 'Specifications', exact: true }).click()
  await expect(page.getByText('Size', { exact: true }).last()).toBeVisible()
})
