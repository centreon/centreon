export interface ZoomState {
  transformMatrix: {
    scaleX: number;
    scaleY: number;
    skewX: number;
    skewY: number;
    translateX: number;
    translateY: number;
  };
}

export type MinimapPosition =
  | 'top-left'
  | 'top-right'
  | 'bottom-left'
  | 'bottom-right';

export interface ChildrenProps extends ZoomState {
  contentClientRect: {
    height: number;
    width: number;
  } | null;
  height: number;
  width: number;
}
