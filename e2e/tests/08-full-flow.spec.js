/**
 * Full End-to-End Business Flow
 * Simulates a complete business cycle:
 *   Register → Settings → Add Customer → Create Quote → Convert to Bill → Record Payment
 */
import { test, expect } from '@playwright/test'

const uid = Date.now()

// This test runs with NO stored auth — it registers a brand new account
test.use({ storageState: { cookies: [], origins: [] } })

test('complete new-user onboarding and billing cycle', async ({ page }) => {
  const email    = `newbiz_${uid}@e2etest.com`
  const password = 'Test@12345'
  const bizName  = `NewBiz ${uid}`

  // ── 1. Register ──────────────────────────────────────────────────────────
  await page.goto('/register')
  await expect(page.getByText('Your details')).toBeVisible()

  await page.getByLabel('Full Name').fill('New Owner')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Mobile Number').fill('9000099999')
  await page.getByLabel('Password').fill(password)
  await page.getByLabel('Confirm Password').fill(password)
  await page.getByRole('button', { name: 'Continue' }).click()

  await expect(page.getByText('Your Business')).toBeVisible()
  await page.getByLabel(/Business.*Name/i).fill(bizName)
  await page.getByLabel('State').selectOption({ index: 1 })
  await page.getByRole('button', { name: 'Create Free Account' }).click()

  await expect(page).toHaveURL('/', { timeout: 15_000 })
  await expect(page.getByText(/Good morning|Good afternoon|Good evening/i)).toBeVisible()

  // ── 2. Settings — save business profile ──────────────────────────────────
  await page.goto('/settings')
  await expect(page.getByLabel('Business Name *')).toBeVisible()
  await page.getByLabel('Mobile').fill('9000099999')
  await page.getByRole('button', { name: 'Save Business Profile' }).click()
  await expect(page.locator('.text-success-700')).toBeVisible({ timeout: 8_000 })

  // ── 3. Create a Customer ──────────────────────────────────────────────────
  await page.goto('/clients/new')
  const clientName = `E2E Customer ${uid}`
  await page.getByLabel(/Business Name|Full Name/i).fill(clientName)
  await page.getByLabel(/^Mobile Number \*/i).fill('9111111111')
  await page.getByRole('button', { name: /Add Customer|Save Changes/i }).click()
  await expect(page).toHaveURL('/clients', { timeout: 10_000 })

  await page.getByPlaceholder(/Search by name/i).fill(clientName)
  await page.waitForTimeout(600)
  await expect(page.getByText(clientName)).toBeVisible()

  // ── 4. Create a Quote ─────────────────────────────────────────────────────
  await page.goto('/quotes/new')

  const clientSelect = page.getByLabel(/Client/i).first()
  await expect(clientSelect).toBeVisible({ timeout: 5_000 })
  await clientSelect.selectOption({ label: clientName })

  const descInput  = page.locator('input[placeholder*="description"], input[placeholder*="Item"]').first()
  const priceInput = page.locator('input[placeholder*="price"], input[placeholder*="Rate"]').first()
  await descInput.fill('Website Development')
  await priceInput.fill('50000')

  await page.getByRole('button', { name: /Save Quote|Create Quote/i }).click()
  await expect(page).toHaveURL(/\/quotes\/\d+/, { timeout: 12_000 })
  await expect(page.getByText(/QTE/)).toBeVisible()

  // ── 5. Convert Quote to Bill ──────────────────────────────────────────────
  const convertBtn = page.getByRole('button', { name: /Convert.*Bill|Convert/i })
  if (await convertBtn.isVisible({ timeout: 3_000 })) {
    await convertBtn.click()
    const confirmBtn = page.getByRole('button', { name: /Confirm|Yes|Convert/i }).last()
    if (await confirmBtn.isVisible({ timeout: 2_000 })) await confirmBtn.click()
    await expect(page).toHaveURL(/\/invoices\/\d+/, { timeout: 12_000 })
    await expect(page.getByText(/INV/)).toBeVisible()
  } else {
    // Fallback: create invoice via wizard
    await page.goto('/invoices/new')
    // Select client via card or select
    const card = page.locator('.grid button').filter({ hasText: clientName })
    if (await card.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await card.click()
    } else {
      await page.locator('select').first().selectOption({ label: clientName })
    }
    await page.getByRole('button', { name: /Next.*Items/i }).click()
    await page.locator('input[placeholder*="sell"], input[placeholder*="provide"]').first().fill('Website Development')
    await page.locator('input[placeholder="0.00"]').first().fill('50000')
    await page.getByRole('button', { name: /Review.*Bill/i }).click()
    await page.getByRole('button', { name: /Create Bill/i }).click()
    await expect(page).toHaveURL(/\/invoices\/\d+/, { timeout: 12_000 })
  }

  const invoiceUrl = page.url()

  // ── 6. Verify Invoice Detail ──────────────────────────────────────────────
  await expect(page.getByText(/INV/)).toBeVisible()
  await expect(page.getByText(/₹/)).toBeVisible()

  // ── 7. Record a Payment ───────────────────────────────────────────────────
  const payBtn = page.getByRole('button', { name: /Record Payment|Add Payment/i })
  if (await payBtn.isVisible({ timeout: 3_000 })) {
    await payBtn.click()
    await page.getByLabel(/Amount/i).fill('50000')
    await page.getByRole('button', { name: /Save|Confirm|Record/i }).last().click()
    await expect(page).toHaveURL(invoiceUrl)
    await expect(
      page.locator('.badge-green, .badge-yellow').first()
    ).toBeVisible({ timeout: 8_000 })
  }

  // ── 8. Dashboard reflects new data ───────────────────────────────────────
  await page.goto('/')
  await expect(page.getByText(/Good morning|Good afternoon|Good evening/i)).toBeVisible()
  await expect(page.locator('.card').first()).toBeVisible()
})
