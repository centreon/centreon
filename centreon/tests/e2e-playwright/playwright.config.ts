import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for Centreon end-to-end tests (proof of concept).
 *
 * The tests run against the Centreon web stack started with the docker compose
 * file located at `.github/docker/docker-compose.yml`. The `web` service exposes
 * the application on http://localhost:4000 and Centreon is served under `/centreon`.
 *
 * Bring the stack up before running the tests:
 *   pnpm stack:up
 * or rely on the `webServer` block below, which starts it automatically and
 * waits until the platform API answers.
 */

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

export default defineConfig({
  expect: {
    timeout: 15_000
  },
  forbidOnly: !!process.env.CI,
  // Tests share a single platform instance and authenticate against it, so they
  // must run serially (`workers: 1`) to avoid cross-test session interference.
  fullyParallel: false,
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] }
    }
  ],
  reporter: [['list'], ['html', { open: 'never' }]],
  retries: process.env.CI ? 1 : 0,
  testDir: './tests',
  // A login flow that boots a fresh platform can be slow on the first run.
  timeout: 90_000,
  use: {
    actionTimeout: 15_000,
    baseURL,
    ignoreHTTPSErrors: true,
    navigationTimeout: 30_000,
    screenshot: 'only-on-failure',
    // Set RECORD_VIDEO=1 (and/or RECORD_TRACE=1) to capture artifacts on every
    // run, including passing tests. Otherwise they are only kept on failure.
    trace: process.env.RECORD_TRACE ? 'on' : 'retain-on-failure',
    video: process.env.RECORD_VIDEO ? 'on' : 'retain-on-failure'
  },
  /**
   * Optionally start the docker compose stack automatically.
   * Disabled by default (set START_STACK=1 to enable) so that developers who
   * already have the stack running do not pay the boot cost on every run.
   */
  webServer: process.env.START_STACK
    ? {
        command:
          'docker compose -f ../../../.github/docker/docker-compose.yml up -d web',
        reuseExistingServer: true,
        timeout: 240_000,
        url: `${baseURL}/api/latest/platform/versions`
      }
    : undefined,
  workers: 1
});
