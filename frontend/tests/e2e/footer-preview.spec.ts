import { expect, test, type Locator } from '@playwright/test'

function parseRgba(color: string): [number, number, number, number] {
  const match = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/)
  if (!match) throw new Error(`Unsupported RGB color: ${color}`)
  return [Number(match[1]), Number(match[2]), Number(match[3]), match[4] !== undefined ? Number(match[4]) : 1]
}

function compositeOver(foreground: string, background: string): [number, number, number] {
  const [fr, fg, fb, fa] = parseRgba(foreground)
  const [br, bg, bb] = parseRgba(background)
  return [
    Math.round(fr * fa + br * (1 - fa)),
    Math.round(fg * fa + bg * (1 - fa)),
    Math.round(fb * fa + bb * (1 - fa)),
  ]
}

function relativeLuminance([r, g, b]: [number, number, number]): number {
  const linear = [r, g, b].map((channel) => {
    const srgb = channel / 255
    return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]
}

function contrastRatio(foreground: string, background: string, backdrop?: string): number {
  const effectiveBg = backdrop ? compositeOver(background, backdrop) : compositeOver(background, 'rgb(255, 255, 255)')
  const effectiveFg = compositeOver(foreground, `rgb(${effectiveBg.join(', ')})`)
  const l1 = relativeLuminance(effectiveFg)
  const l2 = relativeLuminance(effectiveBg)
  const lighter = Math.max(l1, l2)
  const darker = Math.min(l1, l2)
  return (lighter + 0.05) / (darker + 0.05)
}

async function assertFooterContrastAndCorners(footer: Locator): Promise<void> {
  const metrics = await footer.evaluate((el) => {
    const style = (node: Element | null) => (node ? getComputedStyle(node) : null)
    const footerBg = getComputedStyle(el).backgroundColor
    const heading = el.querySelector('.footer-heading')
    const tagline = el.querySelector('section p')
    const link = el.querySelector('.footer-link')
    const legalLink = el.querySelector('.footer-legal-link')
    const copyright = el.querySelector('.border-t p')
    const input = el.querySelector('input[type="email"]')
    const button = el.querySelector('button[type="submit"]')
    const checkbox = el.querySelector('input[type="checkbox"]')
    const consentLabel = el.querySelector('label')
    const badge = el.querySelector('.payment-method')
    const badgeIcon = badge?.querySelector('svg')

    const radii = [el, input, button, checkbox, consentLabel, badge, link, legalLink]
      .filter((node): node is Element => Boolean(node))
      .map((node) => getComputedStyle(node).borderRadius)

    return {
      footerBg,
      headingColor: style(heading)?.color ?? '',
      taglineColor: style(tagline)?.color ?? '',
      linkColor: style(link)?.color ?? '',
      legalLinkColor: style(legalLink)?.color ?? '',
      copyrightColor: style(copyright)?.color ?? '',
      inputBg: style(input)?.backgroundColor ?? '',
      inputColor: style(input)?.color ?? '',
      inputBorder: style(input)?.borderColor ?? '',
      consentColor: style(consentLabel)?.color ?? '',
      badgeBg: style(badge)?.backgroundColor ?? '',
      badgeColor: style(badge)?.color ?? '',
      badgeIconColor: style(badgeIcon ?? null)?.color ?? '',
      radii,
    }
  })

  expect(contrastRatio(metrics.headingColor, metrics.footerBg)).toBeGreaterThanOrEqual(7)
  expect(contrastRatio(metrics.taglineColor, metrics.footerBg)).toBeGreaterThanOrEqual(4.5)
  expect(contrastRatio(metrics.linkColor, metrics.footerBg)).toBeGreaterThanOrEqual(4.5)
  expect(contrastRatio(metrics.legalLinkColor, metrics.footerBg)).toBeGreaterThanOrEqual(4.5)
  expect(contrastRatio(metrics.copyrightColor, metrics.footerBg)).toBeGreaterThanOrEqual(4.5)
  expect(contrastRatio(metrics.consentColor, metrics.footerBg)).toBeGreaterThanOrEqual(4.5)
  expect(contrastRatio(metrics.inputColor, metrics.inputBg)).toBeGreaterThanOrEqual(7)
  expect(contrastRatio(metrics.badgeColor, metrics.badgeBg)).toBeGreaterThanOrEqual(4.5)
  expect(contrastRatio(metrics.badgeIconColor, metrics.badgeBg)).toBeGreaterThanOrEqual(4.5)
  for (const radius of metrics.radii) {
    expect(radius).toBe('0px')
  }
}

test('renders a responsive, high-contrast sharp-corner footer across light, dark, mobile, desktop, and Khmer', async ({ page }) => {
  await page.route('**/api/newsletter', (route) =>
    route.fulfill({ status: 200, json: { message: 'Subscribed' } })
  )

  const appFooter = page.getByTestId('storefront-footer')

  // Desktop: light mode, 4-column layout, theme toggle & persistence
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.addInitScript(() => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'light')
  })
  await page.goto('/')
  await appFooter.scrollIntoViewIfNeeded()
  await expect(appFooter).toBeVisible()
  await assertFooterContrastAndCorners(appFooter)

  const desktopGridColumns = await appFooter
    .locator('.grid')
    .first()
    .evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length)
  expect(desktopGridColumns).toBe(4)

  // Switch to dark mode via theme toggle and verify persistence
  const themeToggle = page.getByTestId('storefront-header-utilities').getByRole('button', { name: /Switch theme/ })
  await themeToggle.click()
  await expect(page.locator('html')).toHaveClass(/dark/)
  await expect.poll(() => page.evaluate(() => localStorage.getItem('ekhmer_theme'))).toBe('dark')
  await appFooter.scrollIntoViewIfNeeded()
  await assertFooterContrastAndCorners(appFooter)
  await appFooter.screenshot({ path: 'test-results/footer-dark-desktop.png' })

  // Verify validation errors and success feedback in dark mode
  await appFooter.locator('input[type="email"]').fill('not-an-email')
  await appFooter.locator('button[type="submit"]').click()
  const emailError = page.locator('#newsletter-email-error')
  await expect(emailError).toBeVisible()

  await appFooter.locator('input[type="email"]').fill('customer@example.com')
  await appFooter.locator('input[type="checkbox"]').check()
  await appFooter.locator('button[type="submit"]').click()
  const successMessage = appFooter.locator('[role="status"]')
  await expect(successMessage).toBeVisible()
  const successColors = await successMessage.evaluate((el) => ({
    fg: getComputedStyle(el).color,
    bg: getComputedStyle(el).backgroundColor,
    footerBg: getComputedStyle(el.closest('footer')!).backgroundColor,
    radius: getComputedStyle(el).borderRadius,
  }))
  expect(contrastRatio(successColors.fg, successColors.bg, successColors.footerBg)).toBeGreaterThanOrEqual(4.5)
  expect(successColors.radius).toBe('0px')

  // Switch back to light mode and verify success message contrast in light mode
  await themeToggle.click()
  await expect(page.locator('html')).not.toHaveClass(/dark/)
  const lightSuccessColors = await successMessage.evaluate((el) => ({
    fg: getComputedStyle(el).color,
    bg: getComputedStyle(el).backgroundColor,
    footerBg: getComputedStyle(el.closest('footer')!).backgroundColor,
  }))
  expect(contrastRatio(lightSuccessColors.fg, lightSuccessColors.bg, lightSuccessColors.footerBg)).toBeGreaterThanOrEqual(4.5)
  await appFooter.screenshot({ path: 'test-results/footer-light-desktop.png' })

  // Mobile viewports: dark mode, accordions, no overflow, clear of MobileBottomNavigation, Khmer support
  await themeToggle.click()
  await expect(page.locator('html')).toHaveClass(/dark/)

  for (const viewport of [320, 375, 390, 430, 768]) {
    await page.setViewportSize({ width: viewport, height: 844 })
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
    await expect(appFooter).toBeVisible()
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy()
  }

  await page.setViewportSize({ width: 390, height: 844 })
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
  const bottomNav = page.getByRole('navigation', { name: 'Mobile navigation' })
  await expect(bottomNav).toBeVisible()

  const overlap = await page.evaluate(() => {
    const footerRect = document.querySelector('[data-testid="storefront-footer"]')?.getBoundingClientRect()
    const navRect = document.querySelector('nav[aria-label="Mobile navigation"]')?.getBoundingClientRect()
    if (!footerRect || !navRect) return true
    return footerRect.bottom > navRect.top + 1
  })
  expect(overlap).toBe(false)

  // Open mobile Shop accordion
  const shopSummary = appFooter.locator('details').first().locator('summary')
  await shopSummary.click()
  await expect(appFooter.locator('details').first()).toHaveAttribute('open', '')

  // Switch to Khmer language and verify no overflow + readable dark mobile footer
  const languageToggle = page.getByTestId('storefront-header-utilities').locator('button:has(img)')
  await languageToggle.click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'km')
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy()
  await assertFooterContrastAndCorners(appFooter)
  await appFooter.screenshot({ path: 'test-results/footer-dark-mobile-km.png' })
})
