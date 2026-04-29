import { defineConfig, devices } from '@playwright/test'
import { fileURLToPath } from 'url'
import path from 'path'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

export default defineConfig({
  testDir: './e2e/tests',
  fullyParallel: false,       // run sequentially — tests share created data
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],

  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:5173',
    storageState: path.join(__dirname, 'e2e/.auth/user.json'),
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    // 1. Auth setup — runs first, no stored auth
    {
      name: 'setup',
      testMatch: /auth\.setup\.js/,
      use: { storageState: undefined },
    },

    // 2. All E2E specs — depend on setup
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
  ],

  // Start the Vite dev server automatically
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5173',
    reuseExistingServer: true,
    timeout: 30_000,
  },
})
