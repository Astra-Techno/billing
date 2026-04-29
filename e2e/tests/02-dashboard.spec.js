/**
 * Dashboard Tests
 */
import { test, expect } from '@playwright/test'

const GREETING = /Good morning|Good afternoon|Good evening/i

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')
  })

  test('shows greeting heading', async ({ page }) => {
    await expect(page.getByText(GREETING)).toBeVisible()
  })

  test('hero card shows Money to Collect stat', async ({ page }) => {
    await expect(page.getByText('Money to Collect')).toBeVisible()
    await expect(page.getByText('from pending bills')).toBeVisible()
  })

  test('hero card shows secondary stats', async ({ page }) => {
    await expect(page.getByText('This Month')).toBeVisible()
    await expect(page.getByText('Overdue')).toBeVisible()
  })

  test('quick actions grid is visible', async ({ page }) => {
    await expect(page.getByText('Quick Actions')).toBeVisible()
    await expect(page.getByRole('link', { name: 'New Bill' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Add Customer' })).toBeVisible()
  })

  test('shows Recent Bills section', async ({ page }) => {
    await expect(page.getByText('Recent Bills')).toBeVisible()
    await expect(page.getByRole('link', { name: 'View all' })).toBeVisible()
  })

  test('"View all" recent bills link navigates to invoice list', async ({ page }) => {
    await page.getByRole('link', { name: 'View all' }).click()
    await expect(page).toHaveURL(/\/invoices/)
  })

  test('clicking a recent bill row navigates to detail page', async ({ page }) => {
    const rows = page.locator('.divide-y .cursor-pointer')
    const count = await rows.count()
    if (count === 0) {
      test.skip()
      return
    }
    await rows.first().click()
    await expect(page).toHaveURL(/\/invoices\/\d+/)
  })

  test('desktop sidebar has all expected links', async ({ page }) => {
    // Desktop sidebar links (hidden on mobile)
    await expect(page.getByRole('link', { name: /Bills/i }).first()).toBeVisible()
    await expect(page.getByRole('link', { name: /Customers/i }).first()).toBeVisible()
    await expect(page.getByRole('link', { name: /Quotations/i }).first()).toBeVisible()
    await expect(page.getByRole('link', { name: /Expenses/i }).first()).toBeVisible()
    await expect(page.getByRole('link', { name: /Reports/i }).first()).toBeVisible()
    await expect(page.getByRole('link', { name: /Settings/i }).first()).toBeVisible()
  })
})
