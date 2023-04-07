import { ReactNode } from 'react';

import { ScaleLinear, ScaleTime } from 'd3-scale';

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
  data: Array<number>;
  ds_data: DsData;
  legend: string;
  maximum_value: number | null;
  metric: string;
  minimum_value: number | null;
  unit: string;
}

export interface GraphData {
  global;
  metrics: Array<Metric>;
  times: Array<string>;
}

export interface TimeValue {
  [field: string]: string | number;
  timeTick: string;
}

export interface Line {
  areaColor: string;
  average_value: number | null;
  color: string;
  display: boolean;
  filled: boolean;
  highlight?: boolean;
  invert: string | null;
  legend: string | null;
  lineColor: string;
  maximum_value: number | null;
  metric: string;
  minimum_value: number | null;
  name: string;
  stackOrder: number | null;
  transparency: number;
  unit: string;
}

export interface AdjustTimePeriodProps {
  end: Date;
  start: Date;
}

export enum GraphOptionId {
  displayEvents = 'displayEvents',
  displayTooltips = 'displayTooltips'
}

export interface AdditionalDataProps<T> {
  additionalData?: T | null;
}

export interface GetDisplayAdditionalLinesConditionProps {
  condition: (resource: Resource | ResourceDetails) => boolean;
  displayAdditionalLines: (args) => ReactNode;
}

export interface Xscale {
  dataTime: Array<TimeValue>;
  valueWidth: number;
}
export interface AxeScale {
  dataLines: Array<Line>;
  dataTimeSeries: Array<TimeValue>;
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

export interface AdditionalLines {
  additionalLinesProps: LinesProps;
  resource: any;
}

export interface FilterLines {
  lines: Array<Line>;
  resource: any;
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
