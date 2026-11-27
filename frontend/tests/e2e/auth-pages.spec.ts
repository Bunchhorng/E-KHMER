import { expect, test, type Page } from '@playwright/test'

const unverifiedUser = {
  id: 5,
  name: 'Sokha Dara',
  email: 'sokha@example.test',
  role: 'customer',
  managed_shops: [],
  email_verified: false,
}

async function setupAuthMocks(page: Page) {
  const state = {
    loginReject: false,
    loginHold: false,
    releaseLogin: (() => {}) as () => void,
    loginCalls: 0,
    registerDuplicateEmail: false,
    resetReject: false,
    resendFail: false,
    user: { ...unverifiedUser },
  }

  await page.route('**/api/**', async (route) => {
    const path = new URL(route.request().url()).pathname
    if (!path.startsWith('/api/')) return route.continue()

    if (path === '/api/auth/login') {
      state.loginCalls++
      if (state.loginHold) await new Promise<void>((r) => { state.releaseLogin = r })
      if (state.loginReject) {
        return route.fulfill({ status: 422, json: { message: 'Invalid credentials.', errors: { email: ['Invalid credentials.'] } } })
      }
      return route.fulfill({ json: { data: { token: 'auth-token-123', user: { ...state.user, email_verified: true } } } })
    }

    if (path === '/api/auth/register') {
      if (state.registerDuplicateEmail) {
        return route.fulfill({ status: 422, json: { message: 'Validation failed', errors: { email: ['The email has already been taken.'] } } })
      }
      return route.fulfill({ status: 201, json: { data: { token: 'reg-token-123', user: state.user } } })
    }

    if (path === '/api/auth/forgot-password') {
      return route.fulfill({ json: { data: { message: 'If an account exists for that email, a password reset link has been sent.' } } })
    }

    if (path === '/api/auth/reset-password') {
      if (state.resetReject) {
        return route.fulfill({ status: 422, json: { message: 'This password reset token is invalid.' } })
      }
      return route.fulfill({ json: { data: { message: 'Password reset successfully.' } } })
    }

    if (path === '/api/auth/email/verification-notification') {
      if (state.resendFail) return route.fulfill({ status: 429, json: { message: 'Too many requests' } })
      return route.fulfill({ json: { data: { message: 'Verification email sent.' } } })
    }

    if (path === '/api/auth/me') return route.fulfill({ json: { data: state.user } })
    if (path === '/api/cart') return route.fulfill({ json: { data: { id: 1, items: [], totals: { subtotal: 0, discount_amount: 0, tax_amount: 0, total: 0, items_count: 0 } } } })
    return route.fulfill({ json: { data: [] } })
  })

  return state
}

async function assertSharpCornersAndNoOverflow(page: Page) {
  const result = await page.evaluate(() => {
    const els = Array.from(document.querySelectorAll('.card, input, button, a, [role="alert"], [role="status"], [data-testid="auth-brand-panel"]'))
    return {
      overflow: document.documentElement.scrollWidth > window.innerWidth,
      radii: els.map((el) => getComputedStyle(el).borderRadius),
    }
  })
  expect(result.overflow).toBe(false)
  for (const radius of result.radii) expect(radius).toBe('0px')
}

