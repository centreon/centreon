import '@testing-library/jest-dom';
// The app's Tailwind entry, so utility classes produce real styles.
import '../www/front_src/src/app.css';

import dayjs from 'dayjs';
import duration from 'dayjs/plugin/duration';
import isBetween from 'dayjs/plugin/isBetween';
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore';
import isToday from 'dayjs/plugin/isToday';
import isYesterday from 'dayjs/plugin/isYesterday';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import weekday from 'dayjs/plugin/weekday';
import React from 'react';

import { resetRequests } from './api';
import { worker } from './mswWorker';

// The rspack build provides React globally (ProvidePlugin): mirror it.
(globalThis as unknown as { React: typeof React }).React = React;

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(isToday);
dayjs.extend(isYesterday);
dayjs.extend(weekday);
dayjs.extend(isBetween);
dayjs.extend(isSameOrBefore);
dayjs.extend(duration);

// Chromium reports "ResizeObserver loop completed with undelivered
// notifications" when a callback re-measures heavily in one frame, and the
// runner fails the whole file on it. Delivering the entries one frame later
// breaks that loop and still notifies the observers.
const NativeResizeObserver = window.ResizeObserver;
window.ResizeObserver = class extends NativeResizeObserver {
  constructor(callback: ResizeObserverCallback) {
    super((entries, observer) => {
      window.requestAnimationFrame(() => callback(entries, observer));
    });
  }
};

// No transitions nor animations: what the tests read is the end state.
document.head.insertAdjacentHTML(
  'beforeend',
  '<style>*, *::before, *::after { transition: none !important; animation: none !important; }</style>'
);

beforeAll(async () => {
  await worker.start({ onUnhandledRequest: 'bypass', quiet: true });
});

// React Testing Library declares a jsdom-style act environment, in which
// React warns about every state update made outside `act()`. In a real
// browser the page updates on its own (queries resolving, popovers settling)
// and the tests wait for it with `findBy*`/`waitFor`, so those warnings are
// noise. Off for each test, after the library's own `beforeAll` turned it on;
// its `render` and events still turn it on while they run.
beforeEach(() => {
  (
    globalThis as unknown as { IS_REACT_ACT_ENVIRONMENT: boolean }
  ).IS_REACT_ACT_ENVIRONMENT = false;
});

// Cypress starts every test from empty storage and a clean request log; so do
// the tests here.
afterEach(() => {
  worker.resetHandlers();
  resetRequests();
  window.localStorage.clear();
  window.sessionStorage.clear();
});

afterAll(() => {
  worker.stop();
});
