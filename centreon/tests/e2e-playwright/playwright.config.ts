import { defineConfig, devices } from '@playwright/test';

import { adminStorageState, baseUrl } from './src/config/environment';

export default defineConfig({
  expect: { timeout: 15_000 },
  forbidOnly: Boolean(process.env.CI),
  // Specs mutating platform-wide settings declare a test lock (see README).
  fullyParallel: true,
  globalSetup: './src/stack/globalSetup.ts',
  globalTeardown: './src/stack/globalTeardown.ts',
  outputDir: './results/artifacts',
  projects: [
    { name: 'setup', testMatch: /.*\.setup\.ts/ },
    {
      dependencies: ['setup'],
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], storageState: adminStorageState }
    }
  ],
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'results/html' }],
    ['junit', { outputFile: 'results/junit.xml' }]
  ],
  retries: process.env.CI ? 1 : 0,
  testDir: './tests',
  timeout: 90_000,
  use: {
    baseURL: baseUrl,
    screenshot: 'only-on-failure',
    testIdAttribute: 'data-testid',
    trace: 'retain-on-failure',
    video: 'retain-on-failure'
  }
});
