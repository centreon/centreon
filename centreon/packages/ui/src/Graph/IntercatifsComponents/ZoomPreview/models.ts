import { MutableRefObject } from 'react';

import { ScaleTime } from 'd3-scale';

import { InteractedZone } from '../../models';

export interface ZoomPreviewData extends InteractedZone {
  graphHeight: number;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  graphWidth: number;
  xScale: ScaleTime<number, number>;
}
