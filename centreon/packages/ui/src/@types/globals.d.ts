import { FunctionComponent, SVGProps } from 'react';
import '@testing-library/jest-dom/types/jest';

declare module '*.scss';

declare module '*.module.css' {
  const classes: { readonly [key: string]: string };
  export default classes;
  export const button: string;
  export const modalBody: string;
  export const modalHeader: string;
}

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
