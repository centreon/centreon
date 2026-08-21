import { defineConfig, devices } from '@playwright/test';

const galleryPort = Number(process.env.GALLERY_PORT ?? 9093);
const galleryUrl = `http://localhost:${galleryPort}/playwright/gallery/index.html`;

export default defineConfig({
  expect: { timeout: 10_000 },
  forbidOnly: Boolean(process.env.CI),
  fullyParallel: true,
  outputDir: './playwright/results/artifacts',
  projects: [
    {
      name: 'components',
      testDir: './tests/components',
      use: {
        ...devices['Desktop Chrome'],
        // mount() navigates here, so baseURL must be the gallery page.
        baseURL: galleryUrl,
        reuseContext: true,
        serviceWorkers: 'block',
        // Same viewport as cy.adjustViewport(), for comparable screenshots.
        viewport: { height: 590, width: 1280 }
      }
    }
  ],
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: './playwright/results/html' }]
  ],
  retries: process.env.CI ? 1 : 0,
  // Not "screenshots": the repository .gitignore ignores **/screenshots, and
  // this mirrors cypress/visual-testing-snapshots.
  snapshotPathTemplate:
    './playwright/visual-testing-snapshots/{testFileName}/{arg}-{projectName}{ext}',
  timeout: 60_000,
  use: {
    screenshot: 'only-on-failure',
    testIdAttribute: 'data-testid',
    trace: 'retain-on-failure'
  },
  webServer: {
    // Direct binary: `pnpm run` currently fails on this checkout because the
    // allowBuilds entries of pnpm-workspace.yaml are unresolved placeholders.
    command: 'node_modules/.bin/rspack serve -c ./rspack.config.gallery.js',
    reuseExistingServer: !process.env.CI,
    timeout: 300_000,
    url: galleryUrl
  }
});
