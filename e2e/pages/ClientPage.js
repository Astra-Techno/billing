export class ClientPage {
  constructor(page) {
    this.page = page
  }

  async gotoList()  { await this.page.goto('/clients') }
  async gotoNew()   { await this.page.goto('/clients/new') }

  async fillBasicDetails({ name, mobile, email = '', gstin = '' }) {
    await this.page.getByLabel(/Business Name|Full Name/i).fill(name)
    await this.page.getByLabel(/^Mobile Number \*/i).fill(mobile)
    if (email)  await this.page.getByLabel(/^Email Address$/i).fill(email)
    if (gstin)  await this.page.getByLabel(/GST Number/i).fill(gstin)
  }

  async fillAddress({ line1 = '', city = '', pincode = '' } = {}) {
    if (line1)   await this.page.getByLabel(/Street|Shop Number/i).fill(line1)
    if (city)    await this.page.getByLabel('City').fill(city)
    if (pincode) await this.page.getByLabel(/PIN Code|Pincode/i).fill(pincode)
  }

  async save() {
    await this.page.getByRole('button', { name: /Add Customer|Save Changes/i }).click()
  }

  async searchFor(term) {
    await this.page.getByPlaceholder(/Search by name/i).fill(term)
    await this.page.waitForTimeout(500) // debounce
  }

  errorMsg() { return this.page.locator('.text-danger-500') }
}
