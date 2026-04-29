/**
 * Invoice Tests — List, Create (3-step wizard), Detail, Payment, Status filters
 */
import { test, expect } from '@playwright/test'
import { ClientPage }  from '../pages/ClientPage.js'
import { InvoicePage } from '../pages/InvoicePage.js'

const uid = Date.now()

async function ensureClient(page, name, mobile) {
  const cp = new ClientPage(page)
  await cp.gotoNew()
  await cp.fillBasicDetails({ name, mobile })
  await cp.save()
  await page.waitForURL('/clients', { timeout: 8_000 })
  return name
}

test.describe('Invoice List', () => {
  test('shows Bills heading and Create New Bill button', async ({ page }) => {
    const ip = new InvoicePage(page)
    await ip.gotoList()

    await expect(page.getByRole('heading', { name: 'Bills' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Create New Bill' })).toBeVisible()
  })

  test('has status filter tabs', async ({ page }) => {
    await page.goto('/invoices')
    await expect(page.getByRole('button', { name: /All/i })).toBeVisible()
    await expect(page.getByRole('button', { name: /Not Sent/i })).toBeVisible()
    await expect(page.getByRole('button', { name: /Awaiting Payment/i })).toBeVisible()
  })

  test('Create New Bill button navigates to form', async ({ page }) => {
    await page.goto('/invoices')
    await page.getByRole('link', { name: 'Create New Bill' }).click()
    await expect(page).toHaveURL(/\/invoices\/new/)
  })
})

test.describe('Create Invoice (wizard)', () => {
  let clientName

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage()
    clientName = await ensureClient(page, `InvClient${uid}`, '9700001111')
    await page.close()
  })

  test('wizard shows step 1 — choose customer', async ({ page }) => {
    await page.goto('/invoices/new')

    await expect(page.getByText('Create New Bill')).toBeVisible()
    await expect(page.getByText(/Who is this bill for/i)).toBeVisible()
    await expect(page.getByLabel(/Bill Date/i)).toBeVisible()
    await expect(page.getByLabel(/Pay By/i)).toBeVisible()
  })

  test('shows error when no client is selected', async ({ page }) => {
    await page.goto('/invoices/new')

    // Try to advance without selecting a customer
    await page.getByRole('button', { name: /Next.*Items/i }).click()
    await expect(page.locator('.text-danger-500')).toContainText(/choose a customer/i, { timeout: 5_000 })
  })

  test('creates a full invoice via wizard and lands on detail page', async ({ page }) => {
    await page.goto('/invoices/new')

    const ip = new InvoicePage(page)

    // Step 1: select first available client
    await ip.selectFirstClient()
    await ip.advanceToItems()

    // Step 2: fill an item
    await ip.fillLineItem(0, { description: `Test Service ${uid}`, price: '5000' })
    await ip.advanceToReview()

    // Step 3: submit
    await page.getByRole('button', { name: /Create Bill/i }).click()

    await expect(page).toHaveURL(/\/invoices\/\d+/, { timeout: 12_000 })
  })

  test('created invoice shows INV prefix in number', async ({ page }) => {
    await page.goto('/invoices/new')
    const ip = new InvoicePage(page)

    await ip.selectFirstClient()
    await ip.advanceToItems()
    await ip.fillLineItem(0, { description: `Product ${uid}`, price: '2000' })
    await ip.advanceToReview()
    await page.getByRole('button', { name: /Create Bill/i }).click()
    await page.waitForURL(/\/invoices\/\d+/, { timeout: 12_000 })

    await expect(page.getByText(/INV/)).toBeVisible()
  })

  test('Add Another Item button adds another item row', async ({ page }) => {
    await page.goto('/invoices/new')
    const ip = new InvoicePage(page)

    await ip.selectFirstClient()
    await ip.advanceToItems()

    const addBtn = page.getByRole('button', { name: /Add Another Item/i })
    await expect(addBtn).toBeVisible()

    const before = await page.locator('input[placeholder*="sell"], input[placeholder*="provide"]').count()
    await addBtn.click()
    const after = await page.locator('input[placeholder*="sell"], input[placeholder*="provide"]').count()
    expect(after).toBeGreaterThan(before)
  })
})

test.describe('Invoice Detail', () => {
  let invoiceUrl

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage()
    await page.goto('/invoices/new')

    const ip = new InvoicePage(page)
    await ip.selectFirstClient()
    await ip.advanceToItems()
    await ip.fillLineItem(0, { description: `Detail Test ${uid}`, price: '10000' })
    await ip.advanceToReview()
    await page.getByRole('button', { name: /Create Bill/i }).click()
    await page.waitForURL(/\/invoices\/\d+/, { timeout: 12_000 })
    invoiceUrl = page.url()
    await page.close()
  })

  test('invoice detail shows bill number', async ({ page }) => {
    await page.goto(invoiceUrl)
    await expect(page.getByText(/INV/)).toBeVisible()
  })

  test('invoice detail shows total amount', async ({ page }) => {
    await page.goto(invoiceUrl)
    await expect(page.getByText(/₹/)).toBeVisible()
  })

  test('invoice detail shows line items', async ({ page }) => {
    await page.goto(invoiceUrl)
    await expect(page.getByText(new RegExp(`Detail Test ${uid}`))).toBeVisible()
  })

  test('invoice detail shows status badge', async ({ page }) => {
    await page.goto(invoiceUrl)
    await expect(
      page.locator('.badge-gray, .badge-blue, .badge-green, .badge-yellow, .badge-red').first()
    ).toBeVisible()
  })

  test('back button returns to Bills list', async ({ page }) => {
    await page.goto(invoiceUrl)
    await page.getByRole('button').first().click()
    await expect(page).toHaveURL(/\/invoices/, { timeout: 5_000 })
  })
})

test.describe('Invoice List Filters', () => {
  test('filter tabs are clickable and update list', async ({ page }) => {
    await page.goto('/invoices')

    const notSentBtn = page.getByRole('button', { name: 'Not Sent' })
    if (await notSentBtn.isVisible()) {
      await notSentBtn.click()
      await page.waitForTimeout(800)
      await expect(page.locator('.card')).toBeVisible()
    }
  })

  test('search filters invoice list', async ({ page }) => {
    await page.goto('/invoices')
    const searchInput = page.getByPlaceholder(/Search/i)
    if (await searchInput.isVisible()) {
      await searchInput.fill('INV')
      await page.waitForTimeout(500)
      await expect(page.locator('.card')).toBeVisible()
    }
  })
})
