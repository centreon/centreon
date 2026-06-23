import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for Centreon end-to-end tests (proof of concept).
 *
 * The tests run against the Centreon web stack started with the docker compose
 * file located at `.github/docker/docker-compose.yml`. The `web` service exposes
 * the application on http://localhost:4000 and Centreon is served under `/centreon`.
 *
 * The required services are ensured automatically at the start of the run
 * (`global-setup.ts` for the web stack, the OIDC spec's `beforeAll` for the
 * `openid` profile): if a needed service is not running it is started, so you
 * normally do not have to run `pnpm stack:up` yourself. Set
 * SKIP_STACK_MANAGEMENT=1 to manage the stack manually.
 */

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

// In CI each test folder runs in its own job (see the `playwright-e2e-test`
// matrix). Each job produces a blob report whose file name is unique per folder
// (`PW_BLOB_NAME`) so the reports can be downloaded into a single directory and
// merged into one HTML report without clobbering each other.
const blobFileName = process.env.PW_BLOB_NAME;

export default defineConfig({
  expect: {
    timeout: 15_000
  },
  forbidOnly: !!process.env.CI,
  // Tests share a single platform instance and authenticate against it, so they
  // must run serially (`workers: 1`) to avoid cross-test session interference.
  fullyParallel: false,
  // One-time provisioning shared by the dashboard specs (feature flag + ACL
  // user). Skipped automatically when SKIP_GLOBAL_SETUP is set.
  globalSetup: require.resolve('./global-setup'),
  projects: [
    {
      // Logs in once as the dashboard creator and saves the session; the
      // dashboard specs reuse it through `test.use({ storageState })`.
      name: 'setup',
      testMatch: /.*\.setup\.ts/
    },
    {
      dependencies: ['setup'],
      name: 'chromium',
      testIgnore: [
        '**/authentication/oidc-authentication.spec.ts',
        '**/*.setup.ts'
      ],
      use: { ...devices['Desktop Chrome'] }
    },
    {
      // OIDC tests need the docker compose `openid` profile. The browser must
      // reach Keycloak at the same host name the `web` container uses
      // (`sso-proxy`), so it is mapped to the published port on localhost.
      name: 'oidc',
      testMatch: '**/authentication/oidc-authentication.spec.ts',
      // Allow extra time: the first OIDC test may start the openid profile
      // (pulling and booting Keycloak from a cold CI runner can be slow).
      timeout: 600_000,
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: {
          args: ['--host-resolver-rules=MAP sso-proxy 127.0.0.1']
        }
      }
    }
  ],
  reporter: blobFileName
    ? [['list'], ['blob', { fileName: blobFileName }]]
    : [['list'], ['html', { open: 'never' }]],
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
  workers: 1
});
