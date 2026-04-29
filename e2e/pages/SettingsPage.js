export class SettingsPage {
  constructor(page) {
    this.page = page
  }

  async goto() { await this.page.goto('/settings') }

  async switchTab(label) {
    await this.page.getByRole('button', { name: label }).click()
  }

  async fillBusinessName(name) {
    await this.page.getByLabel('Business Name *').fill(name)
  }

  async fillGstin(gstin) {
    await this.page.getByLabel(/GSTIN|GST Number/i).fill(gstin)
  }

  async fillBank({ bankName, accountNo, ifsc, holderName }) {
    if (bankName)    await this.page.getByLabel('Bank Name').fill(bankName)
    if (accountNo)   await this.page.getByLabel('Account Number').fill(accountNo)
    if (ifsc)        await this.page.getByLabel('IFSC Code').fill(ifsc)
    if (holderName)  await this.page.getByLabel('Account Holder Name').fill(holderName)
  }

  async saveBusinessProfile() {
    await this.page.getByRole('button', { name: 'Save Business Profile' }).click()
  }

  async saveGst() {
    await this.page.getByRole('button', { name: 'Save GST Details' }).click()
  }

  async saveBank() {
    await this.page.getByRole('button', { name: 'Save Bank Details' }).click()
  }

  async saveInvoice() {
    await this.page.getByRole('button', { name: 'Save Bill Settings' }).click()
  }

  successBanner() { return this.page.locator('.text-success-700') }
}
