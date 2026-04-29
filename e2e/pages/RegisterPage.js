export class RegisterPage {
  constructor(page) {
    this.page = page
  }

  async goto() { await this.page.goto('/register') }

  // Step 1 — personal details
  async fillStep1({ name, email, mobile, password }) {
    await this.page.getByLabel('Full Name').fill(name)
    await this.page.getByLabel('Email').fill(email)
    await this.page.getByLabel('Mobile Number').fill(mobile)
    await this.page.getByLabel('Password').fill(password)
    await this.page.getByLabel('Confirm Password').fill(password)
    await this.page.getByRole('button', { name: 'Continue' }).click()
  }

  // Step 2 — business details
  async fillStep2({ businessName, stateText = 'Tamil Nadu' }) {
    await this.page.getByLabel(/Business.*Name/i).fill(businessName)
    await this.page.getByLabel('State').selectOption({ label: stateText })
    await this.page.getByRole('button', { name: 'Create Free Account' }).click()
  }

  errorMsg() { return this.page.locator('.text-danger-500') }
}
