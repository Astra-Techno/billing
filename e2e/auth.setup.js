/**
 * Auth Setup — runs once before all E2E tests.
 * Tries to log in with the test account. If login fails (account doesn't exist yet),
 * it registers a new account automatically. Then saves browser storage state
 * so every subsequent test starts already authenticated.
 */
import { test as setup, expect } from '@playwright/test'
import { TEST_USER } from './credentials.js'
import { fileURLToPath } from 'url'
import path from 'path'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const AUTH_FILE  = path.join(__dirname, '.auth/user.json')

setup('authenticate', async ({ page }) => {
  // ── Try Login First ───────────────────────────────────────────────────────
  await page.goto('/login')
  await page.locator('input[type="email"]').fill(TEST_USER.email)
  await page.locator('input[autocomplete="current-password"]').fill(TEST_USER.password)
  await page.getByRole('button', { name: 'Sign In' }).click()

  // If login succeeded → dashboard will appear within 5s
  const onDashboard = await page.getByRole('heading', { name: 'Dashboard', exact: true })
    .waitFor({ timeout: 6_000 })
    .then(() => true)
    .catch(() => false)

  if (!onDashboard) {
    // ── Login failed — register the test account ──────────────────────────
    console.log('Login failed — registering new test account...')
    await page.goto('/register')

    // Step 1: personal info
    await page.locator('input[autocomplete="name"]').fill(TEST_USER.name)
    await page.locator('input[type="email"]').fill(TEST_USER.email)
    await page.locator('input[autocomplete="tel"]').fill(TEST_USER.mobile)
    await page.locator('input[autocomplete="new-password"]').first().fill(TEST_USER.password)
    await page.locator('input[autocomplete="new-password"]').last().fill(TEST_USER.password)
    await page.getByRole('button', { name: 'Continue' }).click()

    // Step 2: business info
    await expect(page.getByRole('heading', { name: 'Your Business', exact: true })).toBeVisible({ timeout: 5_000 })
    await page.getByPlaceholder('e.g. Sharma Electronics').fill(TEST_USER.business)
    await page.locator('select[required]').selectOption({ index: 1 })  // first state
    await page.getByRole('button', { name: 'Create Free Account' }).click()

    await page.waitForURL('/', { timeout: 15_000 })
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible()
  }

  // ── Save auth state ───────────────────────────────────────────────────────
  await page.context().storageState({ path: AUTH_FILE })
  console.log('Auth state saved to', AUTH_FILE)
})
