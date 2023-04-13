import { ScaleLinear } from 'd3-scale';

import { Line, TimeValue } from '../../timeSeries/models';

export interface ShapeGraphData {
  [x: string]: unknown;
  display: boolean;
  leftScale?: ScaleLinear<number, number>;
  rightScale?: ScaleLinear<number, number>;
  xScale?: ScaleLinear<number, number>;
  yScale?: ScaleLinear<number, number>;
}

export interface LinesData {
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

export interface AreaStackedLinesData extends ShapeGraphData {
  invertedStackedLinesData: LinesData;
  regularStackedLinesData: LinesData;
}

export interface AreaRegularLines extends ShapeGraphData {
  lines: Array<Line>;
  timeSeries: Array<TimeValue>;
}

export interface Shape {
  areaRegularLines: AreaRegularLines;
  areaStackedLines: AreaStackedLinesData;
}
