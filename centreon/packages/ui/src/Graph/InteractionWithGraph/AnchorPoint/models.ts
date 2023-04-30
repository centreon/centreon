import { ScaleLinear, ScaleTime } from 'd3-scale';

import { TimeValue } from '../../timeSeries/models';

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
