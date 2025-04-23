import { Thresholds } from '../common/models';
import { Metric } from '../common/timeSeries/models';

export interface GaugeProps {
  adaptedMaxValue: number;
  baseColor?: string;
  height: number;
  hideTooltip: () => void;
  metric: Metric;
  radius: number;
  showTooltip: (args) => void;
  thresholds: Thresholds;
  width: number;
}

export enum ThresholdType {
  Warning = 0,
  Error = 1,
  Success = 2
}
