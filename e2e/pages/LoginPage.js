export class LoginPage {
  constructor(page) {
    this.page = page
    this.emailInput    = page.getByLabel('Email address')
    this.passwordInput = page.getByLabel('Password')
    this.submitBtn     = page.getByRole('button', { name: 'Sign In' })
    this.errorMsg      = page.locator('.text-danger-500')
    this.registerLink  = page.getByRole('link', { name: 'Create free account' })
  }

  async goto()                    { await this.page.goto('/login') }
  async fillEmail(email)          { await this.emailInput.fill(email) }
  async fillPassword(pwd)         { await this.passwordInput.fill(pwd) }
  async submit()                  { await this.submitBtn.click() }

  async login(email, password) {
    await this.fillEmail(email)
    await this.fillPassword(password)
    await this.submit()
  }
}
