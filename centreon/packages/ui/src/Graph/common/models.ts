import { Metric } from './timeSeries/models';

export interface LineChartData {
  global;
  metrics: Array<Metric>;
  times: Array<string>;
}

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
