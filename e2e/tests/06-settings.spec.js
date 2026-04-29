/**
 * Settings Tests — Business Profile, GST, Bank, Bill Settings tabs
 */
import { test, expect }   from '@playwright/test'
import { SettingsPage }   from '../pages/SettingsPage.js'

test.describe('Settings Page', () => {
  test('shows Settings heading and tabs', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()

    await expect(page.getByRole('heading', { name: 'Settings' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'My Business' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'GST Details' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Payment Info' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Bill Settings' })).toBeVisible()
  })

  test('My Business tab is active by default', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()

    await expect(page.getByText('Business Profile')).toBeVisible()
    await expect(page.getByLabel('Business Name *')).toBeVisible()
  })

  test('loads existing business name from API', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()

    const nameInput = page.getByLabel('Business Name *')
    await expect(nameInput).not.toBeEmpty({ timeout: 5_000 })
  })
})

test.describe('Business Profile Tab', () => {
  test('shows all business profile fields', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()

    await expect(page.getByLabel('Business Name *')).toBeVisible()
    await expect(page.getByLabel('Business Type')).toBeVisible()
    await expect(page.getByLabel('Mobile')).toBeVisible()
    await expect(page.getByLabel('Email')).toBeVisible()
    await expect(page.getByLabel('Website')).toBeVisible()
    await expect(page.getByLabel('City')).toBeVisible()
    await expect(page.getByLabel('State')).toBeVisible()
    await expect(page.getByLabel('Pincode')).toBeVisible()
  })

  test('saves updated business name and shows success banner', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()

    const nameInput = page.getByLabel('Business Name *')
    await nameInput.clear()
    await nameInput.fill(`E2E Business ${Date.now()}`)
    await sp.saveBusinessProfile()

    await expect(sp.successBanner()).toContainText(/saved/i, { timeout: 8_000 })
  })

  test('business type dropdown has expected options', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()

    const select = page.getByLabel('Business Type')
    await expect(select).toBeVisible()

    const options = select.locator('option')
    await expect(options).toHaveCount(8)
    await expect(options.filter({ hasText: 'Proprietorship' })).toBeVisible()
    await expect(options.filter({ hasText: 'Private Limited' })).toBeVisible()
  })
})

test.describe('GST Details Tab', () => {
  test('switching to GST Details tab shows GSTIN, PAN, CIN fields', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()
    await sp.switchTab('GST Details')

    await expect(page.getByText(/GST Registration/i)).toBeVisible()
    await expect(page.getByLabel(/GSTIN|GST Number/i)).toBeVisible()
    await expect(page.getByLabel('PAN Number')).toBeVisible()
    await expect(page.getByLabel(/CIN/i)).toBeVisible()
  })

  test('GSTIN field has 15-char limit', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()
    await sp.switchTab('GST Details')

    const gstInput = page.getByLabel(/GSTIN|GST Number/i)
    await expect(gstInput).toHaveAttribute('maxlength', '15')
  })

  test('saves GST details and shows success banner', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()
    await sp.switchTab('GST Details')

    await page.getByLabel('PAN Number').clear()
    await page.getByLabel('PAN Number').fill('AABCU9603R')
    await sp.saveGst()

    await expect(sp.successBanner()).toContainText(/saved/i, { timeout: 8_000 })
  })
})

test.describe('Payment Info Tab', () => {
  test('switching to Payment Info tab shows bank fields', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()
    await sp.switchTab('Payment Info')

    await expect(page.getByText('Bank Account')).toBeVisible()
    await expect(page.getByLabel('Bank Name')).toBeVisible()
    await expect(page.getByLabel('Account Number')).toBeVisible()
    await expect(page.getByLabel('IFSC Code')).toBeVisible()
    await expect(page.getByLabel('Account Holder Name')).toBeVisible()
    await expect(page.getByLabel('UPI ID')).toBeVisible()
  })

  test('saves bank details and shows success banner', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()
    await sp.switchTab('Payment Info')

    await sp.fillBank({
      bankName:    'State Bank of India',
      accountNo:   '12345678901',
      ifsc:        'SBIN0001234',
      holderName:  'Test Business',
    })
    await page.getByLabel('UPI ID').fill('testbiz@upi')
    await sp.saveBank()

    await expect(sp.successBanner()).toContainText(/saved/i, { timeout: 8_000 })
  })

  test('IFSC field has 11-char limit', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()
    await sp.switchTab('Payment Info')

    await expect(page.getByLabel('IFSC Code')).toHaveAttribute('maxlength', '11')
  })
})

test.describe('Bill Settings Tab', () => {
  test('switching to Bill Settings tab shows prefix and default text fields', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()
    await sp.switchTab('Bill Settings')

    await expect(page.getByText(/Bill Numbering/i)).toBeVisible()
    await expect(page.getByLabel(/Bill Number Prefix/i)).toBeVisible()
    await expect(page.getByLabel(/Quotation Number Prefix/i)).toBeVisible()
    await expect(page.getByLabel(/Message to Customer/i)).toBeVisible()
    await expect(page.getByLabel('Terms & Conditions')).toBeVisible()
  })

  test('bill prefix defaults to INV', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()
    await sp.switchTab('Bill Settings')

    const prefixInput = page.getByLabel(/Bill Number Prefix/i)
    const value = await prefixInput.inputValue()
    expect(value).toBe('INV')
  })

  test('saves bill settings and shows success banner', async ({ page }) => {
    const sp = new SettingsPage(page)
    await sp.goto()
    await sp.switchTab('Bill Settings')

    await page.getByLabel(/Message to Customer/i).fill('Thank you for your business!')
    await page.getByLabel('Terms & Conditions').fill('Payment due within 30 days.')
    await sp.saveInvoice()

    await expect(sp.successBanner()).toContainText(/saved/i, { timeout: 8_000 })
  })
})
