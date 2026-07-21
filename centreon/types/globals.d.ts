import type {} from 'react';
import '@testing-library/jest-dom';

declare global {
  // biome-ignore lint/style/noNamespace: required for global JSX type augmentation
  namespace JSX {
    type Element = React.JSX.Element;
    type IntrinsicElements = React.JSX.IntrinsicElements;
    type ElementClass = React.JSX.ElementClass;
  }
  interface Window {
    Cypress?: { testingType?: string };
  }
}
