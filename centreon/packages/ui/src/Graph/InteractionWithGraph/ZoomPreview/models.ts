import { ScaleTime } from 'd3-scale';

export interface ZoomBoundaries {
  end: string;
  start: string;
}

export interface ZoomPreviewData {
  eventMouseDown: MouseEvent | null;
  graphHeight: number;
  graphWidth: number;
  positionX?: number;
  xScale: ScaleTime<number, number>;
}
