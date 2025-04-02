import { ProvidedZoom, TransformMatrix } from '@visx/zoom/lib/types';

export interface ZoomState {
  transformMatrix: TransformMatrix;
}

export interface Dimension {
  height: number;
  width: number;
}

export type MinimapPosition =
  | 'top-left'
  | 'top-right'
  | 'bottom-left'
  | 'bottom-right';

export interface ZoomInterface {
  zoom: ProvidedZoom<SVGSVGElement> & ZoomState;
}

export interface ChildrenProps extends ZoomState, Dimension, ZoomInterface {
  contentClientRect: Dimension | null;
}

export interface ZoomChildren {
  children: (args: ChildrenProps) => JSX.Element;
}
