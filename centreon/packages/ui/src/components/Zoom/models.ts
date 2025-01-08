import { ProvidedZoom } from '@visx/zoom/lib/types';

export interface TransformMatrix {
  scaleX: number;
  scaleY: number;
  skewX: number;
  skewY: number;
  translateX: number;
  translateY: number;
}

export interface ZoomState {
  transformMatrix: TransformMatrix;
  setTransformMatrix?: ProvidedZoom<SVGSVGElement>['setTransformMatrix'];
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
  children: ({ width, height, transformMatrix, contentClientRect, zoom }: ChildrenProps) => JSX.Element;
}


