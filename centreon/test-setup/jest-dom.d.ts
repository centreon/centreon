import type { TestingLibraryMatchers } from '@testing-library/jest-dom/matchers';

// The jest-dom matchers browser-setup.ts adds to rstest's `expect`.
declare module '@rstest/core' {
  interface Assertion<T = unknown> extends TestingLibraryMatchers<unknown, T> {}
}
