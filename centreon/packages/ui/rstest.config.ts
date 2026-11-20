import { defineConfig } from '@rstest/core';

import appConfig from '../../rstest.config';

// The app's browser setup (`centreon/rstest.config.ts`), for the
// `@centreon/ui` component tests: `src/**/*.browser.test.tsx`.
export default defineConfig({
  ...appConfig,
  coverage: {
    ...appConfig.coverage,
    // The files the Cypress coverage of this package leaves out, plus the
    // tests and their setup.
    exclude: [
      '**/cypress/**',
      '**/node_modules/**',
      '**/test-setup/**',
      '**/*.js',
      '**/*.cypress.spec.tsx',
      '**/*.browser.test.{ts,tsx}'
    ]
  },
  include: ['src/**/*.browser.test.{ts,tsx}'],
  name: 'ui-browser',
  root: import.meta.dirname
});
