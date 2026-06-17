import { pluginReact } from '@rsbuild/plugin-react';
import { defineConfig } from '@rstest/core';

/**
 * Rstest Browser Mode config: runs component tests in a REAL browser (Playwright
 * provider), like Cypress CT — the apples-to-apples comparison. Experimental in
 * Rstest 0.10.x.
 */
export default defineConfig({
  browser: {
    browser: 'chromium',
    enabled: true,
    headless: true,
    provider: 'playwright'
  },
  include: ['rstest-poc/**/*.browser.spec.tsx'],
  plugins: [pluginReact()],
  setupFiles: ['./rstest-poc/browser.setup.ts']
});
