import { defineConfig, devices } from '@playwright/test'
import { fileURLToPath } from 'url'
import path from 'path'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

export default defineConfig({
  // Keep setup and specs under one root so the authentication dependency is
  // discovered on clean machines as well as machines with a cached auth file.
  testDir: './e2e',
  fullyParallel: false,       // run sequentially — tests share created data
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],

  use: {
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:5173',
    storageState: path.join(__dirname, 'e2e/.auth/user.json'),
    ...(process.platform === 'win32' ? { channel: 'msedge' } : {}),
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    // Traces and screenshots already capture failures. Keeping video disabled
    // lets the suite run on customer-like Windows PCs without Playwright ffmpeg.
    video: 'off',
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
      use: {
        ...devices['Desktop Chrome'],
        // The desktop integration suite already relies on installed Edge on
        // Windows. Reuse it here instead of requiring a separate browser download.
        ...(process.platform === 'win32' ? { channel: 'msedge' } : {}),
      },
      dependencies: ['setup'],
    },
  ],

  // Start the Vite dev server automatically
  webServer: {
    command: 'npm run e2e:server',
    url: 'http://127.0.0.1:5173',
    reuseExistingServer: true,
    timeout: 30_000,
  },
})
