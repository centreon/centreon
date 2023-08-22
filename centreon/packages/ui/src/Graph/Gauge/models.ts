import { Metric } from '../common/timeSeries/models';

export interface GaugeProps {
  adaptedMaxValue: number;
  disabledThresholds?: boolean;
  height: number;
  hideTooltip: () => void;
  metric: Metric;
  radius: number;
  showTooltip: (args) => void;
  thresholdTooltipLabels: Array<string>;
  thresholds: Array<number>;
  width: number;
}

export enum ThresholdType {
  Warning,
  Error,
  Success
}
