import { chromium } from '@playwright/test'
import path from 'node:path'

const base = process.env.BASE_URL || 'http://127.0.0.1:5173'
const browser = await chromium.launch({ headless: true, ...(process.platform === 'win32' ? { channel: 'msedge' } : {}) })
const context = await browser.newContext({ storageState: path.resolve('e2e/.auth/user.json') })
const page = await context.newPage()
const failures = []
let route = ''

page.on('pageerror', error => failures.push(`${route}: browser error: ${error.message}`))
page.on('response', response => {
  if (response.status() >= 500) failures.push(`${route}: HTTP ${response.status()} ${response.url()}`)
})

const routes = [
  '/', '/pos', '/clients', '/clients/new', '/invoices', '/invoices/new',
  '/quotes', '/quotes/new', '/expenses', '/expenses/new', '/payroll',
  '/products', '/products/new', '/inventory', '/credit-notes',
  '/purchase-orders', '/purchases', '/purchase-returns', '/purchase-returns/new',
  '/delivery-challans', '/timesheets',
  '/gst-returns', '/reports', '/settings', '/help', '/more',
]

try {
  for (route of routes) {
    const response = await page.goto(base + route, { waitUntil: 'domcontentloaded', timeout: 20_000 })
    await page.waitForTimeout(350)
    const text = (await page.locator('body').innerText()).trim()
    if (!response?.ok()) failures.push(`${route}: document HTTP ${response?.status()}`)
    if (text.length < 20) failures.push(`${route}: page rendered no useful content`)
    console.log(`OK ${route} -> ${new URL(page.url()).pathname}`)
  }
} finally {
  await browser.close()
}

if (failures.length) {
  console.error(failures.join('\n'))
  process.exitCode = 1
} else {
  console.log(`PASS: ${routes.length} authenticated web routes rendered without browser exceptions or server errors.`)
}
