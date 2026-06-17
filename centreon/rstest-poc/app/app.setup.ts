import { afterAll, afterEach, beforeAll, expect } from '@rstest/core';
import * as matchers from '@testing-library/jest-dom/matchers';
import { cleanup } from '@testing-library/react';
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import ResizeObserver from 'resize-observer-polyfill';

import { resetInterceptions, server } from './server';

// jest-dom matchers.
expect.extend(matchers);

// jsdom polyfills the app components rely on.
globalThis.ResizeObserver = ResizeObserver;
if (!window.matchMedia) {
  window.matchMedia = ((query: string) => ({
    addEventListener: () => undefined,
    addListener: () => undefined,
    dispatchEvent: () => false,
    matches: false,
    media: query,
    onchange: null,
    removeEventListener: () => undefined,
    removeListener: () => undefined
  })) as unknown as typeof window.matchMedia;
}

i18n.use(initReactI18next).init({
  fallbackLng: 'en',
  keySeparator: false,
  lng: 'en',
  nsSeparator: false,
  resources: {}
});

// MSW lifecycle (replaces cypress-msw-interceptor).
beforeAll(() => {
  server.listen({ onUnhandledRequest: 'bypass' });

  // The app's customFetch issues RELATIVE URLs (e.g. "./api/..."). A real
  // browser resolves them against the page origin; Node's fetch cannot. Wrap
  // fetch (after MSW has patched it) to absolutise relative URLs so both MSW
  // matching and the request work, mirroring browser behaviour.
  const patchedFetch = globalThis.fetch;
  globalThis.fetch = ((input: RequestInfo | URL, init?: RequestInit) => {
    if (typeof input === 'string' && !/^https?:\/\//.test(input)) {
      return patchedFetch(new URL(input, 'http://localhost/').toString(), init);
    }
    return patchedFetch(input, init);
  }) as typeof fetch;
});
afterEach(() => {
  server.resetHandlers();
  resetInterceptions();
  cleanup();
});
afterAll(() => server.close());
