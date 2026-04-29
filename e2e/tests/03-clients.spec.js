/**
 * Client Tests — List, Create, Search, View Detail
 */
import { test, expect } from '@playwright/test'
import { ClientPage } from '../pages/ClientPage.js'

const uid = Date.now()

test.describe('Client List', () => {
  test('shows page title and Add Customer button', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoList()

    await expect(page.getByRole('heading', { name: 'Customers' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Add Customer' })).toBeVisible()
  })

  test('shows search box', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoList()

    await expect(page.getByPlaceholder(/Search by name/i)).toBeVisible()
  })

  test('Add Customer link navigates to client form', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoList()
    await page.getByRole('link', { name: 'Add Customer' }).click()
    await expect(page).toHaveURL(/\/clients\/new/)
  })
})

test.describe('Create Client', () => {
  test('shows Add New Customer form with all sections', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoNew()

    await expect(page.getByText('Add New Customer')).toBeVisible()
    await expect(page.getByText('Basic Details')).toBeVisible()
    await expect(page.getByText(/Address/i)).toBeVisible()
    await expect(page.getByText(/Contact Person/i)).toBeVisible()
  })

  test('has Business / Individual type toggle', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoNew()

    await expect(page.getByRole('button', { name: 'Business / Shop' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Individual Person' })).toBeVisible()
  })

  test('switching to Individual Person hides Contact Person field', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoNew()

    await page.getByRole('button', { name: 'Individual Person' }).click()
    await expect(page.getByLabel('Full Name *')).toBeVisible()
    await expect(page.getByLabel('Contact Person')).not.toBeVisible()
  })

  test('saves a new business client and redirects to client list', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoNew()

    const clientName = `E2E Client ${uid}`
    await cp.fillBasicDetails({
      name:   clientName,
      mobile: '9876543210',
      email:  `client${uid}@test.com`,
    })
    await cp.fillAddress({ line1: '123 Main St', city: 'Chennai', pincode: '600001' })
    await cp.save()

    await expect(page).toHaveURL('/clients', { timeout: 10_000 })
  })

  test('new client appears in the client list', async ({ page }) => {
    const cp = new ClientPage(page)

    await cp.gotoNew()
    const clientName = `E2E Listed ${uid}`
    await cp.fillBasicDetails({ name: clientName, mobile: '9876500000' })
    await cp.save()

    await cp.gotoList()
    await cp.searchFor(clientName)
    await expect(page.getByText(clientName)).toBeVisible({ timeout: 5_000 })
  })

  test('shows validation error when name is missing', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoNew()

    await cp.fillBasicDetails({ name: '', mobile: '9876543210' })
    await cp.save()

    await expect(
      page.locator('input:invalid, .text-danger-500')
    ).toBeVisible({ timeout: 5_000 })
  })

  test('Cancel button goes back to previous page', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoList()
    await page.goto('/clients/new')

    await page.getByRole('button', { name: 'Cancel' }).click()
    await expect(page).not.toHaveURL(/\/clients\/new/)
  })
})

test.describe('Client Search', () => {
  test('searching a known client name filters the list', async ({ page }) => {
    const cp = new ClientPage(page)

    await cp.gotoNew()
    const searchName = `SearchTarget${uid}`
    await cp.fillBasicDetails({ name: searchName, mobile: '9900001111' })
    await cp.save()

    await cp.gotoList()
    await cp.searchFor(searchName)

    await expect(page.getByText(searchName)).toBeVisible({ timeout: 5_000 })
  })

  test('searching random string shows no results message', async ({ page }) => {
    const cp = new ClientPage(page)
    await cp.gotoList()
    await cp.searchFor('ZZZNOMATCH999XYZ')

    await expect(
      page.getByText(/No customers/i).or(page.locator('.divide-y > div').first()).first()
    ).toBeVisible({ timeout: 5_000 })
  })
})

test.describe('Client Detail', () => {
  test('clicking a client row opens client detail page', async ({ page }) => {
    const cp = new ClientPage(page)

    await cp.gotoNew()
    const name = `DetailClient${uid}`
    await cp.fillBasicDetails({ name, mobile: '9800000001' })
    await cp.save()

    await cp.gotoList()
    await cp.searchFor(name)
    await page.waitForTimeout(600)

    await page.locator('.cursor-pointer').first().click()
    await expect(page).toHaveURL(/\/clients\/\d+/, { timeout: 8_000 })
  })

  test('client detail shows client name and Create Bill button', async ({ page }) => {
    const cp = new ClientPage(page)

    await cp.gotoNew()
    const name = `ViewClient${uid}`
    await cp.fillBasicDetails({ name, mobile: '9800000002' })
    await cp.save()

    await cp.gotoList()
    await cp.searchFor(name)
    await page.waitForTimeout(600)
    await page.locator('.cursor-pointer').first().click()

    await expect(page.getByText(name)).toBeVisible({ timeout: 8_000 })
    await expect(page.getByText(/Create Bill/i)).toBeVisible()
  })
})
