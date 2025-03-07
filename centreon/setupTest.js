import '@testing-library/jest-dom';
import 'dayjs/locale/en';

import { TextDecoder, TextEncoder } from 'util';
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
import i18n from 'i18next';
import fetchMock from 'jest-fetch-mock';
import React from 'react';
import { initReactI18next } from 'react-i18next';
import ResizeObserver from 'resize-observer-polyfill';

Object.assign(global, { TextDecoder, TextEncoder });

window.ResizeObserver = ResizeObserver;
window.React = React;

jest.setTimeout(15000);

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);
dayjs.extend(isToday);
dayjs.extend(isYesterday);
dayjs.extend(weekday);
dayjs.extend(isBetween);
dayjs.extend(isSameOrBefore);
dayjs.extend(duration);

class IntersectionObserver {
  observe = jest.fn();

  unobserve = jest.fn();

  disconnect = jest.fn();

  current = this;
}

Object.defineProperty(window, 'IntersectionObserver', {
  configurable: true,
  value: IntersectionObserver,
  writable: true
});

Object.defineProperty(global, 'IntersectionObserver', {
  configurable: true,
  value: IntersectionObserver,
  writable: true
});

i18n.use(initReactI18next).init({
  fallbackLng: 'en',
  keySeparator: false,
  lng: 'en',
  nsSeparator: false,
  resources: {}
});

jest.mock('@centreon/ui-context', () => ({
  ...jest.requireActual('./packages/ui-context'),
  ThemeMode: 'light'
}));

fetchMock.enableMocks();
