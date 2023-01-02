// import '@testing-library/jest-dom/extend-expect';
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import fetchMock from 'jest-fetch-mock';

const mockedMatchMedia = () => ({
  addListener: () => {},
  matches: false,
  removeListener: () => {}
});

window.matchMedia = window.matchMedia || mockedMatchMedia;

document.createRange = () => ({
  commonAncestorContainer: {
    nodeName: 'BODY',
    ownerDocument: document
  },
  setEnd: () => {},
  setStart: () => {}
});

global.IntersectionObserver = class IntersectionObserver {
  observe() {
    this.a = '';

    return null;
  }

  disconnect() {
    this.a = '';

    return null;
  }
};

Object.defineProperty(window, 'matchMedia', {
  value: jest.fn().mockImplementation((query) => ({
    addEventListener: jest.fn(),
    addListener: jest.fn(),
    dispatchEvent: jest.fn(),
    matches: false,
    media: query,
    onchange: null,
    removeEventListener: jest.fn(),
    removeListener: jest.fn()
  })),
  writable: true
});

i18n.use(initReactI18next).init({
  fallbackLng: 'en',
  keySeparator: false,
  lng: 'en',
  nsSeparator: false,
  resources: {}
});

window.Image = () => ({
  onerror: () => {},
  onload: () => {},
  src: null
});

fetchMock.enableMocks();
