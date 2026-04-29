/**
 * Auth Tests — Login, Registration, Route Guards, Logout
 * These run with storageState: undefined (no pre-auth) via the chromium project,
 * but they explicitly navigate to /login and /register themselves.
 */
import { test, expect } from '@playwright/test'
import { LoginPage }    from '../pages/LoginPage.js'
import { RegisterPage } from '../pages/RegisterPage.js'
import { TEST_USER }    from '../credentials.js'

// All auth tests start unauthenticated
test.use({ storageState: { cookies: [], origins: [] } })

test.describe('Login Page', () => {
  test('shows login form with correct labels', async ({ page }) => {
    const login = new LoginPage(page)
    await login.goto()

    await expect(page.getByText('BillBook India')).toBeVisible()
    await expect(page.getByText('Sign in to your account')).toBeVisible()
    await expect(login.emailInput).toBeVisible()
    await expect(login.passwordInput).toBeVisible()
    await expect(login.submitBtn).toBeVisible()
    await expect(login.registerLink).toBeVisible()
  })

  test('shows error for invalid credentials', async ({ page }) => {
    const login = new LoginPage(page)
    await login.goto()
    await login.login('wrong@example.com', 'wrongpassword')

    await expect(login.errorMsg).toBeVisible({ timeout: 8_000 })
    await expect(page).toHaveURL(/\/login/)
  })

  test('shows error for empty password', async ({ page }) => {
    const login = new LoginPage(page)
    await login.goto()
    await login.fillEmail('someone@example.com')
    await login.submit()

    // Browser native "required" validation prevents submit — still on login
    await expect(page).toHaveURL(/\/login/)
  })

  test('login link navigates to register', async ({ page }) => {
    const login = new LoginPage(page)
    await login.goto()
    await login.registerLink.click()
    await expect(page).toHaveURL(/\/register/)
  })

  test('successful login redirects to dashboard', async ({ page }) => {
    const login = new LoginPage(page)
    await login.goto()
    await login.login(TEST_USER.email, TEST_USER.password)
    await expect(page).toHaveURL('/', { timeout: 10_000 })
    await expect(page.getByText(/Good morning|Good afternoon|Good evening/i)).toBeVisible()
  })
})

test.describe('Route Guards', () => {
  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/login/)
  })

  test('unauthenticated user cannot access clients', async ({ page }) => {
    await page.goto('/clients')
    await expect(page).toHaveURL(/\/login/)
  })

  test('unauthenticated user cannot access invoices', async ({ page }) => {
    await page.goto('/invoices')
    await expect(page).toHaveURL(/\/login/)
  })

  test('logged-in user visiting /login is redirected to dashboard', async ({ page }) => {
    // Login first
    const login = new LoginPage(page)
    await login.goto()
    await login.login(TEST_USER.email, TEST_USER.password)
    await page.waitForURL('/')

    // Now try to go to /login again
    await page.goto('/login')
    await expect(page).toHaveURL('/')
  })
})

test.describe('Registration Flow', () => {
  test('shows two-step registration form', async ({ page }) => {
    const reg = new RegisterPage(page)
    await reg.goto()

    await expect(page.getByText('Your details')).toBeVisible()
    await expect(page.getByLabel('Full Name')).toBeVisible()
    await expect(page.getByLabel('Email')).toBeVisible()
    await expect(page.getByLabel('Mobile Number')).toBeVisible()
    await expect(page.getByLabel('Password')).toBeVisible()
  })

  test('step 1 validation — requires all fields', async ({ page }) => {
    const reg = new RegisterPage(page)
    await reg.goto()

    // Click Continue with nothing filled
    await page.getByRole('button', { name: 'Continue' }).click()
    await expect(reg.errorMsg()).toBeVisible()
  })

  test('step 1 validation — password too short', async ({ page }) => {
    const reg = new RegisterPage(page)
    await reg.goto()

    await page.getByLabel('Full Name').fill('Test User')
    await page.getByLabel('Email').fill('x@x.com')
    await page.getByLabel('Mobile Number').fill('9000000099')
    await page.getByLabel('Password').fill('short')
    await page.getByLabel('Confirm Password').fill('short')
    await page.getByRole('button', { name: 'Continue' }).click()

    await expect(reg.errorMsg()).toContainText(/8 characters/i)
  })

  test('step 1 validation — password mismatch', async ({ page }) => {
    const reg = new RegisterPage(page)
    await reg.goto()

    await page.getByLabel('Full Name').fill('Test User')
    await page.getByLabel('Email').fill('x@x.com')
    await page.getByLabel('Mobile Number').fill('9000000099')
    await page.getByLabel('Password').fill('Password123')
    await page.getByLabel('Confirm Password').fill('Different123')
    await page.getByRole('button', { name: 'Continue' }).click()

    await expect(reg.errorMsg()).toContainText(/do not match/i)
  })

  test('step 1 → step 2 navigation', async ({ page }) => {
    const reg = new RegisterPage(page)
    await reg.goto()

    await page.getByLabel('Full Name').fill('Demo User')
    await page.getByLabel('Email').fill('demo.nav@test.com')
    await page.getByLabel('Mobile Number').fill('9000000088')
    await page.getByLabel('Password').fill('Password@123')
    await page.getByLabel('Confirm Password').fill('Password@123')
    await page.getByRole('button', { name: 'Continue' }).click()

    // Step 2 should appear
    await expect(page.getByText('Your Business')).toBeVisible()
    await expect(page.getByLabel(/Business.*Name/i)).toBeVisible()
    await expect(page.getByLabel('State')).toBeVisible()
  })

  test('back button on step 2 returns to step 1', async ({ page }) => {
    const reg = new RegisterPage(page)
    await reg.goto()

    await page.getByLabel('Full Name').fill('Demo User')
    await page.getByLabel('Email').fill('demo.back@test.com')
    await page.getByLabel('Mobile Number').fill('9000000087')
    await page.getByLabel('Password').fill('Password@123')
    await page.getByLabel('Confirm Password').fill('Password@123')
    await page.getByRole('button', { name: 'Continue' }).click()

    await expect(page.getByText('Your Business')).toBeVisible()
    await page.locator('button[type="button"]').first().click() // back arrow
    await expect(page.getByText('Your details')).toBeVisible()
  })

  test('already have account link goes to login', async ({ page }) => {
    const reg = new RegisterPage(page)
    await reg.goto()
    await page.getByRole('link', { name: 'Sign in' }).click()
    await expect(page).toHaveURL(/\/login/)
  })
})
