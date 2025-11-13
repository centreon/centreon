import { ScaleTime } from 'd3-scale';

import { RefObject } from 'react';
import { InteractedZone } from '../../models';

export interface ZoomPreviewData extends InteractedZone {
  graphHeight: number;
  graphWidth: number;
  xScale: ScaleTime<number, number>;
  graphSvgRef: RefObject<SVGSVGElement | null>;
  graphMarginLeft: number;
}
