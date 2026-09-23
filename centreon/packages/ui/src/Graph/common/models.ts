import type { Metric } from './timeSeries/models';

export interface LineChartGlobal {
  base?: number;
  title?: string;
  'lower-limit'?: number;
  'upper-limit'?: number;
  [key: string]: unknown;
}

export interface LineChartData {
  global: LineChartGlobal;
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

export interface AdditionalLineProps {
  yValue: number;
  text?: string;
  color: string;
  unit: string;
}
