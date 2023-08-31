import { Metric } from '../common/timeSeries/models';

export interface Thresholds {
  critical: Array<{
    label: string;
    value: number;
  }>;
  enabled: boolean;
  warning: Array<{
    label: string;
    value: number;
  }>;
}

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
