import { MutableRefObject } from 'react';

import { ScaleTime } from 'd3-scale';

export interface ZoomBoundaries {
  end: string;
  start: string;
}

export interface ZoomPreviewData {
  graphHeight: number;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  graphWidth: number;
  xScale: ScaleTime<number, number>;
}
