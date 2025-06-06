import { ScaleLinear, ScaleTime } from 'd3-scale';

import { ChartAxis } from '../../Chart/models';

interface DsData {
  ds_color_area: string;
  ds_color_line: string;
  ds_filled: boolean;
  ds_invert: string | null;
  ds_legend: string | null;
  ds_order: string | null;
  ds_stack: string | null;
  ds_transparency: number;
}

export interface Metric {
  average_value: number | null;
  crit: number | null;
  critical_high_threshold: number | null;
  critical_low_threshold: number | null;
  data: Array<number | null>;
  displayAs?: 'line' | 'bar';
  ds_data?: DsData;
  legend: string;
  maximum_value: number | null;
  metric: string;
  metric_id: number;
  minimum_value: number | null;
  unit: string;
  warning_high_threshold: number | null;
  warning_low_threshold: number | null;
  service_name: string | null;
  host_name: string | null;
}

type TimeSeries = { timeTick: string };

export type TimeValue = TimeSeries & {
  [field: number]: number;
};

export interface Line {
  areaColor: string;
  average_value: number | null;
  color: string;
  display: boolean;
  displayAs?: 'line' | 'bar';
  filled: boolean;
  highlight?: boolean;
  invert: string | null;
  legend: string | null;
  lineColor: string;
  maximum_value: number | null;
  metric: string;
  metric_id: number;
  minimum_value: number | null;
  name: string;
  stackOrder: number | null;
  transparency: number;
  unit: string;
}

export enum GraphOptionId {
  displayEvents = 'displayEvents',
  displayTooltips = 'displayTooltips'
}

export interface AdditionalDataProps<T> {
  additionalData?: T | null;
}

export interface Xscale {
  dataTime: Array<TimeValue>;
  valueWidth: number;
}
export interface AxeScale
  extends Pick<ChartAxis, 'isCenteredZero' | 'scale' | 'scaleLogarithmicBase'> {
  dataLines: Array<Line>;
  dataTimeSeries: Array<TimeValue>;
  isHorizontal?: boolean;
  thresholdUnit?: string;
  thresholds: Array<number>;
  valueGraphHeight: number;
}

export interface GetYScaleProps {
  hasMoreThanTwoUnits: boolean;
  invert: string | null;
  leftScale: ScaleLinear<number, number>;
  rightScale: ScaleLinear<number, number>;
  secondUnit: string;
  unit: string;
}

export interface LinesProps {
  getSortedStackedLines: (lines: Array<Line>) => Array<Line>;
  getTime: (timeValue: TimeValue) => number;
  getUnits: (lines: Array<Line>) => Array<string>;
  getYScale: ({
    hasMoreThanTwoUnits,
    unit,
    secondUnit,
    leftScale,
    rightScale,
    invert
  }: GetYScaleProps) => ScaleLinear<number, number>;
  graphHeight: number;
  graphWidth: number;
  leftScale: ScaleLinear<number, number, never>;
  lines: Array<Line>;
  rightScale: ScaleLinear<number, number, never>;
  timeSeries: Array<TimeValue>;
  xScale: ScaleTime<number, number, never>;
}

export interface NewLines {
  newLines: Array<Line>;
  newSortedLines: Array<Line>;
}

export interface FormatMetricValueProps {
  base?: number;
  unit: string;
  value: number | null;
}

export interface YScales {
  leftScale: ScaleLinear<number, number>;
  rightScale: ScaleLinear<number, number>;
}

export interface TimeValueProps {
  marginLeft?: number;
  timeSeries: Array<TimeValue>;
  x?: number;
  xScale: ScaleLinear<number, number>;
}
