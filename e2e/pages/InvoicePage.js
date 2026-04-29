export class InvoicePage {
  constructor(page) {
    this.page = page
  }

  async gotoList() { await this.page.goto('/invoices') }
  async gotoNew()  { await this.page.goto('/invoices/new') }

  /**
   * Select a client in the wizard step 1.
   * If ≤8 clients: card buttons are shown — click the matching one.
   * If >8 clients: a <select> is shown — use selectOption.
   */
  async selectClient(clientName) {
    const cardBtn = this.page.getByRole('button', { name: new RegExp(clientName, 'i') })
    if (await cardBtn.first().isVisible({ timeout: 3_000 }).catch(() => false)) {
      await cardBtn.first().click()
    } else {
      await this.page.locator('select').filter({ hasText: /customer/i }).selectOption({ label: clientName })
    }
  }

  /** Select first available client (by index on the card grid or select). */
  async selectFirstClient() {
    // Try card buttons first
    const cards = this.page.locator('.grid button').filter({ hasText: /[A-Z]/ })
    if (await cards.count() > 0) {
      await cards.first().click()
    } else {
      await this.page.locator('select').first().selectOption({ index: 1 })
    }
  }

  async advanceToItems() {
    await this.page.getByRole('button', { name: /Next.*Items/i }).click()
  }

  async advanceToReview() {
    await this.page.getByRole('button', { name: /Review.*Bill/i }).click()
  }

  async fillLineItem(index = 0, { description, quantity = 1, price }) {
    // Description field
    const descInputs = this.page.locator('input[placeholder*="sell"], input[placeholder*="provide"]')
    await descInputs.nth(index).fill(description)

    // Price field
    const priceInputs = this.page.locator('input[placeholder="0.00"]')
    await priceInputs.nth(index).fill(String(price))
  }

  async addItem() {
    await this.page.getByRole('button', { name: /Add Another Item/i }).click()
  }

  /** Full wizard submit: advances through all 3 steps and submits. */
  async submit() {
    // Step 1 → 2
    const nextBtn = this.page.getByRole('button', { name: /Next.*Items/i })
    if (await nextBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await nextBtn.click()
    }
    // Step 2 → 3
    const reviewBtn = this.page.getByRole('button', { name: /Review.*Bill/i })
    if (await reviewBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await reviewBtn.click()
    }
    // Step 3: Create Bill
    await this.page.getByRole('button', { name: /Create Bill|Save Changes/i }).click()
  }

  async recordPayment(amount) {
    await this.page.getByRole('button', { name: /Record Payment|Add Payment/i }).click()
    await this.page.getByLabel(/Amount/i).fill(String(amount))
    await this.page.getByRole('button', { name: /Save|Confirm|Record/i }).last().click()
  }

  async searchFor(term) {
    await this.page.getByPlaceholder(/Search/i).fill(term)
    await this.page.waitForTimeout(500)
  }

  errorMsg() { return this.page.locator('.text-danger-500') }
}
