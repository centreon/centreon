import { afterEach, expect } from '@rstest/core';
import * as matchers from '@testing-library/jest-dom/matchers';
import { cleanup } from '@testing-library/react';
import ResizeObserver from 'resize-observer-polyfill';

// Extend Rstest's expect with the jest-dom matchers (toBeInTheDocument, ...).
expect.extend(matchers);

// Testing Library only auto-registers cleanup when it detects the runner's
// global afterEach; under Rstest we wire it explicitly so the DOM is reset
// between tests (otherwise rendered trees accumulate across tests).
afterEach(cleanup);

// Some @centreon/ui components rely on these browser APIs absent from jsdom.
globalThis.ResizeObserver = ResizeObserver;

if (!globalThis.IntersectionObserver) {
  class IntersectionObserver {
    observe = (): void => undefined;
    unobserve = (): void => undefined;
    disconnect = (): void => undefined;
    takeRecords = (): [] => [];
    root = null;
    rootMargin = '';
    thresholds = [];
  }
  globalThis.IntersectionObserver =
    IntersectionObserver as unknown as typeof globalThis.IntersectionObserver;
}
