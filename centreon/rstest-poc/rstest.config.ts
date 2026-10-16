import { pluginReact } from '@rsbuild/plugin-react';
import { defineConfig } from '@rstest/core';

/**
 * Rstest proof of concept for Centreon component tests.
 *
 * Rstest is powered by Rspack — the same bundler Centreon uses in production —
 * so components are bundled in tests exactly as they are shipped (no Vite/jest
 * transform divergence). This config mirrors the minimum the existing Jest
 * setup needs: the React plugin (JSX/automatic runtime) and a jsdom environment.
 */
export default defineConfig({
  include: ['rstest-poc/**/*.rstest.spec.{ts,tsx}'],
  plugins: [pluginReact()],
  setupFiles: ['./rstest-poc/rstest.setup.ts'],
  testEnvironment: 'jsdom'
});
