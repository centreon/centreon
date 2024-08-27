import { ScaleLinear, ScaleTime } from 'd3-scale';

import { Line, TimeValue } from '../../../common/timeSeries/models';

interface AnchorPoint {
  areaColor: string;
  lineColor: string;
  transparency: number;
  xScale: ScaleTime<number, number>;
  yScale: ScaleLinear<number, number>;
}

export interface RegularLinesAnchorPoint extends AnchorPoint {
  metric: string;
  timeSeries: Array<TimeValue>;
}

export interface StackedAnchorPoint extends AnchorPoint {
  stack;
}

export interface StackData {
  data: TimeValue;
}
export type StackValue = [number, number, StackData];

export interface GuidingLines {
  graphHeight: number;
  graphWidth: number;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

export interface GetYAnchorPoint {
  metric_id: number;
  timeSeries: Array<TimeValue>;
  timeTick: Date;
  yScale: ScaleLinear<number, number>;
}

export interface UseTooltipAnchorPointResult {
  tooltipDataAxisX?: string | null;
  tooltipDataAxisYLeft?: number | null;
  tooltipDataAxisYRight?: number | null;
  tooltipLeftAxisX?: number;
  tooltipLeftAxisYLeft?: number;
  tooltipLeftAxisYRight?: number;
  tooltipTopAxisX?: number;
  tooltipTopAxisYLeft?: number;
  tooltipTopAxisYRight?: number;
}

export interface TooltipAnchorModel {
  baseAxis: number;
  graphHeight: number;
  graphWidth?: number;
  leftScale?: ScaleLinear<number, number>;
  lines: Array<Line>;
  rightScale?: ScaleLinear<number, number>;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}