test('auth pages cover login, register, forgot-password, reset-password, verify-email, sharp corners, dark/light, Khmer, and responsive widths', async ({
  page,
}, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop')

  await page.addInitScript(() => {
    localStorage.setItem('app_locale', 'en')
    localStorage.setItem('ekhmer_theme', 'dark')
  })

  const state = await setupAuthMocks(page)

  // 1. Login page on desktop 1440px (two-column branded panel + form)
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/auth/login')
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible()
  await expect(page.getByTestId('auth-brand-panel')).toBeVisible()
  await assertSharpCornersAndNoOverflow(page)

  // Password show/hide toggle accessibility
  const passwordInput = page.locator('#login-password')
  const toggleBtn = page.getByRole('button', { name: 'Show password' })
  await expect(passwordInput).toHaveAttribute('type', 'password')
  await toggleBtn.click()
  await expect(passwordInput).toHaveAttribute('type', 'text')
  await expect(page.getByRole('button', { name: 'Hide password' })).toHaveAttribute('aria-pressed', 'true')

  // Field validation & duplicate submit lock
  await page.getByRole('button', { name: 'Sign In', exact: true }).click()
  await expect(page.getByText('Email is required.')).toBeVisible()
  await expect(page.getByText('Password is required.')).toBeVisible()

  state.loginReject = true
  state.loginHold = true
  await page.locator('#login-email').fill('sokha@example.test')
  await passwordInput.fill('wrongpass')
  await page.getByRole('button', { name: 'Sign In', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Signing in…' })).toBeDisabled()
  state.loginHold = false
  state.releaseLogin()
  await expect(page.getByRole('alert')).toContainText('Invalid credentials.')
  await page.screenshot({ path: 'test-results/auth-login-dark-desktop.png', fullPage: true })

  // Switch to light mode on login
  const themeToggle = page.getByRole('button', { name: /Switch theme/ })
  await themeToggle.click()
  await expect(page.locator('html')).not.toHaveClass(/dark/)
  await page.screenshot({ path: 'test-results/auth-login-light-desktop.png', fullPage: true })

  // 2. Register page: validation, password requirement hint, duplicate email link
  await themeToggle.click()
  await page.goto('/auth/register')
  await expect(page.getByRole('heading', { name: 'Create your account' })).toBeVisible()
  await expect(page.getByText('Must be at least 8 characters.')).toBeVisible()
  await page.locator('#reg-password').fill('Secret123!')
  await expect(page.getByText('Meets the 8-character minimum.')).toBeVisible()
  await page.locator('#reg-name').fill('Sokha Dara')
  await page.locator('#reg-email').fill('taken@example.test')
  await page.locator('#reg-confirm').fill('Secret123!')
  state.registerDuplicateEmail = true
  await page.getByRole('button', { name: 'Create your account', exact: true }).click()
  await expect(page.getByText('The email has already been taken.')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Sign in instead' })).toBeVisible()
  await assertSharpCornersAndNoOverflow(page)

  // 3. Forgot password: neutral confirmation
  await page.goto('/auth/forgot-password')
  await expect(page.getByRole('heading', { name: 'Reset your password' })).toBeVisible()
  await page.locator('#fp-email').fill('sokha@example.test')
  await page.getByRole('button', { name: 'Send Reset Link' }).click()
  await expect(page.getByRole('status')).toContainText('If an account exists for sokha@example.test')
  await expect(page.getByRole('link', { name: 'Back to sign in' })).toBeVisible()

  // 4. Reset password: invalid link notice & valid reset flow
  await page.goto('/auth/reset-password')
  await expect(page.getByRole('alert')).toContainText('invalid or has expired')
  await page.goto('/auth/reset-password?token=valid-tok&email=sokha@example.test')
  await page.locator('#rp-password').fill('NewPass123!')
  await page.locator('#rp-password-confirm').fill('NewPass123!')
  await page.getByRole('button', { name: 'Reset Password' }).click()
  await expect(page.getByRole('status')).toContainText('Your password has been reset.')

  // 5. Verify email: unauthenticated neutral state & verified state
  await page.goto('/auth/verify-email')
  await expect(page.getByRole('heading', { name: 'Email verification' })).toBeVisible()
  await page.goto('/auth/verify-email?verified=1')
  await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible()
  await expect(page.getByText('verified successfully')).toBeVisible()

  // 6. Responsive widths (320px, 375px, 390px, 430px, 768px, 1024px) & Khmer locale on Login
  await page.goto('/auth/login')
  await page.locator('button:has(img)').click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'km')

  for (const width of [320, 375, 390, 430, 768, 1024]) {
    await page.setViewportSize({ width, height: 844 })
    if (width < 1024) {
      await expect(page.getByTestId('auth-brand-panel')).toBeHidden()
      const inputHeight = await page.locator('#login-email').evaluate((el) => el.getBoundingClientRect().height)
      expect(inputHeight).toBeGreaterThanOrEqual(44)
    } else {
      await expect(page.getByTestId('auth-brand-panel')).toBeVisible()
    }
    await assertSharpCornersAndNoOverflow(page)
  }

  await page.setViewportSize({ width: 390, height: 844 })
  await page.screenshot({ path: 'test-results/auth-login-dark-mobile-km.png', fullPage: true })
})
