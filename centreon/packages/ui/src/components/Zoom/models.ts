import type { ProvidedZoom } from '@visx/zoom/lib/types';

export interface ZoomState {
  transformMatrix: {
    scaleX: number;
    scaleY: number;
    skewX: number;
    skewY: number;
    translateX: number;
    translateY: number;
  };
  setTransformMatrix?: ProvidedZoom<SVGSVGElement>['setTransformMatrix'];
}

export type MinimapPosition =
  | 'top-left'
  | 'top-right'
  | 'bottom-left'
  | 'bottom-right';

export interface ZoomInterface {
  zoom: ProvidedZoom<SVGSVGElement> & ZoomState;
}

export interface ChildrenProps extends ZoomState {
  contentClientRect: {
    height: number;
    width: number;
  } | null;
  height: number;
  width: number;
}
