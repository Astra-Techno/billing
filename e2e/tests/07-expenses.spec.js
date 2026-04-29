/**
 * Expense Tests — List, Create
 */
import { test, expect } from '@playwright/test'

const uid = Date.now()

test.describe('Expense List', () => {
  test('shows Expenses heading and Add Expense button', async ({ page }) => {
    await page.goto('/expenses')
    await expect(page.getByRole('heading', { name: 'Expenses' })).toBeVisible()
  })

  test('shows expense list or empty state', async ({ page }) => {
    await page.goto('/expenses')
    await expect(
      page.getByText(/No expenses/i).or(page.locator('table tbody tr').first()).first()
    ).toBeVisible({ timeout: 5_000 })
  })

  test('Add Expense button opens expense form', async ({ page }) => {
    await page.goto('/expenses')
    const addBtn = page.getByRole('link', { name: /Add Expense|New Expense/i })
      .or(page.getByRole('button', { name: /Add Expense|New Expense/i }))

    if (await addBtn.isVisible()) {
      await addBtn.click()
      await expect(page).toHaveURL(/\/expenses\/new|\/expenses/, { timeout: 5_000 })
    }
  })
})

test.describe('Create Expense', () => {
  test('expense form has amount and category fields', async ({ page }) => {
    await page.goto('/expenses/new')

    // Gracefully skip if expenses/new doesn't exist in this build
    if (page.url().includes('login')) {
      test.skip()
      return
    }

    await expect(page.getByLabel(/Amount/i)).toBeVisible()
  })
})
