import { Line } from '../common/timeSeries/models';

export interface TooltipData {
  data: Array<{
    metric: Line;
    value: number | null;
  }>;
  highlightedMetric: number;
  index: number;
}
