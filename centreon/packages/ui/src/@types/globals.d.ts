import { FunctionComponent, SVGProps } from 'react';

declare module '*.scss';

declare module '*.svg' {
  export const ReactComponent: FunctionComponent<
    SVGProps<SVGSVGElement> & { title?: string }
  >;
}
