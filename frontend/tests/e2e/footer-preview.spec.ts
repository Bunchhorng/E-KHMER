import { expect, test } from '@playwright/test'

test('renders a responsive footer without horizontal overflow', async ({ page }) => {
  const appFooter = page.getByTestId('storefront-footer')

  for (const viewport of [320, 375, 390, 430, 768, 1440]) {
    await page.setViewportSize({ width: viewport, height: 900 })
    await page.goto('/')
    await appFooter.scrollIntoViewIfNeeded()
    await expect(appFooter).toBeVisible()
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy()
  }

  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/')
  await appFooter.scrollIntoViewIfNeeded()
  await appFooter.screenshot({ path: 'test-results/footer-after-desktop.png' })

  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/')
  await appFooter.scrollIntoViewIfNeeded()
  await appFooter.locator('input[type="email"]').fill('not-an-email')
  await appFooter.locator('button[type="submit"]').click()
  await expect(page.locator('#newsletter-email-error')).toBeVisible()
  await appFooter.screenshot({ path: 'test-results/footer-after-mobile.png' })
})
