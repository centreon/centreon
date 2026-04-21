import { FunctionComponent, SVGProps } from 'react';
import '@testing-library/jest-dom/types/jest';

declare module '*.scss';

declare module '*.svg' {
  export const ReactComponent: FunctionComponent<
    SVGProps<SVGSVGElement> & { title?: string }
  >;
}

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
