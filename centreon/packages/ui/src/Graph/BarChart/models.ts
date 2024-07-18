import { Line } from '../common/timeSeries/models';

export type TooltipData =
  | {
      data: Array<{
        metric: Line;
        value: number | null;
      }>;
      highlightedMetric: number;
      index: number;
    }
  | {
      thresholdLabel: string;
    };

export interface BarStyle {
  /** The opacity of the bar between 0 and 1. */
  opacity: number;
  /** The radius of a bar between 0 and 0.5. Does not work for stacked bars */
  radius: number;
}
