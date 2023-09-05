import { ScaleTime } from 'd3-scale';

import { InteractedZone } from '../../models';

export interface ZoomPreviewData extends InteractedZone {
  graphHeight: number;
  graphWidth: number;
  xScale: ScaleTime<number, number>;
}
