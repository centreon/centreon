import { defineConfig, devices } from '@playwright/experimental-ct-react';

/**
 * Playwright **Component Testing** configuration (proof of concept).
 *
 * Unlike the end-to-end suite (`tests/e2e-playwright`, which drives the running
 * app over HTTP), component tests mount real React components in a browser via
 * Vite, so this config lives in the frontend workspace where the component
 * sources and their dependencies (MUI, …) resolve.
 *
 * Specs are colocated with the components as `*.pw.spec.tsx` (mirroring the
 * Cypress `*.cypress.spec.tsx`). Visual baselines produced by
 * `toHaveScreenshot()` are centralised under `tests/component-playwright`
 * instead of scattering `-snapshots` folders across `www/front_src`.
 */
export default defineConfig({
  // Baselines are committed PNGs; allow a small pixel tolerance so sub-pixel
  // font-rendering differences between the machine that generated them and the
  // CI runner do not fail the run.
  expect: {
    toHaveScreenshot: { maxDiffPixelRatio: 0.05 }
  },
  forbidOnly: !!process.env.CI,
  fullyParallel: true,
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] }
    }
  ],
  reporter: [['list'], ['html', { open: 'never' }]],
  retries: process.env.CI ? 1 : 0,
  snapshotPathTemplate:
    'tests/component-playwright/__snapshots__/{testFileName}/{arg}-{projectName}-{platform}{ext}',
  testDir: './www/front_src/src',
  testMatch: '**/*.pw.spec.tsx',
  use: {
    ctPort: 3100,
    trace: 'retain-on-failure'
  }
});
