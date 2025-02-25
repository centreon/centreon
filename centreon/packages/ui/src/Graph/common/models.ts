import { Metric } from './timeSeries/models';

export interface LineChartData {
  global;
  metrics: Array<Metric>;
  times: Array<string>;
}

export interface Threshold {
  label: string;
  value: number;
}

export interface Thresholds {
  critical: Array<Threshold>;
  enabled: boolean;
  warning: Array<Threshold>;
}
