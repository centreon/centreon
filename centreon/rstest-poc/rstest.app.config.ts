import { pluginReact } from '@rsbuild/plugin-react';
import { defineConfig } from '@rstest/core';

/**
 * Phase 0 harness: run real app component tests (www/front_src) under Rstest
 * jsdom — the realistic migration target for Centreon's Cypress component tests
 * (providers + MSW API interception), minus the real browser and visual snapshots.
 */
export default defineConfig({
  include: ['rstest-poc/app/**/*.app.spec.tsx'],
  plugins: [pluginReact()],
  setupFiles: ['./rstest-poc/app/app.setup.ts'],
  testEnvironment: 'jsdom'
});
