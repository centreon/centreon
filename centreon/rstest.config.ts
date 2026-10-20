import { resolve } from 'node:path';

import { defineConfig } from '@rstest/core';
import tailwindcss from '@tailwindcss/postcss';

const root = __dirname;

// Component tests run in a real Chromium (Playwright): `*.browser.test.tsx`.
// Headless by default; `pnpm rstest:ui` opens the browser to debug.
export default defineConfig({
  browser: {
    enabled: true,
    headless: true,
    provider: 'playwright',
    viewport: { height: 1000, width: 1280 }
  },
  // `--coverage` in CI: merged with the Cypress coverage for the coverage
  // gate, so the same files are left out.
  coverage: {
    exclude: [
      '**/cypress/**',
      '**/packages/**',
      '**/node_modules/**',
      '**/test-setup/**',
      '**/*.js',
      '**/*.spec.tsx',
      '**/*.browser.test.{ts,tsx}'
    ],
    provider: 'istanbul',
    reporters: ['json'],
    reportsDirectory: './coverage-rstest'
  },
  globals: true,
  include: [
    'test-setup/**/*.browser.test.{ts,tsx}',
    'www/front_src/src/**/*.browser.test.{ts,tsx}'
  ],
  name: 'browser',
  // In CI, also a JUnit report for the job's test summary.
  reporters: process.env.CI
    ? [
        'default',
        'github-actions',
        ['junit', { outputPath: './rstest-junit.xml' }]
      ]
    : ['default'],
  resolve: {
    alias: {
      '@centreon/ui/components': resolve(root, 'packages/ui/src/components'),
      '@centreon/ui/fonts': resolve(
        root,
        'node_modules/@centreon/ui/public/fonts'
      ),
      '@mui/material': resolve(root, 'node_modules/@mui/material'),
      '@test-setup': resolve(root, 'test-setup'),
      dayjs: resolve(root, 'node_modules/dayjs'),
      // MSW 2 for these tests, while Cypress keeps MSW 1 under `msw`. Drop the
      // alias, and bump `msw`, with the last Cypress component test.
      msw: 'msw-v2'
    }
  },
  // A spy a failing test left in place does not leak into the next one.
  restoreMocks: true,
  // A flaky test fails rather than passing on a second attempt.
  retry: 0,
  setupFiles: [resolve(root, 'test-setup/browser-setup.ts')],
  source: {
    // @centreon/ui and @centreon/ui-context ship TypeScript sources.
    include: [/[\\/]@centreon[\\/]/, /[\\/]packages[\\/]ui(-context)?[\\/]/]
  },
  testTimeout: 20_000,
  tools: {
    postcss: {
      postcssOptions: {
        plugins: [tailwindcss({ base: resolve(root, 'www/front_src/src') })]
      }
    },
    rspack: {
      module: {
        rules: [{ test: /\.icon\.svg$/, use: ['@svgr/webpack'] }]
      }
    },
    swc: {
      // The app build's React Compiler options (@centreon/js-config), so the
      // tests run the code users get.
      jsc: {
        transform: {
          reactCompiler: {
            compilationMode: 'infer',
            panicThreshold: 'none',
            target: '19'
          }
        }
      }
    }
  }
});
