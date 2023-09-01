import { Thresholds } from '../common/models';
import { Metric } from '../common/timeSeries/models';

export interface GaugeProps {
  adaptedMaxValue: number;
  height: number;
  hideTooltip: () => void;
  metric: Metric;
  radius: number;
  showTooltip: (args) => void;
  thresholds: Thresholds;
  width: number;
}

export enum ThresholdType {
  Warning,
  Error,
  Success
}
