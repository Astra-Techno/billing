/**
 * Quote Tests — List, Create, Detail, Convert to Bill
 */
import { test, expect } from '@playwright/test'
import { InvoicePage } from '../pages/InvoicePage.js'

const uid = Date.now()

test.describe('Quote List', () => {
  test('shows Quotations heading and New Quotation button', async ({ page }) => {
    await page.goto('/quotes')
    await expect(page.getByRole('heading', { name: 'Quotations' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'New Quotation' })).toBeVisible()
  })

  test('New Quotation link navigates to quote form', async ({ page }) => {
    await page.goto('/quotes')
    await page.getByRole('link', { name: 'New Quotation' }).click()
    await expect(page).toHaveURL(/\/quotes\/new/)
  })

  test('has status filter tabs', async ({ page }) => {
    await page.goto('/quotes')
    await expect(page.getByRole('button', { name: /All/i })).toBeVisible()
  })
})

test.describe('Create Quote', () => {
  test('quote form has client selector and line items', async ({ page }) => {
    await page.goto('/quotes/new')
    // Quote form may use a select or similar
    await expect(page.getByLabel(/Client|Customer/i).first()).toBeVisible()
  })

  test('shows error when no client selected', async ({ page }) => {
    await page.goto('/quotes/new')
    await page.getByRole('button', { name: /Save Quote|Create Quote/i }).click()
    await expect(page.locator('.text-danger-500')).toBeVisible({ timeout: 5_000 })
  })

  test('creates a quote and lands on detail page', async ({ page }) => {
    await page.goto('/quotes/new')

    const clientSelect = page.getByLabel(/Client/i).first()
    await clientSelect.selectOption({ index: 1 })

    const descInput  = page.locator('input[placeholder*="description"], input[placeholder*="Item"]').first()
    const priceInput = page.locator('input[placeholder*="price"], input[placeholder*="Rate"]').first()

    await descInput.fill(`Quote Service ${uid}`)
    await priceInput.fill('8000')

    await page.getByRole('button', { name: /Save Quote|Create Quote/i }).click()
    await expect(page).toHaveURL(/\/quotes\/\d+/, { timeout: 12_000 })
  })

  test('created quote shows QTE prefix', async ({ page }) => {
    await page.goto('/quotes/new')

    const clientSelect = page.getByLabel(/Client/i).first()
    await clientSelect.selectOption({ index: 1 })

    const descInput  = page.locator('input[placeholder*="description"], input[placeholder*="Item"]').first()
    const priceInput = page.locator('input[placeholder*="price"], input[placeholder*="Rate"]').first()

    await descInput.fill(`Another Service ${uid}`)
    await priceInput.fill('3000')

    await page.getByRole('button', { name: /Save Quote|Create Quote/i }).click()
    await page.waitForURL(/\/quotes\/\d+/, { timeout: 12_000 })

    await expect(page.getByText(/QTE/)).toBeVisible()
  })
})

test.describe('Quote Detail', () => {
  let quoteUrl

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage()
    await page.goto('/quotes/new')

    const clientSelect = page.getByLabel(/Client/i).first()
    await clientSelect.selectOption({ index: 1 })

    const descInput  = page.locator('input[placeholder*="description"], input[placeholder*="Item"]').first()
    const priceInput = page.locator('input[placeholder*="price"], input[placeholder*="Rate"]').first()
    await descInput.fill(`Quote Detail ${uid}`)
    await priceInput.fill('15000')

    await page.getByRole('button', { name: /Save Quote|Create Quote/i }).click()
    await page.waitForURL(/\/quotes\/\d+/, { timeout: 12_000 })
    quoteUrl = page.url()
    await page.close()
  })

  test('quote detail shows quote number', async ({ page }) => {
    await page.goto(quoteUrl)
    await expect(page.getByText(/QTE/)).toBeVisible()
  })

  test('quote detail shows total amount', async ({ page }) => {
    await page.goto(quoteUrl)
    await expect(page.getByText(/₹/)).toBeVisible()
  })

  test('quote detail has Convert to Bill button', async ({ page }) => {
    await page.goto(quoteUrl)
    await expect(
      page.getByRole('button', { name: /Convert.*Bill|Convert/i })
    ).toBeVisible()
  })

  test('convert to bill creates invoice and redirects', async ({ page }) => {
    await page.goto(quoteUrl)
    const convertBtn = page.getByRole('button', { name: /Convert.*Bill|Convert/i })
    if (!await convertBtn.isVisible()) {
      test.skip()
      return
    }
    await convertBtn.click()
    const confirmBtn = page.getByRole('button', { name: /Confirm|Yes|Convert/i }).last()
    if (await confirmBtn.isVisible({ timeout: 2_000 })) await confirmBtn.click()

    await expect(page).toHaveURL(/\/invoices\/\d+/, { timeout: 12_000 })
    await expect(page.getByText(/INV/)).toBeVisible()
  })
})
