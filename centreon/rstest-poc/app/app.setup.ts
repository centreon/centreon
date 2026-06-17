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
beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterEach(() => {
  server.resetHandlers();
  resetInterceptions();
  cleanup();
});
afterAll(() => server.close());
